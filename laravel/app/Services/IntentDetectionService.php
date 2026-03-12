<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\Organization;
use App\Models\AdminSetting;

class IntentDetectionService
{
    private AiAgentService $aiAgent;

    // Rule-based keywords for quick intent detection.
    // IMPORTANT: 'available'/'availability' are NOT booking signals — they indicate stock/status checks
    // (realtime_data). Only classify as 'booking' when the user explicitly wants to MAKE a reserveration
    // or appointment. This avoids mis-classifying product catalog queries as booking requests.
    private const INTENT_KEYWORDS = [
        'booking' => [
            'reserve', 'book a', 'book an', 'booking', 'reservation',
            'appointment', 'can i book', 'can i reserve', 'can i schedule',
            'make a reservation', 'schedule an appointment', 'book a slot',
            'book a table', 'book a room'
        ],
        'pricing' => [
            'price', 'cost', 'fee', 'charge', 'rate', 'pricing', 'how much',
            'tuition', 'fees', 'payment', 'bill', 'invoice', 'expense'
        ],
        'realtime_data' => [
            'available', 'availability', 'check availability', 'in stock', 'out of stock',
            'is it available', 'slot available', 'room available', 'in your store',
            'current', 'now', 'today', 'live', 'real-time', 'status', 'check',
            'inventory', 'stock', 'balance', 'account', 'latest', 'update'
        ],
        'lookup' => [
            'find', 'search', 'lookup', 'get', 'show', 'list', 'display',
            'which', 'what', 'where', 'when', 'who', 'how many'
        ],
        'static_info' => [
            'policy', 'rule', 'guideline', 'procedure', 'faq', 'help',
            'information', 'about', 'description', 'explain', 'what is'
        ]
    ];

    // Action trigger patterns
    private const ACTION_PATTERNS = [
        'availability_check' => [
            'available', 'vacancy', 'free', 'open slot', 'check availability'
        ],
        'data_retrieval' => [
            'get data', 'show me', 'list all', 'find records', 'search for'
        ],
        'status_check' => [
            'status', 'current state', 'live data', 'real-time'
        ]
    ];

    // Example phrases for embedding-based intent matching.
    // Booking examples must be appointment/reservation-making phrases ONLY.
    // Availability/stock checking belongs to realtime_data, not booking.
    private const INTENT_EXAMPLES = [
        'booking' => [
            'book an appointment',
            'reserve a slot for me',
            'I want to make a reservation',
            'can I schedule an appointment'
        ],
        'pricing' => [
            'what is the price',
            'how much does it cost',
            'pricing and fees',
            'what is the cost of this product'
        ],
        'realtime_data' => [
            'is this item available',
            'is it in stock',
            'check availability of the product',
            'do you have this in your store',
            'live inventory status'
        ],
        'lookup' => [
            'search for a record',
            'find a product',
            'look up details',
            'show me information about'
        ],
        'static_info' => [
            'return policy information',
            'tell me about your services',
            'general FAQ and help',
            'what is your refund policy'
        ]
    ];

    public function __construct(AiAgentService $aiAgent)
    {
        $this->aiAgent = $aiAgent;
    }

    /**
     * Detect intent from user query using hybrid approach
     */
    public function detectIntent(string $query, int $organizationId): array
    {
        $query = strtolower(trim($query));

        $settings = $this->getIntentSettings($organizationId);
        $strategy = $settings['intent_strategy'];
        $ruleThreshold = $settings['intent_rule_threshold'];
        $embeddingThreshold = $settings['intent_embedding_threshold'];
        $useLlm = $settings['intent_use_llm'];
        
        Log::info('Intent detection started', [
            'query' => $query,
            'organization_id' => $organizationId
        ]);

        // Step 1: Rule-based quick detection
        $ruleBasedIntent = $this->detectIntentByRules($query, $organizationId);

        if ($strategy === 'rules_only' || $ruleBasedIntent['confidence'] >= $ruleThreshold) {
            $finalIntent = array_merge($ruleBasedIntent, [
                'method' => 'rule_primary'
            ]);
        } else {
            $embeddingIntent = null;
            $llmIntent = null;

            if (in_array($strategy, ['rules_then_embedding', 'hybrid'], true)) {
                $embeddingIntent = $this->detectIntentWithEmbeddings($query);
                if ($embeddingIntent && ($embeddingIntent['confidence'] ?? 0) >= $embeddingThreshold) {
                    $finalIntent = array_merge($embeddingIntent, [
                        'rule_backup' => $ruleBasedIntent,
                        'method' => 'embedding_primary'
                    ]);
                }
            }

            if (!isset($finalIntent) && in_array($strategy, ['rules_then_llm', 'hybrid'], true) && $useLlm) {
                $llmIntent = $this->detectIntentWithLLM($query, $settings);
            }

            if (!isset($finalIntent)) {
                $finalIntent = $this->combineIntentResults($ruleBasedIntent, $llmIntent);
                if ($embeddingIntent) {
                    $finalIntent['embedding_backup'] = $embeddingIntent;
                }
            }
        }
        
        Log::info('Intent detection completed', [
            'query' => $query,
            'rule_based' => $ruleBasedIntent,
            'llm_based' => $llmIntent ?? null,
            'final_intent' => $finalIntent
        ]);

        return $finalIntent;
    }

