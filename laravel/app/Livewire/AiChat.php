<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;

class AiChat extends Component
{
    public function clearSlots()
    {
        $this->conversationContext['slots'] = [];
        session()->put('ai_chat_context', $this->conversationContext);
    }
    public $organizations;
    public $selectedOrgId = null;
    public $query = '';
    public $messages = [];
    public $isLoading = false;
    public $conversationContext = []; // Track conversation entities and context

    public function mount()
    {
        $this->organizations = Organization::all();
        $this->loadConversationFromSession();
    }

    private function loadConversationFromSession()
    {
        $this->messages = session()->get('ai_chat_messages', []);
        $this->conversationContext = session()->get('ai_chat_context', []);
    }

    private function saveConversationToSession()
    {
        session()->put('ai_chat_messages', $this->messages);
        session()->put('ai_chat_context', $this->conversationContext);
    }

    public function sendMessage()
{
    if (empty($this->query) || !$this->selectedOrgId) {
        return;
    }

    $this->isLoading = true;
    $userMessage = $this->query;
    $this->query = '';

    // Add user message
    $this->messages[] = [
        'role' => 'user',
        'content' => $userMessage,
        'timestamp' => now()
    ];

    $aiService = app(\App\Services\AiAgentService::class);

    // ---- SESSION MEMORY (short-term) ----
    $sessionMemory = session()->get('ai_chat_memory', []); // arbitrary key/values

    // Keep history short (last 4 user/assistant turns only) to reduce tokens
    $recentMessages = array_slice(
        array_values(array_filter($this->messages, fn($m) => in_array($m['role'], ['user','assistant']))),
        -4
    );

    $t0 = microtime(true);
    $perf = [];
    $perf['start'] = $t0;
    // ---- DIRECT QUERY FAST NLU BYPASS CHECK ----
    $maybeDirect = $this->isDirectQuery($userMessage);
    $bypassedNLU = false;

    // Prepare defaults
    $intentName = 'general_qna';
    $slots = $this->conversationContext['slots'] ?? [];
    $rewritten = $userMessage;

    if ($maybeDirect) {
        // Skip NLU fully
        $bypassedNLU = true;
        \Log::info('NLU bypassed for direct query', ['query' => $userMessage]);
    } else {
        // ---- UNIFIED NLU: slots + intent + rewritten query in ONE CALL ----
        // Clear slots before NLU for stateless extraction
        $this->clearSlots();
        $existingSlots = $this->conversationContext['slots'] ?? [];

// 🔹 Shorter system prompt for 1B
$nluSystem = <<<PROMPT
Return STRICT JSON ONLY in this shape:
{
  "intent": "general_qna|booking|pricing|timing|directions|contact_info|troubleshooting|other",
  "slots": {
    "organization"?: string,
    "service"?: string,
    "date"?: string,   // ISO 8601
    "time"?: string,   // ISO 8601
    "location"?: string,
    "person"?: string,
    "quantity"?: string|number,
    "price"?: string|number,
    "email"?: string,
    "phone"?: string
  },
  "rewritten_query": "single explicit query with slot values inline"
}
Rules:
- Merge with provided slots, prefer explicit new values
- Do not invent slots
- Use ISO for date/time if present
- Output JSON ONLY
PROMPT;

$nluUser = [
    'utterance'       => $userMessage,
    'existing_slots'  => $existingSlots,
    'recent_messages' => $recentMessages,
    'memory'          => $sessionMemory,
];

        // 🔹 1B as primary, 3B as fallback
        $nluModels = ['llama3.2:1b', 'llama3.2:3b'];
        $nlu = null;
        foreach ($nluModels as $mdl) {
            $nluResp = $aiService->llmChat([
                ['role' => 'system', 'content' => $nluSystem],
                ['role' => 'user',   'content' => json_encode($nluUser, JSON_UNESCAPED_UNICODE)]
            ], $mdl);
            $raw = $nluResp['message']['content'] ?? '';
            $clean = $this->sanitizeJsonContent($raw);
            $parsed = json_decode($clean, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($parsed)) {
                $nlu = $parsed;
                \Log::info('Unified NLU parsed', ['model' => $mdl, 'json' => $parsed]);
                break;
            } else {
                \Log::warning('Unified NLU JSON parse failed', [
                    'model' => $mdl,
                    'raw'   => mb_substr($raw, 0, 300)
                ]);
            }
        }
        $perf['after_nlu'] = microtime(true);
        if ($nlu) {
            $intentName = $nlu['intent'] ?? $intentName;
            $incomingSlots = is_array($nlu['slots'] ?? null) ? $nlu['slots'] : [];
            $slots = array_filter(array_merge($slots, $incomingSlots), fn($v) => !is_null($v) && $v !== '');
            $rewritten = !empty($nlu['rewritten_query'] ?? '') ? $nlu['rewritten_query'] : $rewritten;
        }
    }

    $this->conversationContext['slots'] = $slots;

    // If the user query is a simple direct question, bypass rewritten query and use original
    $directBypass = false;
    if ($this->isDirectQuery($userMessage)) {
        $rewritten = $userMessage; // force original for retrieval
        $directBypass = true;
    }

    // ---- REQUIRED SLOTS for specific intents ----
    $requiredByIntent = [
        'booking' => ['service','date','time','person'],
        'pricing' => ['service'],
        'timing'  => ['service'],
    ];
    $missing = [];
    if (isset($requiredByIntent[$intentName])) {
        foreach ($requiredByIntent[$intentName] as $s) {
            if (empty($slots[$s] ?? null)) $missing[] = $s;
        }
    }

    if (!empty($missing) && $intentName === 'booking') {
        $ask = "To proceed with booking, we need: " . implode(', ', $missing) . ". Please provide these.";
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $ask,
            'timestamp' => now()
        ];
        $this->saveConversationToSession();
        $this->isLoading = false;
        return;
    }

