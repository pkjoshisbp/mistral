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

        try {
            $aiService = new AiAgentService();
            
            // Get embedding for the query
            $embedding = $aiService->embed($userMessage);
            
            // embed() now returns raw embedding array (or null)
            if ($embedding && is_array($embedding)) {
                // Search across multiple collection types for this organization
                $allContext = [];
                
                // Prioritize collections most likely to have data for faster search
                $collectionTypes = ['webpage', 'service', 'product', 'faq', 'document'];
                
                foreach ($collectionTypes as $type) {

                    $collectionName = "org_{$this->selectedOrgId}_{$type}";
                    // Log the query and collection being sent to Qdrant
                    \Log::debug('Qdrant search', [
                        'collection' => $collectionName,
                        'embedding' => $embedding,
                        'query' => $userMessage
                    ]);

                    $searchResults = $aiService->searchQdrant(
                        $collectionName,
                        $embedding,
                        3  // Reduced from 5 to 3 for faster processing
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
                        
                        // If we found good results in webpage collection, prioritize those
                        if ($type === 'webpage' && count($searchResults['results']) >= 2) {
                            break; // Skip other collections if webpage has good results
                        }
                    }
                }

                // Sort by relevance score and take top results
                usort($allContext, function($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                
                $topContext = array_slice($allContext, 0, 3);  // Reduced from 5 to 3 for faster processing

                // ...existing code...
                $context = '';
                if (!empty($topContext)) {
                    $context = "Relevant information:\n\n";
                    foreach ($topContext as $item) {
                        $payload = $item['content'];
                        // Aggregate all string fields in payload
                        foreach ($payload as $key => $value) {
                            if (is_string($value) && !empty(trim($value))) {
                                $context .= ucfirst($key) . ': ';
                                $context .= (strlen($value) > 300 ? substr($value, 0, 300) . '...' : $value);
                                $context .= "\n";
                            }
                        }
                        $context .= "\n";
                    }
                } else {
                    $context = "No specific information found. Provide general response and suggest contacting the organization.";
                }

                // Get organization info
                $orgName = Organization::find($this->selectedOrgId)->name ?? 'this organization';
                
                // Log the final context for debugging
                \Log::debug('AIChat context for LLM', ['context' => $context]);

                // Prepare conversation messages for LLM (use chat instead of single answer)
                $conversationMessages = [];

                // Add system prompt with context - enforce organization voice

                $systemPrompt = "You are an AI assistant answering on behalf of {$orgName}. Always respond as the organization, not as yourself. Never use 'I', 'my', or 'me' in your answers. Use 'we', 'our', or '{$orgName}' in all responses. Answer based only on the context below.

                {$context}

                Instructions: Be helpful and specific. If no relevant context, suggest contacting {$orgName}.";

                $conversationMessages[] = [
                    'role' => 'system',
                    'content' => $systemPrompt
                ];

                // Add conversation history (only last 2 messages to avoid duplicates)
                $recentMessages = array_slice($this->messages, -2);
                foreach ($recentMessages as $msg) {
                    if ($msg['role'] !== 'system') {
                        $conversationMessages[] = [
                            'role' => $msg['role'],
                            'content' => $msg['content']
                        ];
                    }
                }

                // Get AI response using chat (maintains conversation context)
                $response = $aiService->llmChat($conversationMessages);

                // llmChat returns { message: { role: 'assistant', content: '...'} }
                if ($response && isset($response['message']['content'])) {
                    $assistantResponse = $response['message']['content'];
                    
                    // Extract and store entities mentioned for future reference
                    $this->updateConversationContext($userMessage, $assistantResponse, $topContext);
                    
                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => $assistantResponse,
                        'timestamp' => now()
                    ];
                } else {
                    throw new \Exception('Failed to get AI response from the model');
                }
            } else {
                throw new \Exception('Failed to generate embedding for your question');
            }

        } catch (\Exception $e) {
            $this->messages[] = [
                'role' => 'system',
                'content' => 'I apologize, but I encountered a technical issue: ' . $e->getMessage() . '. Please try again or contact support if the problem persists.',
                'timestamp' => now()
            ];
        }

        $this->saveConversationToSession();
        $this->isLoading = false;
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
            $this->conversationContext['recent_services'], -5
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
