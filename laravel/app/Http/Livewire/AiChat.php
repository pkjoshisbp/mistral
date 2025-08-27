<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;

class AiChat extends Component
{
    public $organizations;
    public $selectedOrgId = null;
    public $query = '';
    public $messages = [];
    public $isLoading = false;
    public $conversationContext = [];

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

        try {
            $aiService = new AiAgentService();
            $embedding = $aiService->embed($userMessage);
            if ($embedding && is_array($embedding)) {
                $allContext = [];
                $collectionTypes = ['webpage', 'service', 'product', 'faq', 'document'];
                foreach ($collectionTypes as $type) {
                    $collectionName = "org_{$this->selectedOrgId}_{$type}";
                    $searchResults = $aiService->searchQdrant(
                        $collectionName,
                        $embedding,
                        3
                    );
                    if ($searchResults && isset($searchResults['results']) && !empty($searchResults['results'])) {
                        foreach ($searchResults['results'] as $result) {
                            $payload = $result['payload'];
                            $allContext[] = [
                                'score' => $result['score'],
                                'type' => $type,
                                'content' => $payload
                            ];
                        }
                        if ($type === 'webpage' && count($searchResults['results']) >= 2) {
                            break;
                        }
                    }
                }
                usort($allContext, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                $topContext = array_slice($allContext, 0, 10); // get more for filtering
                $context = '';
                if (!empty($topContext)) {
                    $context = "Relevant information:\n\n";
                    $seen = [];
                    $mobileNumbers = [];
                    $keyword = strtolower($userMessage ?? $this->query ?? '');
                    $keywordParts = preg_split('/\s+/', $keyword);
                    $keywordRegex = implode('|', array_map('preg_quote', array_filter($keywordParts)));
                    $matchedFirst = [];
                    $other = [];
                    foreach ($topContext as $item) {
                        $payload = $item['content'];
                        $payloadText = json_encode($payload);
                        // Extract mobile numbers explicitly
                        if (preg_match_all('/Mobile[\s\-:]*([+]?\d[\d\s\-]{7,})/i', $payloadText, $mobMatches)) {
                            foreach ($mobMatches[1] as $mob) {
                                $mob = trim(str_replace(['-', ' '], '', $mob));
                                if (!in_array($mob, $mobileNumbers)) {
                                    $mobileNumbers[] = $mob;
                                }
                            }
                        }
                        if (isset($payload['title']) && isset($payload['content'])) {
                            $entryKey = md5($payload['title'] . $payload['content']);
                            if (in_array($entryKey, $seen)) {
                                continue;
                            }
                            $seen[] = $entryKey;
                            // Extract only keyword-specific paragraphs
                            $matchedText = '';
                            $matches = [];
                            if (!empty($keywordRegex) && preg_match_all("/(.*?($keywordRegex).*?)(?:\\n|$)/is", $payload['content'], $matches)) {
                                foreach ($matches[1] as $match) {
                                    $matchedText .= trim($match) . "\n";
                                }
                            }
                            if (!empty($matchedText)) {
                                $matchedFirst[] = $payload['title'] . ': ' . $matchedText;
                            } else {
                                $other[] = $payload['title'] . ': ' . (strlen($payload['content']) > 300 ? substr($payload['content'], 0, 300) . '...' : $payload['content']);
                            }
                        }
                    }
                    // Add mobile numbers at the top of context
                    if (!empty($mobileNumbers)) {
                        $context .= "Mobile Number(s): " . implode(', ', $mobileNumbers) . "\n\n";
                    }
                    $finalContext = array_merge($matchedFirst, $other);
                    $deduped = [];
                    foreach ($finalContext as $text) {
                        if (!in_array($text, $deduped)) {
                            $deduped[] = $text;
                        }
                    }
                    foreach (array_slice($deduped, 0, 3) as $text) {
                        $context .= "- " . $text . "\n\n";
                    }
                } else {
                    $context = "No specific information found. Provide general response and suggest contacting the organization.";
                }
                $orgName = Organization::find($this->selectedOrgId)->name ?? 'this organization';
                $conversationMessages = [];
                $systemPrompt = "You are an AI assistant for {$orgName}. Answer ONLY based on the context below. If the user asks for 'your mobile number', 'your contact', or similar, ALWAYS extract and return the organization's mobile number from the context if present. Do NOT say you are an AI or give privacy disclaimers.\n\n{$context}\n\nInstructions: Be helpful, specific, and always provide the organization's mobile number if available. If no relevant context, suggest contacting the organization.";
                $conversationMessages[] = [
                    'role' => 'system',
                    'content' => $systemPrompt
                ];
                foreach ($this->messages as $msg) {
                    $conversationMessages[] = [
                        'role' => $msg['role'],
                        'content' => $msg['content']
                    ];
                }
                $response = $aiService->llmChat($conversationMessages, 'llama3.2:1b');
                if ($response && isset($response['message']['content'])) {
                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => $response['message']['content'],
                        'timestamp' => now()
                    ];
                } else {
                    throw new \Exception('Failed to get AI response');
                }
            } else {
                throw new \Exception('Failed to generate embedding');
            }
        } catch (\Exception $e) {
            $this->messages[] = [
                'role' => 'system',
                'content' => 'Sorry, I encountered an error: ' . $e->getMessage(),
                'timestamp' => now()
            ];
        }
        $this->isLoading = false;
        $this->saveConversationToSession();
    }

    public function clearChat()
    {
        $this->messages = [];
        $this->saveConversationToSession();
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