    // ---- RETRIEVAL (Qdrant via nomic embeddings) ----
    $allContext = [];
    $topContext = [];
    $context = '';
    try {
        // Get organization slug for collection name
        $organization = Organization::find($this->selectedOrgId);
        $orgSlug = $organization ? str_replace('-', '_', $organization->slug) : "org_{$this->selectedOrgId}";
        $collectionName = $orgSlug;
        // Fetch all keywords from collection for context expansion
        $allKeywords = [];
        $embedStart = microtime(true);
        $initialEmbedding = $aiService->embed($rewritten, 'nomic-embed-text');
        $perf['embed_initial_ms'] = (microtime(true) - $embedStart) * 1000;
        $searchStart = microtime(true);
        $searchResults = $aiService->searchQdrant($collectionName, $initialEmbedding, 20);
        $perf['search_initial_ms'] = (microtime(true) - $searchStart) * 1000;
        if ($searchResults && isset($searchResults['results'])) {
            foreach ($searchResults['results'] as $result) {
                $payload = $result['payload'] ?? [];
                if (!empty($payload['keywords'])) {
                    $allKeywords[] = $payload['keywords'];
                }
            }
        }
        // Expand query with found keywords
        $expandedQuery = $rewritten;
        if (!empty($allKeywords)) {
            $expandedQuery .= ' ' . implode(' ', $allKeywords);
        }
        if ($expandedQuery === $rewritten) {
            // Reuse embedding, no need for second call
            $embedding = $initialEmbedding;
            $perf['embed_reused'] = true;
        } else {
            $embed2Start = microtime(true);
            $embedding = $aiService->embed($expandedQuery, 'nomic-embed-text');
            $perf['embed_second_ms'] = (microtime(true) - $embed2Start) * 1000;
            $perf['embed_reused'] = false;
        }
        $search2Start = microtime(true);
        $searchResults = $aiService->searchQdrant($collectionName, $embedding, 5);
        $perf['search_final_ms'] = (microtime(true) - $search2Start) * 1000;
        $allContext = [];
        if ($searchResults && isset($searchResults['results'])) {
            foreach ($searchResults['results'] as $result) {
                $payload = $result['payload'] ?? [];
                $allContext[] = [
                    'score'   => $result['score'] ?? null,
                    'type'    => 'data',
                    'content' => $payload
                ];
            }
        }
        $perf['retrieval_done'] = microtime(true);

        // Rank + top 3
        if (!empty($allContext)) {
            usort($allContext, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
            $topContext = array_slice($allContext, 0, 3);
        }

        // Build flattened text context for LLM and logs
        if (!empty($topContext)) {
            $context = "Relevant information:\n\n";
            $uniquePayloads = [];
            foreach ($topContext as $item) {
                $payload = $item['content'] ?? [];
                // Use description+content+keywords as deduplication key
                $desc = isset($payload['description']) ? trim($payload['description']) : '';
                $cont = isset($payload['content']) ? trim($payload['content']) : '';
                $keywords = isset($payload['keywords']) ? trim($payload['keywords']) : '';
                $dedupKey = md5($desc . $cont . $keywords);
                if (isset($uniquePayloads[$dedupKey])) continue;
                $uniquePayloads[$dedupKey] = $payload;
            }
            foreach ($uniquePayloads as $payload) {
                // Only use description for context and logs
                if (isset($payload['description']) && ($desc = trim($payload['description'])) !== '') {
                    $context .= "Description: " . (strlen($desc) > 300 ? substr($desc, 0, 300) . '...' : $desc) . "\n";
                }
                // Optionally include other key fields (e.g., name, category, keywords) if needed
                // Example:
                // if (isset($payload['name']) && ($name = trim($payload['name'])) !== '') {
                //     $context .= "Name: $name\n";
                // }
                $context .= "\n";
            }
        } else {
            $context = "No specific information found in the knowledge base.";
        }

    \Log::debug('AIChat context (flattened)', ['context' => $context]);
    $perf['context_ready'] = microtime(true);

    } catch (\Throwable $t) {
        \Log::warning('Retrieval step failed', ['error' => $t->getMessage()]);
        $context = "Retrieval failed. Provide a general response and suggest contacting the organization.";
        $topContext = [];
    }

    // ---- FAST PATH: direct answer for very simple factual queries (address / phone) ----
    $orgName = optional(Organization::find($this->selectedOrgId))->name ?? 'this organization';
    $fastAnswered = false;
    $lowerQ = strtolower($userMessage);
    $isSimpleContactQuery = (bool)preg_match('/\b(address|location|where are you|phone|mobile|contact)\b/', $lowerQ);
    if ($directBypass && $isSimpleContactQuery && !empty($topContext)) {
        // Extract first description block
        $rawDesc = '';
        foreach ($topContext as $ctxItem) {
            $payload = $ctxItem['content'] ?? [];
            if (!empty($payload['description'])) { $rawDesc = $payload['description']; break; }
        }
        if ($rawDesc) {
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $rawDesc)));
            $info = [
                'address' => null,
                'telephone' => null,
                'mobile' => null,
                'email' => null,
                'contact_person' => null,
                'website' => null,
                'hours' => null,
                'pricing' => null
            ];
            foreach ($lines as $l) {
                $lc = strtolower($l);
                if (!$info['email'] && preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+/i', $l)) $info['email'] = $l;
                if (!$info['website'] && preg_match('/https?:\/\//i', $l)) $info['website'] = $l;
                if (!$info['telephone'] && preg_match('/telephone\s*:\s*(.+)/i', $l, $m)) $info['telephone'] = trim($m[1]);
                if (!$info['mobile'] && preg_match('/mobile\s*-\s*(.+)/i', $l, $m)) $info['mobile'] = trim($m[1]);
                if (!$info['contact_person'] && preg_match('/contact\s*-\s*(.+)/i', $l, $m)) $info['contact_person'] = trim($m[1]);
                if (!$info['hours'] && preg_match('/(mon|tue|wed|thu|fri|sat|sun).*\d{1,2}\s*(am|pm)/i', $l)) $info['hours'] = $l;
                if (!$info['pricing'] && preg_match('/(price|cost|rs\.?|₹)/i', $l)) $info['pricing'] = $l;
            }
            // Address heuristic: first long line containing market / road / odisha if not already used
            foreach ($lines as $l) {
                if ($info['address']) break;
                if (preg_match('/(market|road|street|odisha|sambalpur|block|sector|lane|avenue|dist\.?)/i', $l)) {
                    $info['address'] = $l;
                }
            }
            // Build professional compact response
            $parts = [];
            if ($info['address']) $parts[] = 'Our address: ' . $info['address'];
            if ($info['telephone']) $parts[] = 'Telephone: ' . $info['telephone'];
            if ($info['mobile']) $parts[] = 'Mobile: ' . $info['mobile'];
            if ($info['email']) $parts[] = 'Email: ' . $info['email'];
            if ($info['contact_person']) $parts[] = 'Contact: ' . $info['contact_person'];
            if ($info['website']) $parts[] = 'Website: ' . $info['website'];
            if ($info['hours']) $parts[] = 'Hours: ' . $info['hours'];
            if ($info['pricing']) $parts[] = 'Pricing: ' . $info['pricing'];
            if (!empty($parts)) {
                // Limit to two sentences: first sentence address (if present) + second with rest joined by; separators
                $addressSentence = '';
                $otherSentence = '';
                if ($info['address']) {
                    $addressSentence = $parts[0];
                    // remove first part from list for second sentence
                    array_shift($parts);
                }
                if (!empty($parts)) {
                    $otherSentence = implode('; ', $parts);
                }
                $answer = trim($addressSentence . ( $otherSentence ? '. ' . $otherSentence : '' )) . '.';
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $answer,
                    'timestamp' => now()
                ];
                \Log::info('Fast path contact answer used', [
                    'query' => $userMessage,
                    'answer' => $answer,
                    'generation_skipped' => true
                ]);
                $fastAnswered = true;
            }
        }
    }

    if ($fastAnswered) {
        // Persist, log perf & exit early
        $this->saveConversationToSession();
        $this->isLoading = false;
        $perf['end'] = microtime(true);
        $perfSummary = [
            'direct_bypass' => $directBypass,
            'fast_path' => true,
            'nlu_ms' => isset($perf['after_nlu']) ? round( ($perf['after_nlu'] - $perf['start']) * 1000, 1) : null,
            'embed_initial_ms' => $perf['embed_initial_ms'] ?? null,
            'search_initial_ms' => $perf['search_initial_ms'] ?? null,
            'embed_second_ms' => $perf['embed_second_ms'] ?? 0,
            'embed_reused' => $perf['embed_reused'] ?? null,
            'search_final_ms' => $perf['search_final_ms'] ?? null,
            'generation_ms' => 0,
            'total_ms' => round( ($perf['end'] - $perf['start']) * 1000, 1)
        ];
        \Log::info('AiChat performance', $perfSummary);
        return; // skip LLM generation
    }

    // ---- SYSTEM PROMPT (compressed) ----
    $systemPrompt = "Answer strictly for {$orgName} using ONLY provided context + user message. Speak as {$orgName} (we/our). Never say you are AI or use I/me/my. If address/price/timing/contact present: answer directly. If missing info: brief follow-up or advise contacting {$orgName}. Keep to <=2 concise factual sentences.";

    $contextSummary = [
        'slots'     => $slots,
        'intent'    => $intentName,
        'rewritten' => $rewritten,
        'retrieval' => $topContext
    ];

    // Minimize message count: merge context summary + snippets into one system message
    $mergedContext = 'Context JSON: ' . json_encode($contextSummary, JSON_UNESCAPED_UNICODE) . "\n" . $context;
    $chatMessages = [
        ['role' => 'system', 'content' => $systemPrompt . "\n" . $mergedContext],
    ];
    // Only include last one user + assistant if available to cut tokens further
    $trimmedRecent = array_slice($recentMessages, -2);
    foreach ($trimmedRecent as $rm) { $chatMessages[] = $rm; }

    // ---- LLM ANSWER (final) ----
    $genStart = microtime(true);
    $response = $aiService->llmChat($chatMessages, 'llama3.2:3b');
    $perf['generation_ms'] = (microtime(true) - $genStart) * 1000;

    if ($response && isset($response['message']['content'])) {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $response['message']['content'],
            'timestamp' => now()
        ];
    } else {
        $this->messages[] = [
            'role' => 'system',
            'content' => 'We ran into an issue generating a response. Please try again.',
            'timestamp' => now()
        ];
    }

    // ---- Persist minimal memory (example) ----
    if (!empty($intentName)) $sessionMemory['last_intent'] = $intentName;
    if (!empty($slots['service'] ?? null)) $sessionMemory['last_service'] = $slots['service'];
    session()->put('ai_chat_memory', $sessionMemory);

    $this->saveConversationToSession();
    $this->isLoading = false;
    $perf['end'] = microtime(true);
    $perfSummary = [
        'direct_bypass' => $directBypass,
        'nlu_bypassed' => $bypassedNLU,
        'nlu_ms' => isset($perf['after_nlu']) ? round( ($perf['after_nlu'] - $perf['start']) * 1000, 1) : null,
        'embed_initial_ms' => $perf['embed_initial_ms'] ?? null,
        'search_initial_ms' => $perf['search_initial_ms'] ?? null,
        'embed_second_ms' => $perf['embed_second_ms'] ?? 0,
        'embed_reused' => $perf['embed_reused'] ?? null,
        'search_final_ms' => $perf['search_final_ms'] ?? null,
        'generation_ms' => $perf['generation_ms'] ?? null,
        'total_ms' => round( ($perf['end'] - $perf['start']) * 1000, 1)
    ];
    \Log::info('AiChat performance', $perfSummary);
}
    private function sanitizeJsonContent(string $txt): string
    {
        $t = trim($txt);
        if (strpos($t, '```') === 0) {
            // strip starting fence (with or without "json")
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
            // strip ending fence
            $t = preg_replace('/\s*```$/', '', $t);
        }
        return trim($t);
    }

    /**
     * Heuristic to decide if a query is "direct" and should skip rewriting.
     * Criteria: short length, contains a core info keyword, no anaphora pronouns that need resolution.
     */
    private function isDirectQuery(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') return false;
        if (mb_strlen($t) > 80) return false; // too long -> probably needs rewrite
        $keywords = ['address','location','phone','mobile','email','contact','price','cost','timing','hours','opening','close','website'];
        $hasKeyword = false;
        foreach ($keywords as $k) {
            if (str_contains($t, $k)) { $hasKeyword = true; break; }
        }
        if (!$hasKeyword) return false;
        // If contains ambiguous pronouns likely needing context, skip direct
        if (preg_match('/\b(it|that|there|they|them|those)\b/', $t)) return false;
        // Basic question structure or imperative like 'give address'
        if (preg_match('/^(what|where|give|show|list|provide|tell)\b/', $t) || str_contains($t, '?')) {
            return true;
        }
        return false;
    }

    /**
     * Track entities and topics mentioned in conversation for reference resolution
     */
    private function updateConversationContext($userMessage, $assistantResponse, $topContext)
    {
        // Extract service/test names mentioned
        $services = [];
        foreach ($topContext as $item) {
            if (isset($item['content']['title'])) {
                $title = $item['content']['title'];
                if (stripos($userMessage . ' ' . $assistantResponse, $title) !== false) {
                    $services[] = $title;
                }
            }
        }

        // Store recent services mentioned
        $this->conversationContext['recent_services'] = array_unique(array_merge(
            $this->conversationContext['recent_services'] ?? [],
            $services
        ));

        // Keep only last 5 services to avoid memory bloat
        $this->conversationContext['recent_services'] = array_slice(
            $this->conversationContext['recent_services'],
            -5
        );

        // Store last topic discussed
        if (!empty($services)) {
            $this->conversationContext['last_topic'] = $services[0];
        }
    }

    public function clearChat()
    {
        $this->messages = [];
        $this->conversationContext = [];
        session()->forget(['ai_chat_messages', 'ai_chat_context']);
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
