<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AiAgentService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_agent.url', 'http://localhost:8111');
    }

    /**
     * Get the configured AI provider (llama or openai)
     */
    public function getAiProvider()
    {
        // Check database settings first, then fall back to config/env
        if (class_exists(\App\Models\AdminSetting::class)) {
            $provider = \App\Models\AdminSetting::get('ai_model_provider');
            if ($provider) {
                return $provider;
            }
        }
        
        return config('app.ai_model_provider', env('AI_MODEL_PROVIDER', 'llama'));
    }

    /**
     * Check if OpenAI is the active provider
     */
    public function isOpenAiProvider()
    {
        return $this->getAiProvider() === 'openai';
    }

    /**
     * Get the configured OpenAI model
     */
    public function getOpenAiModel()
    {
        if (class_exists(\App\Models\AdminSetting::class)) {
            $model = \App\Models\AdminSetting::get('openai_default_model');
            if ($model) {
                return $model;
            }
        }
        
        return config('app.openai_default_model', 'gpt-5-mini');
    }

    /**
     * Get the configured Llama model
     */
    public function getLlamaModel()
    {
        if (class_exists(\App\Models\AdminSetting::class)) {
            $backendType = \App\Models\AdminSetting::get('ai_backend_type', 'ollama');
            
            // For llama.cpp backend, use the configured repo or path
            if ($backendType === 'llamacpp') {
                $llamacppRepo = \App\Models\AdminSetting::get('llamacpp_model_repo');
                $llamacppPath = \App\Models\AdminSetting::get('llamacpp_model_path');
                
                // Prefer custom path if provided, otherwise use repo
                if ($llamacppPath) {
                    return $llamacppPath;
                } elseif ($llamacppRepo) {
                    return $llamacppRepo;
                } else {
                    // Default to 3B model if nothing configured
                    return 'bartowski/Llama-3.2-3B-Instruct-GGUF:Llama-3.2-3B-Instruct-Q4_K_M.gguf';
                }
            }
            
            // For ollama backend, use traditional model setting
            $model = \App\Models\AdminSetting::get('llama_default_model');
            if ($model) {
                return $model;
            }
        }
        
        return config('app.llama_default_model', 'llama3.2:1b');
    }

    /**
     * Get the configured AI backend type
     */
    public function getBackendType()
    {
        if (class_exists(\App\Models\AdminSetting::class)) {
            return \App\Models\AdminSetting::get('ai_backend_type', 'ollama');
        }
        
        return config('app.ai_backend_type', 'ollama');
    }

    /**
     * Get AI provider for specific organization (with fallback to global)
     */
    public function getAiProviderForOrganization($organizationId = null)
    {
        if ($organizationId && class_exists(\App\Models\Organization::class)) {
            $organization = \App\Models\Organization::find($organizationId);
            if ($organization && $organization->settings && isset($organization->settings['ai_model_provider'])) {
                return $organization->settings['ai_model_provider'];
            }
        }
        
        return $this->getAiProvider();
    }

    /**
     * Get OpenAI model for specific organization (with fallback to global)
     */
    public function getOpenAiModelForOrganization($organizationId = null)
    {
        if ($organizationId && class_exists(\App\Models\Organization::class)) {
            $organization = \App\Models\Organization::find($organizationId);
            if ($organization && $organization->settings && isset($organization->settings['openai_model'])) {
                return $organization->settings['openai_model'];
            }
        }
        
        return $this->getOpenAiModel();
    }

    /**
     * Get Llama model for specific organization (with fallback to global)
     */
    public function getLlamaModelForOrganization($organizationId = null)
    {
        if ($organizationId && class_exists(\App\Models\Organization::class)) {
            $organization = \App\Models\Organization::find($organizationId);
            if ($organization && $organization->settings) {
                // Check for organization-specific backend type
                $backendType = $organization->settings['ai_backend_type'] ?? $this->getBackendType();
                
                if ($backendType === 'llamacpp') {
                    // Check organization-specific llamacpp settings
                    $llamacppPath = $organization->settings['llamacpp_model_path'] ?? null;
                    $llamacppRepo = $organization->settings['llamacpp_model_repo'] ?? null;
                    
                    if ($llamacppPath) {
                        return $llamacppPath;
                    } elseif ($llamacppRepo) {
                        return $llamacppRepo;
                    }
                } else {
                    // Check organization-specific llama model
                    if (isset($organization->settings['llama_model'])) {
                        return $organization->settings['llama_model'];
                    }
                }
            }
        }
        
        return $this->getLlamaModel();
    }

    /**
     * Get backend type for specific organization (with fallback to global)
     */
    public function getBackendTypeForOrganization($organizationId = null)
    {
        if ($organizationId && class_exists(\App\Models\Organization::class)) {
            $organization = \App\Models\Organization::find($organizationId);
            if ($organization && $organization->settings && isset($organization->settings['ai_backend_type'])) {
                return $organization->settings['ai_backend_type'];
            }
        }
        
        return $this->getBackendType();
    }

    /**
     * Get AI backend type for specific organization (accepts Organization object)
     */
    public function getAiBackendTypeForOrganization($organization = null)
    {
        if ($organization && $organization->settings && isset($organization->settings['ai_backend_type'])) {
            return $organization->settings['ai_backend_type'];
        }
        
        return $this->getBackendType();
    }

    /**
     * Get AI model provider for specific organization (with fallback to global)
     */
    public function getAiModelProviderForOrganization($organization = null)
    {
        if ($organization && $organization->settings && isset($organization->settings['ai_model_provider'])) {
            return $organization->settings['ai_model_provider'];
        }
        
        return $this->getAiProvider();
    }

    /**
     * Get AI model for specific organization (with fallback to global)
     */
    public function getAiModelForOrganization($organization = null)
    {
        if ($organization && $organization->settings && isset($organization->settings['ai_model'])) {
            return $organization->settings['ai_model'];
        }
        
        return $this->getLlamaModel();
    }

    /**
     * Generate embeddings for given text
     */
    public function embed($text, $model = null)
    {
        try {
            $maxAttempts = 3;
            $attempt = 0;
            $lastError = null;
            // Truncation disabled: send full text for embedding
            // If you experience timeouts or errors, you can re-enable below:
            // if (strlen($text) > 1200) {
            //     $originalLength = strlen($text);
            //     $text = substr($text, 0, 1200);
            //     Log::info('Further truncated text for embedding (service layer)', [
            //         'original_length' => $originalLength,
            //         'truncated_length' => strlen($text)
            //     ]);
            // }

            while ($attempt < $maxAttempts) {
                $attempt++;
                $start = microtime(true);
                // Always use nomic-embed-text for embeddings
                $payload = ['text' => $text, 'model' => 'nomic-embed-text'];
                $response = Http::timeout(30)->post("{$this->baseUrl}/embed", $payload);
                $elapsedMs = (int)((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $data = $response->json();
                    // Truncate logging of the full response (can contain long embedding array)
                    try {
                        $rawJson = json_encode($data);
                        $truncated = substr($rawJson, 0, 100);
                        Log::info('AI Agent embed response', [
                            'truncated' => $truncated,
                            'total_length' => strlen($rawJson),
                            'embedding_dims' => isset($data['embedding']) && is_array($data['embedding']) ? count($data['embedding']) : null,
                            'model' => $data['model'] ?? null
                        ]);
                    } catch (\Throwable $t) {
                        Log::info('AI Agent embed response (fallback log)', [
                            'error' => $t->getMessage(),
                            'has_embedding' => isset($data['embedding']),
                            'model' => $data['model'] ?? null
                        ]);
                    }
                    Log::debug('Embedding generated', [
                        'len' => strlen($text),
                        'elapsed_ms' => $elapsedMs,
                        'attempt' => $attempt,
                        'model' => $data['model'] ?? 'unknown'
                    ]);
                    return $data['embedding'] ?? null;
                }

                $statusCode = $response->status();
                $lastError = $response->body();
                if ($statusCode === 408) {
                    Log::warning('AI Agent embed timeout - Ollama may be overloaded', [
                        'text_length' => strlen($text),
                        'attempt' => $attempt,
                        'elapsed_ms' => $elapsedMs
                    ]);
                    // Exponential backoff + jitter
                    $base = $attempt * 400000; // microseconds
                    $jitter = random_int(50000, 150000); // 50-150ms
                    usleep($base + $jitter);
                    continue;
                } elseif ($statusCode === 503) {
                    Log::warning('AI Agent embed service unavailable', [
                        'response' => $lastError,
                        'attempt' => $attempt
                    ]);
                    $base = $attempt * 300000; // 0.3,0.6,0.9s
                    $jitter = random_int(30000, 120000);
                    usleep($base + $jitter);
                    continue;
                } else {
                    Log::error('AI Agent embed error', [
                        'status' => $statusCode,
                        'response' => $lastError,
                        'attempt' => $attempt
                    ]);
                    break; // Non-retryable
                }
            }
            return null; // All attempts failed
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                Log::warning('AI Agent embed timeout exception - Ollama service may need restart', [
                    'error' => $e->getMessage(),
                    'text_length' => strlen($text)
                ]);
            } else {
                Log::error('AI Agent embed exception', ['error' => $e->getMessage()]);
            }
            return null;
        }
    }

    /**
     * Batch embed multiple texts using backend /embed_batch.
     * Returns array of embeddings (null where failed) in same order.
     */
    public function embedBatch(array $texts)
    {
        if (empty($texts)) return [];
        // Pre-truncate each to align with backend cap (1800) & our earlier 1200 preference
        $prepared = [];
        foreach ($texts as $t) {
            if (!is_string($t)) { $prepared[] = ''; continue; }
            if (strlen($t) > 1200) $t = substr($t, 0, 1200);
            $prepared[] = $t;
        }
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/embed_batch", [
                'texts' => $prepared
            ]);
            if (!$response->successful()) {
                Log::warning('embedBatch failed', ['status' => $response->status(), 'body' => $response->body()]);
                return array_fill(0, count($texts), null);
            }
            $data = $response->json();
            $results = $data['results'] ?? [];
            $embeddings = [];
            foreach ($results as $r) {
                $embeddings[] = $r['embedding'] ?? null;
            }
            Log::debug('Batch embeddings generated', [
                'count' => count($embeddings),
                'model' => $data['model'] ?? 'unknown',
                'total_ms' => $data['total_ms'] ?? null
            ]);
            return $embeddings;
        } catch (\Exception $e) {
            Log::error('embedBatch exception', ['error' => $e->getMessage()]);
            return array_fill(0, count($texts), null);
        }
    }

    /**
     * Create a new collection in Qdrant
     */
    public function createCollection($collectionName, $vectorSize = 768)  // Default for nomic-embed-text
    {
        try {
            $response = Http::timeout(30)->post("{$this->baseUrl}/qdrant/create_collection", [
                'collection_name' => $collectionName,
                'vector_size' => $vectorSize
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('AI Agent create collection exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Add data to Qdrant collection
     */
    public function addToQdrant($collectionName, $vector, $payload, $id = null)
    {
        try {
            $data = [
                'collection_name' => $collectionName,
                'vector' => $vector,
                'payload' => $payload
            ];

            if ($id) {
                $data['id'] = $id;
            }

            $response = Http::timeout(30)->post("{$this->baseUrl}/qdrant/add", $data);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('AI Agent add to qdrant exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Search Qdrant collection
     */
    public function searchQdrant($collectionName, $queryVector, $limit = 5)
    {
        try {
            // Get more results for filtering but respect score thresholds
            $searchLimit = min(max($limit * 2, 15), 25); // Get 2x requested or max 25
            $response = Http::timeout(30)->post("{$this->baseUrl}/qdrant/search", [
                'collection_name' => $collectionName,
                'query_vector' => $queryVector,
                'limit' => $searchLimit
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['results']) && is_array($data['results'])) {
                    // Filter by relevance score first - only keep results above threshold
                    $minScore = 0.4; // Minimum relevance threshold
                    $relevantResults = [];
                    
                    foreach ($data['results'] as $result) {
                        $score = $result['score'] ?? 0;
                        if ($score >= $minScore) {
                            $relevantResults[] = $result;
                        }
                    }
                    
                    // If we have high-quality results (score > 0.7), prefer fewer, better results
                    $hasHighQualityResult = false;
                    foreach ($relevantResults as $result) {
                        if (($result['score'] ?? 0) > 0.7) {
                            $hasHighQualityResult = true;
                            break;
                        }
                    }
                    
                    // Adjust limit based on result quality
                    $effectiveLimit = $hasHighQualityResult ? min($limit, 5) : $limit;
                    
                    // Prioritize service results for service queries, then other results
                    $serviceResults = [];
                    $mriResults = [];
                    $otherResults = [];
                    
                    foreach ($relevantResults as $result) {
                        $payload = $result['payload'] ?? [];
                        $dataType = $payload['data_type'] ?? '';
                        $content = $payload['content'] ?? '';
                        
                        if ($dataType === 'service') {
                            $serviceResults[] = $result;
                        } elseif (stripos($content, 'MRI') !== false || stripos($content, 'magnetic resonance') !== false) {
                            $mriResults[] = $result;
                        } else {
                            $otherResults[] = $result;
                        }
                    }
                    
                    // Merge prioritized results and trim to effective limit
                    $finalResults = array_merge($serviceResults, $mriResults, $otherResults);
                    $data['results'] = array_slice($finalResults, 0, $effectiveLimit);
                }
                Log::info('AI Agent Qdrant search response', ['response' => $data]);
                return $data;
            } else {
                // Log the error but don't throw exception - collection might not exist
                Log::info("Qdrant search failed for collection {$collectionName}", [
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('AI Agent search qdrant exception', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Enhanced search with optional query rewriting 
     */
    public function enhancedSearch($collectionName, $originalQuery, $limit = 5)
    {
        try {
            Log::info('Enhanced search started', [
                'collection' => $collectionName,
                'original_query' => $originalQuery
            ]);

            // TEMPORARILY DISABLE QUERY REWRITE - use original query directly
            // This fixes the issue where query rewrite was producing conversational responses
            // that confused the search and final AI response
            $queryToUse = $originalQuery;
            
            Log::info('Query rewrite skipped (disabled)', [
                'original_query' => $originalQuery,
                'query_used' => $queryToUse
            ]);

            // Generate embedding for the original query
            $embedding = $this->embed($queryToUse);

            if (!$embedding || !is_array($embedding)) {
                Log::warning('Failed to generate embedding for enhanced search', [
                    'query' => $queryToUse
                ]);
                return null;
            }

            // Search Qdrant with the embedding
            $searchResults = $this->searchQdrant($collectionName, $embedding, $limit);
            
            Log::info('Enhanced search completed', [
                'collection' => $collectionName,
                'original_query' => $originalQuery,
                'query_used' => $queryToUse,
                'results_count' => isset($searchResults['results']) ? count($searchResults['results']) : 0
            ]);

            return $searchResults;

        } catch (\Exception $e) {
            Log::error('Enhanced search failed', [
                'collection' => $collectionName,
                'original_query' => $originalQuery,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Enhanced search with action processing - combines static KB with live data
     */
    public function enhancedSearchWithActions($collectionName, $originalQuery, $organizationId, $limit = 5)
    {
        try {
            Log::info('Enhanced search with actions started', [
                'collection' => $collectionName,
                'organization_id' => $organizationId,
                'original_query' => $originalQuery
            ]);

            // Create ActionService instance
            $intentDetector = app(IntentDetectionService::class);
            $executor = app(ActionExecutorService::class);
            $actionService = new ActionService($this, $intentDetector, $executor);

            // Process query through action system
            $actionResult = $actionService->processQuery($originalQuery, $organizationId);
            
            // Also get knowledge base results
            $kbResults = $this->enhancedSearch($collectionName, $originalQuery, $limit);
            
            // Combine results based on action processing outcome
            if ($actionResult['type'] === 'action_executed' && $actionResult['result']['success']) {
                // Action executed successfully - prioritize live data
                $liveDataFormatted = $actionService->formatLiveDataForAI(
                    $actionResult['result']['data'],
                    $actionResult['action']
                );
                
                Log::info('Action executed successfully with live data', [
                    'action_name' => $actionResult['action']['name'],
                    'live_data_length' => strlen($liveDataFormatted),
                    'kb_results_count' => isset($kbResults['results']) ? count($kbResults['results']) : 0
                ]);

                return [
                    'type' => 'hybrid',
                    'action_result' => $actionResult,
                    'live_data' => $liveDataFormatted,
                    'kb_results' => $kbResults,
                    'primary_source' => 'live_data'
                ];
                
            } elseif ($actionResult['type'] === 'action_executed' && !$actionResult['result']['success']) {
                // Action failed - use KB as fallback
                Log::warning('Action execution failed, using KB fallback', [
                    'action_error' => $actionResult['result']['error'] ?? 'Unknown error',
                    'kb_results_count' => isset($kbResults['results']) ? count($kbResults['results']) : 0
                ]);

                return [
                    'type' => 'fallback_to_kb',
                    'action_result' => $actionResult,
                    'kb_results' => $kbResults,
                    'primary_source' => 'knowledge_base',
                    'fallback_reason' => $actionResult['result']['error'] ?? 'Action execution failed'
                ];
                
            } else {
                // No action needed or no matching actions - use KB only
                Log::info('Using knowledge base only', [
                    'action_type' => $actionResult['type'],
                    'kb_results_count' => isset($kbResults['results']) ? count($kbResults['results']) : 0
                ]);

                return [
                    'type' => 'knowledge_base_only',
                    'action_result' => $actionResult,
                    'kb_results' => $kbResults,
                    'primary_source' => 'knowledge_base'
                ];
            }

        } catch (\Exception $e) {
            Log::error('Enhanced search with actions failed', [
                'collection' => $collectionName,
                'organization_id' => $organizationId,
                'original_query' => $originalQuery,
                'error' => $e->getMessage()
            ]);

            // Fallback to regular KB search
            $kbResults = $this->enhancedSearch($collectionName, $originalQuery, $limit);
            
            return [
                'type' => 'error_fallback_to_kb',
                'kb_results' => $kbResults,
                'primary_source' => 'knowledge_base',
                'error' => 'Action processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Rewrite user query using Llama-3.2
     */
    public function rewriteQueryForSearch($originalQuery)
    {
        try {
            // Use the regular LLM for query rewriting instead of the GGUF model
            $systemPrompt = "You are a query rewriter for database search. Convert the user's question into search keywords ONLY. Extract just the main topic/subject they're asking about.

EXAMPLES:
- 'What pricing plans do you offer?' → 'pricing plans'
- 'Do you have any refund policy?' → 'refund policy'  
- 'How much does it cost?' → 'cost pricing'
- 'What are your business hours?' → 'business hours'
- 'Can you provide WhatsApp integration?' → 'WhatsApp integration'

Output ONLY the search keywords, no sentences, no explanations, no conversational text.";
            
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $originalQuery]
            ];
            
            $response = $this->llmChat($messages, 'llama3.2:1b');
            
            if ($response && isset($response['message']['content'])) {
                $rewrittenQuery = trim($response['message']['content']);
                
                // Validation: If the rewritten query is too long or conversational, use original
                if (strlen($rewrittenQuery) > 100 || 
                    str_word_count($rewrittenQuery) > 10 ||
                    stripos($rewrittenQuery, 'I offer') !== false ||
                    stripos($rewrittenQuery, 'you can') !== false ||
                    stripos($rewrittenQuery, 'we provide') !== false) {
                    
                    Log::warning('Query rewrite produced conversational text, using original', [
                        'original' => $originalQuery,
                        'rewritten' => $rewrittenQuery
                    ]);
                    return $originalQuery;
                }
                
                Log::info('Query rewrite successful', [
                    'original' => $originalQuery,
                    'rewritten' => $rewrittenQuery
                ]);
                return $rewrittenQuery;
            } else {
                Log::warning('Query rewrite failed, using original query', [
                    'original' => $originalQuery,
                    'response' => $response
                ]);
                return $originalQuery;
            }
        } catch (\Exception $e) {
            Log::warning('Query rewrite exception, using original query', [
                'original' => $originalQuery,
                'error' => $e->getMessage()
            ]);
            return $originalQuery;
        }
    }

    /**
     * Get LLM answer
     */
    public function llmAnswer($prompt, $model = null)
    {
        try {
            // Use configured model if none provided
            if (!$model) {
                $model = $this->getLlamaModel();
            }

            $response = Http::timeout(30)->post("{$this->baseUrl}/llm/answer", [
                'prompt' => $prompt,
                'model' => $model
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('AI Agent LLM answer exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * LLM chat with conversation context
     */
    public function llmChat($messages, $model = null, $userId = null, $organizationId = null)  // Default determined dynamically
    {
        try {
            // Use configured model if none provided
            if (!$model) {
                $model = $this->getLlamaModel();
            }

            $payload = [
                'messages' => $messages,
                'model' => $model,
                'backend_type' => $this->getBackendType()
            ];
            
            // Truncate logged payload to keep logs lean
            $payloadPreview = substr(json_encode($payload), 0, 100);
            Log::info('AI Agent LLM chat request', [
                'url' => "{$this->baseUrl}/llm/chat",
                'payload_preview' => $payloadPreview,
                'payload_length' => strlen(json_encode($payload)),
                'timeout' => 30,
                'model' => $payload['model'],
                'backend_type' => $payload['backend_type']
            ]);

            $response = Http::timeout(30)->post("{$this->baseUrl}/llm/chat", $payload);

            $body = $response->body();
            $responseData = $response->successful() ? $response->json() : null;
            $tokensUsed = isset($responseData['usage']['total_tokens']) ? $responseData['usage']['total_tokens'] : 'unknown';
            
            Log::info('AI Agent LLM chat response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_length' => strlen($body),
                'body_preview' => substr($body, 0, 100),
                'model' => $payload['model'],
                'backend_type' => $payload['backend_type'],
                'tokens_used' => $tokensUsed
            ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Log token usage if organization is provided
                if ($organizationId) {
                    $tokensUsed = 0;
                    
                    // Try to get tokens from response first
                    if (isset($result['usage']['total_tokens'])) {
                        $tokensUsed = $result['usage']['total_tokens'];
                    } else {
                        // Estimate tokens if not provided by FastAPI
                        $inputText = json_encode($messages);
                        $outputText = isset($result['message']['content']) ? $result['message']['content'] : '';
                        $tokensUsed = (int)((strlen($inputText) + strlen($outputText)) / 4); // Rough estimate: 4 chars per token
                    }
                    
                    $this->logTokenUsage(
                        $userId,
                        $organizationId,
                        'llm_chat',
                        $tokensUsed,
                        substr($payloadPreview, 0, 255)
                    );
                }
                
                return $result;
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error('AI Agent LLM chat exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * OpenAI chat completion
     */
    public function openAiChat($messages, $model = null, $userId = null, $organizationId = null)
    {
        try {
            // Use configured model if none provided
            if (!$model) {
                $model = $this->getOpenAiModel();
            }

            Log::info('OpenAI chat request', [
                'model' => $model,
                'messages_count' => count($messages),
                'user_id' => $userId,
                'organization_id' => $organizationId
            ]);

            // Get API key from admin settings or fallback to config
            $apiKey = null;
            if (class_exists(\App\Models\AdminSetting::class)) {
                $apiKey = \App\Models\AdminSetting::get('openai_api_key');
            }
            
            if (!$apiKey) {
                $apiKey = config('services.openai.api_key');
            }
            
            // Let GPT-5-mini use its default token allocation - no artificial limits
            // The model will decide how to allocate reasoning vs output tokens
            
            Log::info('OpenAI chat request (no token limits)', [
                'model' => $model,
                'messages_count' => count($messages)
            ]);
            
            $client = OpenAI::client($apiKey);
            
            $result = $client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'max_completion_tokens' => 300, // Limit response length to keep it concise
            ]);

            Log::info('OpenAI chat response', [
                'id' => $result->id,
                'model' => $result->model,
                'usage' => $result->usage->toArray()
            ]);

            // Debug: Log the raw choices structure and full result
            Log::info('OpenAI choices debug', [
                'choices_count' => count($result->choices),
                'first_choice' => isset($result->choices[0]) ? [
                    'message_role' => $result->choices[0]->message->role ?? 'null',
                    'message_content' => $result->choices[0]->message->content ?? 'null',
                    'content_length' => strlen($result->choices[0]->message->content ?? ''),
                    'finish_reason' => $result->choices[0]->finishReason ?? 'null',
                ] : 'no_choices',
                'raw_result_keys' => array_keys($result->toArray()),
                'choices_structure' => $result->choices[0]->toArray()
            ]);

            // Extract content from the response
            $content = $result->choices[0]->message->content ?? '';
            
            // Handle empty content from reasoning models - use the search context intelligently
            if (empty($content)) {
                // Log that we're using fallback
                Log::info('OpenAI returned empty content, using intelligent fallback');
                
                // For now, return null to let the caller handle it with context
                // This allows the original search results to be used as fallback
                return null;
            }

            // Convert OpenAI response format to match our existing format
            $response = [
                'message' => [
                    'role' => $result->choices[0]->message->role,
                    'content' => $content,
                ],
                'usage' => [
                    'prompt_tokens' => $result->usage->promptTokens,
                    'completion_tokens' => $result->usage->completionTokens,
                    'total_tokens' => $result->usage->totalTokens,
                ]
            ];

            // Log token usage if user and organization are provided
            if ($userId && $organizationId) {
                $this->logTokenUsage(
                    $userId,
                    $organizationId,
                    'openai_chat',
                    $response['usage']['total_tokens'],
                    "OpenAI {$model} chat"
                );
            }

            // Debug: Log the final response structure
            Log::info('OpenAI final response', [
                'has_message' => isset($response['message']),
                'message_content_length' => strlen($response['message']['content'] ?? ''),
                'content_preview' => substr($response['message']['content'] ?? '', 0, 100),
                'usage_tokens' => $response['usage']['total_tokens'] ?? 0
            ]);

            return $response;

        } catch (\Exception $e) {
            Log::error('OpenAI chat exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Estimate token count for messages (rough approximation)
     */
    public function estimateTokenCount($messages)
    {
        $totalText = '';
        foreach ($messages as $message) {
            $totalText .= $message['content'] ?? '';
        }
        
        // Rough estimation: 1 token ≈ 0.75 words or 4 characters
        $wordCount = str_word_count($totalText);
        $charCount = strlen($totalText);
        
        // Use the higher estimate for safety
        $tokensFromWords = $wordCount / 0.75;
        $tokensFromChars = $charCount / 4;
        
        return max($tokensFromWords, $tokensFromChars);
    }
    
    /**
     * Calculate dynamic token limit based on context size
     * For reasoning models like GPT-5-mini, we need extra tokens for internal reasoning
     */
    public function calculateDynamicTokenLimit($inputTokens)
    {
        // For reasoning models, we need to account for both reasoning tokens and output tokens
        // GPT-5-mini uses reasoning tokens internally, then generates output
        
        if ($inputTokens < 50) {
            // Short context: 200 reasoning + 200 output = 400 total
            return 400;
        } elseif ($inputTokens < 100) {
            // Medium context: 250 reasoning + 250 output = 500 total
            return 500;
        } elseif ($inputTokens < 200) {
            // Longer context: 300 reasoning + 300 output = 600 total
            return 600;
        } elseif ($inputTokens < 400) {
            // Complex context: 400 reasoning + 400 output = 800 total
            return 800;
        } else {
            // Very complex context: 500 reasoning + 500 output = 1000 total
            return 1000;
        }
    }

    /**
     * Smart LLM chat that routes to the appropriate provider
     */
    public function smartLlmChat($messages, $model = null, $userId = null, $organizationId = null)
    {
        if ($this->isOpenAiProvider()) {
            // Always use GPT-5-mini as it's the only allowed model
            $openAiModel = 'gpt-5-mini';
            return $this->openAiChat($messages, $openAiModel, $userId, $organizationId);
        } else {
            $llamaModel = $model ?: $this->getLlamaModel();
            return $this->llmChat($messages, $llamaModel, $userId, $organizationId);
        }
    }

    /**
     * Check if collection exists
     */
    public function collectionExists($collectionName)
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/qdrant/collections/{$collectionName}");
            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Collection exists check exception', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get collection info including count
     */
    public function getCollectionInfo($collectionName)
    {
        try {
            $response = Http::timeout(30)->get("{$this->baseUrl}/qdrant/collections/{$collectionName}/info");
            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('Get collection info exception', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Store data in Qdrant for a specific organization
     */
    public function storeData($organizationId, $type, $title, $content, $metadata = [])
    {
        try {
            // Create collection name based on organization
            $collectionName = "org_{$organizationId}_{$type}";
            
            // Ensure collection exists
            $this->createCollection($collectionName);
            
            // Prepare data for embedding - limit length to prevent timeouts
            $textForEmbedding = $title . ' ' . $content;
            if (strlen($textForEmbedding) > 2500) {
                $textForEmbedding = substr($textForEmbedding, 0, 2500);
                Log::info("Truncated text for embedding to prevent timeout", [
                    'original_length' => strlen($title . ' ' . $content),
                    'truncated_length' => strlen($textForEmbedding)
                ]);
            }
            
            // Generate embedding
            $embedding = $this->embed($textForEmbedding);
            
            if (!$embedding || !is_array($embedding)) {
                Log::warning('Failed to generate embedding for storeData - skipping vector storage but continuing', [
                    'org_id' => $organizationId,
                    'type' => $type,
                    'title' => $title
                ]);
                // Return true but log the failure - crawler should continue
                return true;
            }
            
            // Prepare payload
            $payload = array_merge([
                'org_id' => $organizationId,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'created_at' => now()->toISOString()
            ], $metadata);
            
            // Store in Qdrant
            $result = $this->addToQdrant(
                $collectionName,
                $embedding,  // Use embedding directly since embed() already returns the array
                $payload
            );
            
            if (!$result) {
                Log::warning('Failed to store in Qdrant but continuing crawl', [
                    'collection' => $collectionName,
                    'title' => $title
                ]);
            }
            
            return true; // Always return true to continue crawling
            
        } catch (\Exception $e) {
            Log::error('Store data exception', ['error' => $e->getMessage()]);
            return true; // Continue crawling even if storage fails
        }
    }

    /**
     * Sync data from MySQL to Qdrant
     */
    public function syncToQdrant($organizationId, $data)
    {
        $collectionName = "org_{$organizationId}";
        
        // Create collection if it doesn't exist
        $this->createCollection($collectionName);

        $results = [];
        foreach ($data as $item) {
            // Generate embedding for the item
            $text = $this->prepareTextForEmbedding($item);
            $embedding = $this->embed($text);

            if ($embedding && is_array($embedding)) {
                $result = $this->addToQdrant(
                    $collectionName,
                    $embedding,
                    $item,
                    $item['id'] ?? null
                );
                $results[] = $result;
            } else {
                Log::warning('Sync embed failed - item skipped', [
                    'org_id' => $organizationId,
                    'item_id' => $item['id'] ?? null
                ]);
            }
        }

        return $results;
    }

    /**
     * Prepare text for embedding generation
     */
    private function prepareTextForEmbedding($item)
    {
        $text = '';
        
        // Concatenate relevant fields
        if (isset($item['name'])) $text .= $item['name'] . ' ';
        if (isset($item['description'])) $text .= $item['description'] . ' ';
        if (isset($item['content'])) $text .= $item['content'] . ' ';
        if (isset($item['category'])) $text .= $item['category'] . ' ';
        
        return trim($text);
    }

    /**
     * Lightweight intent detection using the LLM (categorical, JSON output).
     */
    public function detectIntent(string $utterance, array $context = [], string $model = 'llama3.2:1b')
    {
        $sys = "You are an intent classifier. Classify the user's utterance into one of: 
        [general_qna, booking, pricing, timing, directions, contact_info, troubleshooting, other].
        Return STRICT JSON with keys: intent (string), confidence (0-1). Do not add any text outside JSON.";
        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => json_encode(['utterance' => $utterance, 'context' => $context])]
        ];
        $resp = $this->llmChat($messages, $model);
        if (!$resp || empty($resp['message']['content'])) return ['intent' => 'general_qna', 'confidence' => 0.4];
        $txt = trim($resp['message']['content']);
        $parsed = json_decode($txt, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($parsed)) {
            return ['intent' => 'general_qna', 'confidence' => 0.4, 'raw' => $txt];
        }
        return $parsed;
    }

    /**
     * Slot extraction (few general-purpose slots) using the LLM. Returns ['slots'=>[], 'missing'=>[]].
     */
    public function extractSlots(string $utterance, array $existingSlots = [], string $model = 'llama3.2:1b')
    {
        $schema = [
            'organization', 'service', 'date', 'time', 'location', 'person', 'quantity',
            'price', 'email', 'phone'
        ];
        $sys = "Extract slots from the utterance. Allowed slots: " . implode(',', $schema) . ". 
        Merge with existing slots (prefer new if confident). Infer only if explicit or unambiguous.
        Return STRICT JSON: {\"slots\": {slot: value, ...}} with ISO date/time if present.";
        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => json_encode(['utterance'=>$utterance, 'existing'=>$existingSlots])]
        ];
        $resp = $this->llmChat($messages, $model);
        $out = ['slots' => $existingSlots];
        if ($resp && !empty($resp['message']['content'])) {
            $txt = trim($resp['message']['content']);
            $parsed = json_decode($txt, true);
            if (json_last_error() === JSON_ERROR_NONE && isset($parsed['slots']) && is_array($parsed['slots'])) {
                $out['slots'] = array_filter(array_merge($existingSlots, $parsed['slots']), function($v){ return $v !== null && $v !== ''; });
            }
        }
        return $out;
    }

    /**
     * Rewriter: rewrite the user query to be explicit & self-contained using memory, slots and recent context.
     */
    public function rewriteQuery(string $utterance, array $recentMessages = [], array $slots = [], array $memory = [], string $model = 'llama3.2:1b')
    {
        $sys = "Rewrite the user's message into a single explicit, context-complete query for retrieval.
        Keep original meaning. Include relevant slot values inline (date/time normalized). 
        Output plain text only.";
        $messages = [
            ['role' => 'system', 'content' => $sys],
            ['role' => 'user', 'content' => json_encode([
                'utterance'=>$utterance,
                'recent_messages'=>array_slice($recentMessages, -8),
                'slots'=>$slots,
                'memory'=>$memory
            ])]
        ];
        $resp = $this->llmChat($messages, $model);
        if (!$resp || empty($resp['message']['content'])) return $utterance;
        return trim($resp['message']['content']);
    }

    /**
     * Store organization data to Qdrant via FastAPI
     */
    public function storeDataToQdrant($organizationSlug, $dataType, $items)
    {
        try {
            // Filter out items with empty content to avoid overwriting good data with blanks
            $filtered = [];
            foreach ($items as $it) {
                $content = isset($it['content']) ? trim((string) $it['content']) : '';
                if ($content === '') {
                    Log::warning('Skipping item with empty content for Qdrant store', [
                        'organization_slug' => $organizationSlug,
                        'data_type' => $dataType,
                        'item_id' => $it['id'] ?? null,
                        'title' => $it['title'] ?? null
                    ]);
                    continue;
                }
                $filtered[] = $it;
            }

            if (empty($filtered)) {
                Log::warning('No valid items to store to Qdrant after filtering empties', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType
                ]);
                return [
                    'success' => false,
                    'successful_stores' => 0,
                    'failed_stores' => 0,
                    'failures' => ['all_items_empty']
                ];
            }

            $payload = [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'items' => $filtered
            ];

            Log::info('Storing data to Qdrant', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'item_count' => count($items)
            ]);

            $response = Http::timeout(120)->post("{$this->baseUrl}/store_data", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Successfully stored data to Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'successful_stores' => $data['successful_stores'] ?? 0,
                    'failed_stores' => $data['failed_stores'] ?? 0
                ]);
                return $data;
            } else {
                Log::error('Failed to store data to Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Store data to Qdrant exception', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Delete organization data from Qdrant via FastAPI
     */
    public function deleteDataFromQdrant($organizationSlug, $itemIds)
    {
        try {
            $payload = [
                'organization_slug' => $organizationSlug,
                'item_ids' => is_array($itemIds) ? $itemIds : [$itemIds]
            ];

            Log::info('Deleting data from Qdrant', [
                'organization_slug' => $organizationSlug,
                'item_ids' => $payload['item_ids']
            ]);

            $response = Http::timeout(60)->post("{$this->baseUrl}/delete_data", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Successfully deleted data from Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'deleted_count' => $data['deleted_count'] ?? 0,
                    'failed_deletes' => $data['failed_deletes'] ?? []
                ]);
                return $data;
            } else {
                Log::error('Failed to delete data from Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Delete data from Qdrant exception', [
                'organization_slug' => $organizationSlug,
                'item_ids' => $itemIds,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Update existing data in Qdrant (wrapper for storeDataToQdrant with update semantics)
     */
    public function updateDataToQdrant($organizationSlug, $dataType, $items)
    {
        try {
            Log::info('Updating data to Qdrant', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'item_count' => count($items)
            ]);

            $payload = [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'items' => $items
            ];

            $response = Http::timeout(60)->post("{$this->baseUrl}/update_data", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Successfully updated data in Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'successful_stores' => $data['successful_stores'] ?? 0,
                    'failed_stores' => $data['failed_stores'] ?? 0
                ]);
                return $data;
            } else {
                Log::error('Failed to update data in Qdrant', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'status' => $response->status(),
                    'error' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Update data to Qdrant exception', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'items' => $items,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Log token usage for billing and monitoring purposes
     */
    private function logTokenUsage($userId, $organizationId, $endpointType, $tokensUsed, $requestSummary)
    {
        try {
            $subscription = null;
            
            // Handle widget usage (no user ID) - assign to organization's first user
            if (!$userId && $organizationId) {
                $organization = \App\Models\Organization::find($organizationId);
                if ($organization) {
                    // Get the first user associated with this organization
                    $firstUser = $organization->users()->first();
                    if ($firstUser) {
                        $userId = $firstUser->id;
                        $subscription = $firstUser->activeSubscription;
                    }
                }
            } else if ($userId) {
                // Get user's active subscription
                $user = \App\Models\User::find($userId);
                $subscription = $user ? $user->activeSubscription : null;
            }
            
            // Still log even if no user found (for monitoring)
            \App\Models\TokenUsageLog::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'subscription_id' => $subscription ? $subscription->id : null,
                'endpoint_type' => $endpointType,
                'tokens_used' => $tokensUsed,
                'request_summary' => $requestSummary,
                'used_at' => now()
            ]);
            
            // Update subscription token usage if subscription exists
            if ($subscription) {
                $subscription->increment('tokens_used_this_period', $tokensUsed);
                
                Log::info('Subscription token usage updated', [
                    'subscription_id' => $subscription->id,
                    'tokens_added' => $tokensUsed,
                    'new_total' => $subscription->fresh()->tokens_used_this_period,
                    'plan_limit' => $subscription->subscriptionPlan->token_cap_monthly ?? 'unlimited'
                ]);
            } elseif ($userId) {
                // No active subscription: deduct from credits balance
                try {
                    $userCredit = \App\Models\UserCredit::getOrCreateForUser($userId);
                    $deducted = $userCredit->deductCredits($tokensUsed, 'AI usage: ' . $endpointType, [
                        'metadata' => [
                            'organization_id' => $organizationId,
                            'endpoint_type' => $endpointType,
                            'tokens_used' => $tokensUsed,
                        ]
                    ]);
                    if (!$deducted) {
                        Log::warning('Insufficient credits for usage', [
                            'user_id' => $userId,
                            'required' => $tokensUsed,
                            'balance' => $userCredit->balance
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Credit deduction failed', [
                        'user_id' => $userId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            Log::info('Token usage logged', [
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'tokens_used' => $tokensUsed,
                'endpoint_type' => $endpointType
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log token usage', [
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'tokens_used' => $tokensUsed,
                'error' => $e->getMessage()
            ]);
        }
    }

}