    /**
     * Rule-based intent detection using keywords
     */
    private function detectIntentByRules(string $query, int $organizationId = 0): array
    {
        $scores = [];
        $orgKeywords = $this->getOrgIntentKeywords($organizationId);
        
        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            $mergedKeywords = array_merge($keywords, $orgKeywords[$intent] ?? []);
            $score = 0;
            $matches = 0;
            
            foreach ($mergedKeywords as $keyword) {
                if (Str::contains($query, $keyword)) {
                    $score += 1;
                    $matches++;
                    
                    // Boost score for exact matches
                    if ($keyword === $query || Str::startsWith($query, $keyword)) {
                        $score += 0.5;
                    }
                }
            }
            
            if ($score > 0) {
                // Normalize score based on keyword density and query length
                $normalizedScore = min($score / max(1, str_word_count($query) / 2), 1.0);
                $scores[$intent] = $normalizedScore;
            }
        }

        if (empty($scores)) {
            return [
                'intent' => 'static_info',
                'confidence' => 0.3,
                'method' => 'rule_default',
                'matches' => []
            ];
        }

        // Get highest scoring intent
        arsort($scores);
        $topIntent = array_key_first($scores);
        $topScore = $scores[$topIntent];

        return [
            'intent' => $topIntent,
            'confidence' => $topScore,
            'method' => 'rule_based',
            'all_scores' => $scores
        ];
    }

    private function getOrgIntentKeywords(int $organizationId): array
    {
        if (!$organizationId) {
            return [];
        }

        $org = Organization::find($organizationId);
        if (!$org) {
            return [];
        }

        $keywords = $org->settings['intent_keywords'] ?? [];
        if (!is_array($keywords)) {
            return [];
        }

        return $keywords;
    }

    /**
     * LLM-based intent detection for complex queries
     */
    private function detectIntentWithLLM(string $query, array $settings = []): ?array
    {
        try {
            // Use Vast.ai GPU with llama3:8b for faster intent detection (3-4s vs 26s local)
            $intentModel = 'llama3:8b-instruct-q5_K_M';
            $maxTokens = $settings['intent_llm_max_tokens'] ?? 64;
            $temperature = $settings['intent_llm_temperature'] ?? 0.1;
            $topP = $settings['intent_llm_top_p'] ?? 0.85;
            $repeatPenalty = $settings['intent_llm_repeat_penalty'] ?? 1.05;

            $systemPrompt = "You are an intent classifier. Choose exactly one intent: booking, pricing, realtime_data, lookup, or static_info. Rules: (1) Use 'booking' ONLY when the user explicitly wants to MAKE or SCHEDULE an appointment/reservation — NOT for stock or product availability checks. (2) Use 'realtime_data' when the user is checking availability, stock status, or whether a product/service exists. (3) 'Is X available?' about a product = realtime_data. 'Book an appointment' = booking. Mark action_needed=true only when the user wants to do something (book, check live data, search/lookup). Respond with JSON only: {\\\"intent\\\":\\\"...\\\", \\\"confidence\\\":0-1, \\\"action_needed\\\":true/false, \\\"reasoning\\\":\\\"<=12 words\\\"}. No prose.";

            $options = [
                'num_predict' => $maxTokens,
                'temperature' => $temperature,
                'top_p' => $topP,
                'repeat_penalty' => $repeatPenalty,
                'use_vastai' => true,  // Force Vast.ai GPU for speed
            ];

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query]
            ];

            $response = $this->aiAgent->smartLlmChat($messages, $intentModel, null, null, $options);
            
            if ($response && isset($response['message']['content'])) {
                $content = trim($response['message']['content']);
                $content = trim($content, "\n\r\t \"");
                $result = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    return array_merge($result, ['method' => 'llm_based']);
                }

                $parsed = $this->parseLooseIntentResponse($content);
                if ($parsed) {
                    return array_merge($parsed, ['method' => 'llm_based']);
                }
            }

        } catch (\Exception $e) {
            Log::error('LLM intent detection failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function parseLooseIntentResponse(string $content): ?array
    {
        $allowedIntents = ['booking', 'pricing', 'realtime_data', 'lookup', 'static_info'];

        $intent = null;
        if (preg_match('/intent\s*[:=]\s*([a-z_]+)/i', $content, $match)) {
            $intent = strtolower($match[1]);
        }
        if (!$intent || !in_array($intent, $allowedIntents, true)) {
            return null;
        }

        $confidence = null;
        if (preg_match('/confidence\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $content, $match)) {
            $confidence = (float) $match[1];
            if ($confidence > 1) {
                $confidence = $confidence / 100.0;
            }
            $confidence = max(0.0, min(1.0, $confidence));
        }

        $actionNeeded = null;
        if (preg_match('/action\s*needed\s*[:=]\s*(true|false)/i', $content, $match)) {
            $actionNeeded = strtolower($match[1]) === 'true';
        }

        if ($actionNeeded === null) {
            $actionNeeded = in_array($intent, ['booking', 'realtime_data', 'lookup', 'pricing'], true);
        }

        $reasoning = null;
        if (preg_match('/reasoning\s*[:=]\s*([^\n\r\}]+)/i', $content, $match)) {
            $reasoning = trim($match[1]);
        }

        return [
            'intent' => $intent,
            'confidence' => $confidence ?? 0.6,
            'action_needed' => $actionNeeded,
            'reasoning' => $reasoning ?? 'llm_loose_parse',
        ];
    }

    /**
     * Combine rule-based and LLM results intelligently
     */
    private function combineIntentResults(array $ruleResult, ?array $llmResult): array
    {
        // If LLM failed or wasn't called, use rule-based result
        if (!$llmResult) {
            return $ruleResult;
        }

        $ruleConfidence = $ruleResult['confidence'];
        $llmConfidence = $llmResult['confidence'] ?? 0;

        // If rule-based is high confidence, trust it
        if ($ruleConfidence >= 0.8) {
            return array_merge($ruleResult, [
                'llm_backup' => $llmResult,
                'method' => 'rule_primary'
            ]);
        }

        // If LLM is high confidence, trust it
        if ($llmConfidence >= 0.8) {
            return array_merge($llmResult, [
                'rule_backup' => $ruleResult,
                'method' => 'llm_primary'
            ]);
        }

        // Both moderate confidence - combine them
        if ($ruleResult['intent'] === $llmResult['intent']) {
            // Intents agree - boost confidence
            return array_merge($llmResult, [
                'confidence' => min(($ruleConfidence + $llmConfidence) / 1.5, 1.0),
                'method' => 'combined_agreement',
                'rule_backup' => $ruleResult
            ]);
        }

        // Intents disagree - use higher confidence one
        if ($llmConfidence > $ruleConfidence) {
            return array_merge($llmResult, [
                'method' => 'llm_preferred',
                'rule_backup' => $ruleResult
            ]);
        } else {
            return array_merge($ruleResult, [
                'method' => 'rule_preferred',
                'llm_backup' => $llmResult
            ]);
        }
    }

    /**
     * Embedding-based intent detection for fast, stable routing
     */
    private function detectIntentWithEmbeddings(string $query): ?array
    {
        try {
            $queryEmbedding = $this->aiAgent->embed($query);
            if (!$queryEmbedding || !is_array($queryEmbedding)) {
                return null;
            }

            $intentEmbeddings = $this->getCachedIntentEmbeddings();
            if (empty($intentEmbeddings)) {
                return null;
            }

            $bestIntent = null;
            $bestScore = 0.0;

            foreach ($intentEmbeddings as $intent => $embeddings) {
                foreach ($embeddings as $embedding) {
                    $score = $this->calculateCosineSimilarity($queryEmbedding, $embedding);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIntent = $intent;
                    }
                }
            }

            if (!$bestIntent) {
                return null;
            }

            return [
                'intent' => $bestIntent,
                'confidence' => $bestScore,
                'action_needed' => in_array($bestIntent, ['booking', 'realtime_data', 'lookup', 'pricing'], true),
                'reasoning' => 'embedding_match',
                'method' => 'embedding'
            ];
        } catch (\Exception $e) {
            Log::warning('Embedding intent detection failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getCachedIntentEmbeddings(): array
    {
        return Cache::remember('intent_embeddings_v2', 3600, function () {
            $intentEmbeddings = [];
            $allTexts = [];
            $intentMap = [];

            foreach (self::INTENT_EXAMPLES as $intent => $examples) {
                foreach ($examples as $example) {
                    $intentMap[] = $intent;
                    $allTexts[] = $example;
                }
            }

            $embeddings = $this->aiAgent->embedBatch($allTexts);
            if (!$embeddings || !is_array($embeddings)) {
                return [];
            }

            foreach ($embeddings as $index => $embedding) {
                $intent = $intentMap[$index] ?? null;
                if (!$intent || !$embedding || !is_array($embedding)) {
                    continue;
                }
                $intentEmbeddings[$intent][] = $embedding;
            }

            return $intentEmbeddings;
        });
    }

    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($vectorA), count($vectorB));
        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function getIntentSettings(int $organizationId): array
    {
        $org = Organization::find($organizationId);
        $settings = $org?->settings ?? [];

        $getGlobal = function (string $key, $default = null) {
            return class_exists(AdminSetting::class)
                ? AdminSetting::get($key, $default)
                : $default;
        };

        return [
            'intent_strategy' => $settings['intent_strategy']
                ?? $getGlobal('intent_strategy', 'hybrid'),
            'intent_rule_threshold' => (float) ($settings['intent_rule_threshold']
                ?? $getGlobal('intent_rule_threshold', 0.8)),
            'intent_embedding_threshold' => (float) ($settings['intent_embedding_threshold']
                ?? $getGlobal('intent_embedding_threshold', 0.75)),
            'intent_use_llm' => (bool) ($settings['intent_use_llm']
                ?? $getGlobal('intent_use_llm', true)),
            'intent_llm_model' => $settings['intent_llm_model']
                ?? $getGlobal('intent_llm_model', null),
            'intent_llm_max_tokens' => (int) ($settings['intent_llm_max_tokens']
                ?? $getGlobal('intent_llm_max_tokens', 64)),
            'intent_llm_temperature' => (float) ($settings['intent_llm_temperature']
                ?? $getGlobal('intent_llm_temperature', 0.1)),
            'intent_llm_top_p' => (float) ($settings['intent_llm_top_p']
                ?? $getGlobal('intent_llm_top_p', 0.85)),
            'intent_llm_repeat_penalty' => (float) ($settings['intent_llm_repeat_penalty']
                ?? $getGlobal('intent_llm_repeat_penalty', 1.05)),
        ];
    }

    /**
     * Check if query matches specific action patterns
     */
    public function matchesActionPattern(string $query, array $actionKeywords = []): float
    {
        $query = strtolower($query);
        $matches = 0;
        $totalKeywords = count($actionKeywords);

        if ($totalKeywords === 0) {
            return 0.0;
        }

        foreach ($actionKeywords as $keyword) {
            if (Str::contains($query, strtolower($keyword))) {
                $matches++;
            }
        }

        return $matches / $totalKeywords;
    }

    /**
     * Determine if query requires real-time action vs static knowledge
     */
    public function requiresAction(array $intentResult): bool
    {
        $intent = $intentResult['intent'] ?? 'static_info';
        $actionNeeded = $intentResult['action_needed'] ?? false;

        // These intents typically require actions
        $actionIntents = ['booking', 'realtime_data', 'lookup', 'pricing'];
        
        return $actionNeeded || in_array($intent, $actionIntents);
    }

    /**
     * Get recommended action types for the detected intent
     */
    public function getRecommendedActionTypes(array $intentResult): array
    {
        $intent = $intentResult['intent'] ?? 'static_info';

        return match ($intent) {
            'booking' => ['availability', 'booking', 'schedule'],
            'pricing' => ['pricing', 'cost', 'rates'],
            'realtime_data' => ['status', 'inventory', 'balance'],
            'lookup' => ['search', 'records', 'custom'],
            'static_info' => [], // Use knowledge base instead
            default => ['search', 'custom']
        };
    }

    /**
     * Extract temporal context (dates, times) from query
     */
    public function extractTemporalContext(string $query): array
    {
        $query = strtolower($query);
        $context = [];

        // Date patterns
        $datePatterns = [
            'today' => date('Y-m-d'),
            'tomorrow' => date('Y-m-d', strtotime('+1 day')),
            'next week' => date('Y-m-d', strtotime('+1 week')),
            'next month' => date('Y-m-d', strtotime('+1 month')),
        ];

        foreach ($datePatterns as $pattern => $date) {
            if (Str::contains($query, $pattern)) {
                $context['date'] = $date;
                $context['date_source'] = $pattern;
                break;
            }
        }

        // Time patterns
        if (preg_match('/(\d{1,2})(:\d{2})?\s*(am|pm)/i', $query, $matches)) {
            $context['time'] = $matches[0];
        }

        // Quantity patterns
        if (preg_match('/(\d+)\s*(guest|people|person|room|night)/i', $query, $matches)) {
            $context['quantity'] = (int)$matches[1];
            $context['quantity_type'] = $matches[2];
        }

        return $context;
    }
}