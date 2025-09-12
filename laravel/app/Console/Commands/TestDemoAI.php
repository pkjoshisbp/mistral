<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AiAgentService;

class TestDemoAI extends Command
{
    protected $signature = 'demo:test-ai {industry} {question}';
    protected $description = 'Test demo AI responses with real backend';

    public function handle()
    {
        $industry = $this->argument('industry');
        $question = $this->argument('question');
        
        $this->info("Testing demo AI for industry: {$industry}");
        $this->info("Question: {$question}");
        
        try {
            $aiService = app(AiAgentService::class);
            $collectionName = 'demo_' . $industry;
            
            // Check if collection exists
            if (!$aiService->collectionExists($collectionName)) {
                $this->error("Collection {$collectionName} does not exist");
                return 1;
            }
            
            $this->info("✅ Collection {$collectionName} exists");
            
            // Search for relevant context
            $searchResults = $aiService->enhancedSearch($collectionName, $question, 3);
            
            if (empty($searchResults)) {
                $this->warn("No search results found for the question");
                return 1;
            }
            
            $resultsCount = isset($searchResults['results']) ? count($searchResults['results']) : count($searchResults);
            $this->info("✅ Found {$resultsCount} search results");
            
            // Build context from search results
            $context = '';
            $results = $searchResults['results'] ?? $searchResults;
            foreach ($results as $result) {
                $context .= $result['payload']['question'] . "\n";
                $context .= $result['payload']['answer'] . "\n\n";
            }
            
            // Generate AI response
            $systemPrompt = "You are a helpful customer service assistant for a {$industry} business. Use the provided FAQ context to answer customer questions accurately and helpfully. If the question is not covered in the FAQ, provide a general helpful response based on common industry knowledge.";
            
            $aiResponse = $aiService->llmChat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => "FAQ Context:\n{$context}\n\nCustomer Question: {$question}"]
            ]);
            
            if ($aiResponse && isset($aiResponse['message']['content'])) {
                $this->info("✅ AI Response Generated:");
                $this->line("─────────────────────────");
                $this->line($aiResponse['message']['content']);
                $this->line("─────────────────────────");
            } else {
                $this->error("Failed to generate AI response");
                $this->line("Response: " . json_encode($aiResponse));
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("Error testing demo AI: " . $e->getMessage());
            return 1;
        }
    }
}
