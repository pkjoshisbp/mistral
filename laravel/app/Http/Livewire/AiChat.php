<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;

class AiChat extends Component
{
    public $organizations;
    public $selectedOrgId;
    public $query = '';
    public $messages = [];
    public $isLoading = false;

    public function mount()
    {
        $this->organizations = Organization::all();
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
            
            if ($embedding && isset($embedding['embedding'])) {
                // Search for relevant context
                $searchResults = $aiService->searchQdrant(
                    "org_{$this->selectedOrgId}",
                    $embedding['embedding'],
                    3
                );

                // Prepare context from search results
                $context = '';
                if ($searchResults && isset($searchResults['results'])) {
                    foreach ($searchResults['results'] as $result) {
                        $payload = $result['payload'];
                        $context .= "Name: " . ($payload['name'] ?? '') . "\n";
                        $context .= "Description: " . ($payload['description'] ?? '') . "\n";
                        $context .= "Content: " . ($payload['content'] ?? '') . "\n\n";
                    }
                }

                // Create prompt with context
                $prompt = "Context:\n{$context}\n\nUser Question: {$userMessage}\n\nPlease provide a helpful answer based on the context provided.";

                // Get AI response
                $response = $aiService->llmAnswer($prompt);

                if ($response && isset($response['answer'])) {
                    $this->messages[] = [
                        'role' => 'assistant',
                        'content' => $response['answer'],
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
    }

    public function clearChat()
    {
        $this->messages = [];
    }

    public function render()
    {
        return view('livewire.ai-chat');
    }
}
