<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;

class AiChatSimple extends Component
{
    public $messages = [];
    public $newMessage = '';
    public $isLoading = false;
    public $selectedOrgId = 1;
    public $conversationContext = [];

    public function mount()
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
        if (empty(trim($this->newMessage))) return;

        $userMessage = trim($this->newMessage);
        $this->newMessage = '';
        $this->isLoading = true;

        // Add user message
        $this->messages[] = [
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now()
        ];

        $aiService = app(\App\Services\AiAgentService::class);
        $t0 = microtime(true);

        // Keep last 4 messages for context
        $recentMessages = array_slice(
            array_values(array_filter($this->messages, fn($m) => in_array($m['role'], ['user','assistant']))),
            -4
        );

        // ---- SIMPLE QUERY REWRITING ----
        $rewritten = $userMessage;
        if (!empty($recentMessages)) {
            try {
                $conversationText = "";
                foreach ($recentMessages as $msg) {
                    $conversationText .= ($msg['role'] === 'user' ? "User" : "Assistant") . ": " . $msg['content'] . "\n";
                }
                
                $rewriteResp = $aiService->llmChat([
                    ['role' => 'system', 'content' => 'Rewrite the user query to be more specific based on conversation context. Keep it brief.'],
                    ['role' => 'user', 'content' => "Context:\n$conversationText\nQuery: $userMessage\nRewritten:"]
                ], 'llama3.2:1b');
                
                $rewritten = trim($rewriteResp['message']['content'] ?? $userMessage);
                if (empty($rewritten) || strlen($rewritten) < 3) {
                    $rewritten = $userMessage;
                }
            } catch (Exception $e) {
                $rewritten = $userMessage;
            }
        }

        // ---- RETRIEVAL ----
        $context = '';
        try {
            $organization = Organization::find($this->selectedOrgId);
            if (!$organization) {
                throw new \Exception("Organization not found: {$this->selectedOrgId}");
            }
            $collectionName = $organization->collection_name;
            $embedding = $aiService->embed($rewritten, 'nomic-embed-text');
            $searchResults = $aiService->searchQdrant($collectionName, $embedding, 3);
            
            if ($searchResults && isset($searchResults['results'])) {
                $context = "Relevant information:\n\n";
                foreach ($searchResults['results'] as $result) {
                    $payload = $result['payload'] ?? [];
                    if (!empty($payload['description'])) {
                        $context .= "Description: " . $payload['description'] . "\n\n";
                    }
                }
            }
        } catch (Exception $e) {
            \Log::warning('Retrieval failed', ['error' => $e->getMessage()]);
            $context = "Please contact us directly for specific information.";
        }

        // ---- GENERATE RESPONSE ----
        try {
            $orgName = optional(Organization::find($this->selectedOrgId))->name ?? 'our organization';
            $systemPrompt = "You are answering for {$orgName}. Use the provided context to give helpful, accurate responses. Speak as the organization (we/our). Keep responses concise and helpful.";

            $chatMessages = [
                ['role' => 'system', 'content' => $systemPrompt . "\n\n" . $context],
                ['role' => 'user', 'content' => $userMessage]
            ];

            $response = $aiService->llmChat($chatMessages, 'llama3.2:3b');
            $assistantMessage = $response['message']['content'] ?? 'I apologize, but I cannot provide a response right now. Please try again.';

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $assistantMessage,
                'timestamp' => now()
            ];

        } catch (Exception $e) {
            \Log::error('Chat generation failed', ['error' => $e->getMessage()]);
            $this->messages[] = [
                'role' => 'assistant',
                'content' => 'I apologize, but I am experiencing technical difficulties. Please try again later.',
                'timestamp' => now()
            ];
        }

        $this->saveConversationToSession();
        $this->isLoading = false;

        // Log performance
        $totalTime = (microtime(true) - $t0) * 1000;
        \Log::info('Simple AiChat performance', [
            'total_ms' => round($totalTime, 1),
            'query_rewritten' => $rewritten !== $userMessage
        ]);
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
