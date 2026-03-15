<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OpenAI;

class AiAgentService
{
    private $baseUrl;

    /** Debug data captured during the most recent enhancedSearch() call. */
    public array $lastSearchDebug = [];

    public function getLastSearchDebug(): array
    {
        return $this->lastSearchDebug;
    }

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
     * Get model dedicated for query rewriting.
     */
    public function getRewriteModel(): string
    {
        if (class_exists(\App\Models\AdminSetting::class)) {
            $configured = trim((string) \App\Models\AdminSetting::get('ai_rewrite_model', ''));
            if ($configured !== '') {
                return $configured;
            }
        }

        return 'mistral-nemo';
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
            if ($organization && $organization->settings) {
                if (isset($organization->settings['openai_model'])) {
                    return $organization->settings['openai_model'];
                }
                if (isset($organization->settings['ai_model'])) {
                    return $organization->settings['ai_model'];
                }
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
                $provider = $organization->settings['ai_model_provider'] ?? $this->getAiProvider();
                if ($provider === 'openai') {
                    return $this->getLlamaModel();
                }

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
                    // Check organization-specific llama model.
                    // Prefer 'ai_model' (the current admin-panel setting) over the legacy
                    // 'llama_model' key which may hold a stale model name from older deployments.
                    if (isset($organization->settings['ai_model'])) {
                        return $organization->settings['ai_model'];
                    }
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
     * Toggle: enable intent classification + query rewrite for retrieval/action gating.
     */
    public function useIntentAndRewrite(): bool
    {
        // Pre-Qdrant LLM rewrite adds 2+ LLM calls (rewriteQueryForSearch + selectBestAnswerWithLLM)
        // on every single query, including simple first-turn messages. The vector
        // embeddings from nomic-embed-text handle semantic similarity well enough
        // (job ≈ vacancy ≈ hiring) without a pre-rewrite step. Post-Qdrant expansion
        // (expandQueryForLowConfidence) is the correct place to add an LLM fallback
        // only when confidence is actually low. Always return false here.
        return false;
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
     * Delete a collection from Qdrant
     */
    public function deleteCollection($collectionName)
    {
        try {
            $response = Http::timeout(30)->delete("{$this->baseUrl}/qdrant/delete_collection", [
                'collection_name' => $collectionName
            ]);

            if ($response->successful()) {
                Log::info('Qdrant collection deleted successfully', [
                    'collection' => $collectionName
                ]);
                return $response->json();
            } else {
                Log::warning('Failed to delete Qdrant collection', [
                    'collection' => $collectionName,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                return null;
            }
        } catch (\Exception $e) {
            Log::error('AI Agent delete collection exception', [
                'collection' => $collectionName,
                'error' => $e->getMessage()
            ]);
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

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('AI Agent add to qdrant failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'collection' => $collectionName
            ]);

            return null;
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

                    usort($relevantResults, function ($a, $b) {
                        return ((float) ($b['score'] ?? 0.0)) <=> ((float) ($a['score'] ?? 0.0));
                    });

                    $data['results'] = array_slice($relevantResults, 0, max(1, (int) $limit));
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
     * Use LLM to select the most relevant answer between two options
     * 
     * @param string $userQuery The original user query
     * @param array $resultA First result (from original query)
     * @param array $resultB Second result (from rewritten query)
     * @return string 'original' or 'rewritten'
     */
    private function selectBestAnswerWithLLM($userQuery, $resultA, $resultB)
    {
        $titleA = $resultA['payload']['title'] ?? 'N/A';
        $contentA = $resultA['payload']['content'] ?? 'N/A';
        $scoreA = $resultA['score'] ?? 0;
        
        $titleB = $resultB['payload']['title'] ?? 'N/A';
        $contentB = $resultB['payload']['content'] ?? 'N/A';
        $scoreB = $resultB['score'] ?? 0;
        
        // Truncate content to reduce token count (keep first 200 chars)
        $contentA = mb_substr(strip_tags($contentA), 0, 200);
        $contentB = mb_substr(strip_tags($contentB), 0, 200);
        
        $prompt = <<<PROMPT
User's Question: "{$userQuery}"

A: {$titleA}
B: {$titleB}

Which title better matches the question? Reply only: A or B
PROMPT;

        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a relevance judge. Select the answer that best matches the user\'s question.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ];
        
        $response = $this->llmChat($messages, 'llama3.2:3b', 'ollama', [
            'temperature' => 0.1,
            'num_predict' => 10
        ]);
        
        $choice = trim(strtoupper($response['message']['content'] ?? ''));
        
        Log::info('LLM answer selection', [
            'user_query' => $userQuery,
            'option_a' => $titleA,
            'option_b' => $titleB,
            'llm_choice' => $choice
        ]);
        
        // Return 'original' for A, 'rewritten' for B
        return (strpos($choice, 'A') !== false) ? 'original' : 'rewritten';
    }

    /**
     * Enhanced search with optional query rewriting 
     */
    public function enhancedSearch($collectionName, $originalQuery, $limit = 5, array $options = [])
    {
        $searchStartTime = microtime(true);
        try {
            Log::info('Enhanced search started', [
                'collection' => $collectionName,
                'original_query' => $originalQuery
            ]);

            $useRewriteAndIntent = $this->useIntentAndRewrite();
            $disableRewrite = (bool) ($options['disable_rewrite'] ?? false);
            if ($disableRewrite && $useRewriteAndIntent) {
                $useRewriteAndIntent = false;
                Log::info('Bypassing query rewrite via enhancedSearch options', [
                    'collection' => $collectionName,
                    'original_query' => $originalQuery,
                ]);
            }
            $entityFocusedQuery = $this->isEntityFocusedRetrievalQuery((string) $originalQuery);
            if ($entityFocusedQuery && $useRewriteAndIntent) {
                $useRewriteAndIntent = false;
                Log::info('Bypassing query rewrite for entity-focused retrieval query', [
                    'collection' => $collectionName,
                    'original_query' => $originalQuery,
                ]);
            }

            $termBoostResults = $this->searchQdrantByTerms($collectionName, (string) $originalQuery, max((int) $limit, 8));

            if (!$useRewriteAndIntent) {
                $embedding = $this->embed($originalQuery);

                if (!$embedding || !is_array($embedding)) {
                    Log::warning('Failed to generate embedding for semantic search', [
                        'query' => $originalQuery
                    ]);
                    return null;
                }

                $searchLimit = max((int) $limit, 15);
                $results = $this->searchQdrant($collectionName, $embedding, $searchLimit);
                $mergedResults = $this->mergeSearchResultsById([
                    $termBoostResults['results'] ?? [],
                    $results['results'] ?? [],
                ]);
                $rerankedResults = $this->applyHybridLexicalReranking($mergedResults, (string) $originalQuery);
                $results = [
                    'results' => array_slice($rerankedResults, 0, (int) $limit),
                ];

                // ── Low-confidence expansion fallback ──────────────────────────
                // If best score < 0.72, the original query may be ambiguous or
                // use abbreviations/domain jargon the embedding model doesn't
                // recognise well (e.g. "TGT" = Trained Graduate Teacher).
                // Expand the query via LLM and retry; keep whichever pass wins.
                $firstPassMaxScore = 0.0;
                foreach ($rerankedResults as $_r) {
                    $firstPassMaxScore = max($firstPassMaxScore, (float) ($_r['semantic_score'] ?? $_r['score'] ?? 0));
                }

                $expansionThreshold = (float) ($options['expansion_threshold'] ?? 0.72);
                $skipExpansion      = (bool)  ($options['skip_expansion']      ?? false);

                // Also skip when any result already has a strong hybrid/keyword score (>1.5).
                // A high hybrid score means keyword coverage is good — LLM expansion
                // adds latency (~7s) with no retrieval benefit.
                if (!$skipExpansion) {
                    foreach ($rerankedResults as $_hr) {
                        if ((float) ($_hr['score'] ?? $_hr['hybrid_score'] ?? 0) > 1.5) {
                            $skipExpansion = true;
                            break;
                        }
                    }
                }

                if (!$skipExpansion && $firstPassMaxScore < $expansionThreshold) {
                    Log::info('Low-confidence result, attempting query expansion', [
                        'collection'         => $collectionName,
                        'original_query'     => $originalQuery,
                        'first_pass_score'   => round($firstPassMaxScore, 4),
                        'expansion_threshold' => $expansionThreshold,
                    ]);

                    $expandedQuery = $this->expandQueryForLowConfidence($originalQuery, $firstPassMaxScore);

                    if ($expandedQuery !== $originalQuery) {
                        $expandedEmbedding = $this->embed($expandedQuery);

                        if ($expandedEmbedding && is_array($expandedEmbedding)) {
                            $expandedResults   = $this->searchQdrant($collectionName, $expandedEmbedding, $searchLimit);
                            $expandedTermBoost = $this->searchQdrantByTerms($collectionName, $expandedQuery, max((int) $limit, 8));

                            $expandedMerged  = $this->mergeSearchResultsById([
                                $expandedTermBoost['results'] ?? [],
                                $expandedResults['results']   ?? [],
                            ]);
                            $expandedReranked = $this->applyHybridLexicalReranking($expandedMerged, $expandedQuery);

                            $expandedMaxScore = 0.0;
                            foreach ($expandedReranked as $_r) {
                                $expandedMaxScore = max($expandedMaxScore, (float) ($_r['semantic_score'] ?? $_r['score'] ?? 0));
                            }

                            Log::info('Query expansion completed', [
                                'collection'           => $collectionName,
                                'original_query'       => $originalQuery,
                                'expanded_query'       => $expandedQuery,
                                'first_pass_max_score' => round($firstPassMaxScore, 4),
                                'expanded_max_score'   => round($expandedMaxScore, 4),
                                'used_expansion'       => $expandedMaxScore > $firstPassMaxScore,
                            ]);

                            if ($expandedMaxScore > $firstPassMaxScore) {
                                $results = [
                                    'results'        => array_slice($expandedReranked, 0, (int) $limit),
                                    'expanded_query' => $expandedQuery,
                                    'expansion_score_gain' => round($expandedMaxScore - $firstPassMaxScore, 4),
                                ];
                            }
                        }
                    }
                }

                $searchElapsed = round((microtime(true) - $searchStartTime) * 1000, 2);

                Log::info('Semantic search completed (rewrite/intent disabled)', [
                    'collection'        => $collectionName,
                    'original_query'    => $originalQuery,
                    'first_pass_score'  => round($firstPassMaxScore, 4),
                    'expansion_attempted' => (!$skipExpansion && $firstPassMaxScore < $expansionThreshold),
                    'results_count'     => isset($results['results']) ? count($results['results']) : 0,
                    'total_elapsed_ms'  => $searchElapsed,
                ]);

                // Surface debug data for WidgetController
                $this->lastSearchDebug = [
                    'expansion_attempted'  => (!$skipExpansion && $firstPassMaxScore < $expansionThreshold),
                    'expanded_query'       => $results['expanded_query'] ?? null,
                    'expansion_score_gain' => $results['expansion_score_gain'] ?? null,
                    'first_pass_score'     => round($firstPassMaxScore, 4),
                    'total_elapsed_ms'     => $searchElapsed,
                ];

                return $results;
            }

            // Use query rewrite to improve keyword matching, with safeguards
            $rewrittenQuery = $this->rewriteQueryForSearch($originalQuery);

            if ($rewrittenQuery === $originalQuery) {
                Log::info('Query rewrite skipped (no change)', [
                    'original_query' => $originalQuery,
                    'query_used' => $rewrittenQuery
                ]);
            } else {
                Log::info('Query rewrite applied', [
                    'original_query' => $originalQuery,
                    'query_used' => $rewrittenQuery
                ]);
            }

            // Generate embeddings for both original and rewritten queries
            $originalEmbedding = $this->embed($originalQuery);
            $rewrittenEmbedding = $rewrittenQuery !== $originalQuery ? $this->embed($rewrittenQuery) : null;

            if (!$originalEmbedding || !is_array($originalEmbedding)) {
                Log::warning('Failed to generate embedding for enhanced search', [
                    'query' => $originalQuery
                ]);
                return null;
            }

            // Search Qdrant with original query embedding
            $originalResults = $this->searchQdrant($collectionName, $originalEmbedding, $limit);

            // Search Qdrant with rewritten query embedding (if available)
            $rewrittenResults = null;
            if ($rewrittenEmbedding && is_array($rewrittenEmbedding)) {
                $rewrittenResults = $this->searchQdrant($collectionName, $rewrittenEmbedding, $limit);
            }

            $searchElapsed = round((microtime(true) - $searchStartTime) * 1000, 2);
            Log::info('Enhanced search completed', [
                'collection' => $collectionName,
                'original_query' => $originalQuery,
                'rewritten_query' => $rewrittenQuery,
                'original_results_count' => isset($originalResults['results']) ? count($originalResults['results']) : 0,
                'rewritten_results_count' => isset($rewrittenResults['results']) ? count($rewrittenResults['results']) : 0,
                'total_elapsed_ms' => $searchElapsed
            ]);

            // Let LLM decide which result is more relevant to the user's original query
            // Get top result from each query type
            $topOriginal = isset($originalResults['results'][0]) ? $originalResults['results'][0] : null;
            $topRewritten = isset($rewrittenResults['results'][0]) ? $rewrittenResults['results'][0] : null;
            
            // If we have different top results, let LLM choose the best one
            $selectedResults = [];
            if ($topOriginal && $topRewritten && 
                ($topOriginal['id'] ?? null) !== ($topRewritten['id'] ?? null)) {
                
                try {
                    $llmChoice = $this->selectBestAnswerWithLLM($originalQuery, $topOriginal, $topRewritten);
                    
                    if ($llmChoice === 'original') {
                        // Use original query results
                        $selectedResults = $originalResults['results'] ?? [];
                        Log::info('LLM selected original query results as more relevant', [
                            'original_title' => $topOriginal['payload']['title'] ?? 'N/A',
                            'rewritten_title' => $topRewritten['payload']['title'] ?? 'N/A'
                        ]);
                    } else {
                        // Use rewritten query results
                        $selectedResults = $rewrittenResults['results'] ?? [];
                        Log::info('LLM selected rewritten query results as more relevant', [
                            'original_title' => $topOriginal['payload']['title'] ?? 'N/A',
                            'rewritten_title' => $topRewritten['payload']['title'] ?? 'N/A'
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('LLM answer selection failed, using score-based merge', [
                        'error' => $e->getMessage()
                    ]);
                    // Fallback to score-based merge
                    $selectedResults = [];
                }
            }
            
            // If LLM selection was used, return those results
            if (!empty($selectedResults)) {
                Log::info('Enhanced search completed with LLM selection', [
                    'collection' => $collectionName,
                    'original_query' => $originalQuery,
                    'query_used' => $rewrittenQuery,
                    'results_count' => count($selectedResults)
                ]);
                
                return [
                    'results' => array_slice($selectedResults, 0, $limit)
                ];
            }
            
            // Otherwise, merge results by best score per unique id (fallback)
            $mergedResults = $this->mergeSearchResultsById([
                $termBoostResults['results'] ?? [],
                $originalResults['results'] ?? [],
                $rewrittenResults['results'] ?? [],
            ]);

            usort($mergedResults, function ($a, $b) {
                return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
            });

            $mergedResults = $this->applyHybridLexicalReranking($mergedResults, (string) $originalQuery);

            $topMerged = array_slice($mergedResults, 0, 3);
            $topMergedSummary = array_map(function ($item) {
                return [
                    'id' => $item['id'] ?? null,
                    'score' => $item['score'] ?? null,
                    'data_type' => $item['payload']['data_type'] ?? null,
                    'item_id' => $item['payload']['item_id'] ?? null,
                    'title' => $item['payload']['title'] ?? null
                ];
            }, $topMerged);

            Log::info('Enhanced search merged top results', [
                'collection' => $collectionName,
                'original_query' => $originalQuery,
                'rewritten_query' => $rewrittenQuery,
                'top_results' => $topMergedSummary
            ]);

            $searchResults = [
                'results' => array_slice($mergedResults, 0, $limit)
            ];
            
            Log::info('Enhanced search completed', [
                'collection' => $collectionName,
                'original_query' => $originalQuery,
                'query_used' => $rewrittenQuery,
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

    private function applyExplicitTermReranking(array $results, string $query): array
    {
        if (empty($results)) {
            return $results;
        }

        $sortedByScore = $results;
        usort($sortedByScore, function (array $a, array $b): int {
            return ((float) ($b['score'] ?? 0.0)) <=> ((float) ($a['score'] ?? 0.0));
        });

        $terms = $this->extractExplicitSearchTerms($query);
        if (empty($terms)) {
            return $sortedByScore;
        }

        $reranked = [];
        foreach ($sortedByScore as $item) {
            if (!is_array($item)) {
                continue;
            }

            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $title = $this->normalizeSearchText((string) ($payload['title'] ?? ''));
            $content = $this->normalizeSearchText((string) ($payload['content'] ?? ''));

            $boost = 0.0;
            foreach ($terms as $term) {
                $termNormalized = $this->normalizeSearchText($term);
                if ($termNormalized === '') {
                    continue;
                }

                if ($title !== '' && $title === $termNormalized) {
                    $boost += 1.20;
                } elseif ($title !== '' && str_contains($title, $termNormalized)) {
                    $boost += 0.65;
                } elseif ($content !== '' && str_contains($content, $termNormalized)) {
                    $boost += 0.25;
                }
            }

            $item['_boosted_score'] = ((float) ($item['score'] ?? 0.0)) + $boost;
            $reranked[] = $item;
        }

        usort($reranked, function (array $a, array $b): int {
            $scoreA = (float) ($a['_boosted_score'] ?? $a['score'] ?? 0.0);
            $scoreB = (float) ($b['_boosted_score'] ?? $b['score'] ?? 0.0);
            if ($scoreA === $scoreB) {
                return ((float) ($b['score'] ?? 0.0)) <=> ((float) ($a['score'] ?? 0.0));
            }

            return $scoreB <=> $scoreA;
        });

        foreach ($reranked as &$item) {
            unset($item['_boosted_score']);
        }

        Log::info('Applied explicit-term reranking for search results', [
            'terms' => $terms,
            'top_titles' => array_values(array_filter(array_map(function ($item) {
                return $item['payload']['title'] ?? null;
            }, array_slice($reranked, 0, 3))))
        ]);

        return $reranked;
    }

    private function applyHybridLexicalReranking(array $results, string $query): array
    {
        if (empty($results)) {
            return $results;
        }

        $queryNorm = $this->normalizeSearchText($query);
        $isPricingLikeIntent = (bool) preg_match('/\b(plan|plans|pricing|price|cost|subscription|subscriptions|package|packages|tier|tiers|billing)\b/i', $queryNorm);
        $isEnterpriseLikeIntent = (bool) preg_match('/\b(corporate|enterprise|business|company|team|organization|organisation)\b/i', $queryNorm);
        $isDemoLikeIntent = (bool) preg_match('/\b(demo|trial|sample|example|test)\b/i', $queryNorm);

        $queryTerms = $this->extractLexicalTermsForRerank($query);
        if (empty($queryTerms)) {
            return $this->applyExplicitTermReranking($results, $query);
        }

        $queryEntityTerms = $this->extractQueryEntityTermsForRerank($queryTerms, $queryNorm);
        $expandedEntityTerms = $this->expandEntityAliasesForRerank($queryEntityTerms);
        $hasStrongEntityIntent = !empty($queryEntityTerms);

        $rescored = [];
        foreach ($results as $item) {
            if (!is_array($item)) {
                continue;
            }

            $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];
            $titleNorm = $this->normalizeSearchText((string) ($payload['title'] ?? $payload['question'] ?? ''));
            $contentNorm = $this->normalizeSearchText((string) ($payload['content'] ?? $payload['answer'] ?? ''));
            $entityNorm = $this->normalizeSearchText($this->extractPayloadEntityTextForRerank($payload));
            $keywordsNorm = $this->normalizeSearchText($this->extractPayloadKeywordsForRerank($payload));
            $auxNorm = $this->normalizeSearchText(implode(' ', array_filter([
                (string) ($payload['category'] ?? ''),
                (string) ($payload['plan_type'] ?? ''),
                (string) ($payload['billing_period'] ?? ''),
                (string) ($payload['type'] ?? ''),
                (string) data_get($payload, 'metadata.plan_type', ''),
                (string) data_get($payload, 'metadata.billing_period', ''),
                (string) data_get($payload, 'metadata.csv.plan_type', ''),
                (string) data_get($payload, 'metadata.csv.billing_period', ''),
            ])));

            $matched = 0;
            $entityMatches = 0;
            $keywordMatches = 0;
            $titleMatches = 0;
            $contentMatches = 0;
            $auxMatches = 0;
            $lexicalBoost = 0.0;

            foreach ($queryTerms as $term) {
                if ($term === '') {
                    continue;
                }

                $inEntity = $entityNorm !== '' && str_contains($entityNorm, $term);
                $inKeywords = $keywordsNorm !== '' && str_contains($keywordsNorm, $term);
                $inTitle = $titleNorm !== '' && str_contains($titleNorm, $term);
                $inContent = $contentNorm !== '' && str_contains($contentNorm, $term);
                $inAux = $auxNorm !== '' && str_contains($auxNorm, $term);

                if ($inEntity || $inKeywords || $inTitle || $inContent || $inAux) {
                    $matched++;
                }

                if ($inEntity) {
                    $lexicalBoost += 0.52;
                    $entityMatches++;
                }

                if ($inKeywords) {
                    $lexicalBoost += 0.52;
                    $keywordMatches++;
                }

                if ($inTitle) {
                    $lexicalBoost += 0.38;
                    $titleMatches++;
                }

                if ($inContent) {
                    $lexicalBoost += 0.16;
                    $contentMatches++;
                }

                if ($inAux) {
                    $lexicalBoost += 0.14;
                    $auxMatches++;
                }
            }

            $queryEntityHitCount = 0;
            foreach ($expandedEntityTerms as $entityTerm) {
                if ($entityTerm === '') {
                    continue;
                }

                $inEntity = $entityNorm !== '' && str_contains($entityNorm, $entityTerm);
                $inKeywords = $keywordsNorm !== '' && str_contains($keywordsNorm, $entityTerm);
                $inTitle = $titleNorm !== '' && str_contains($titleNorm, $entityTerm);
                $inAux = $auxNorm !== '' && str_contains($auxNorm, $entityTerm);
                if ($inEntity || $inKeywords || $inTitle || $inAux) {
                    $queryEntityHitCount++;
                }
            }
            $lexicalBoost += (0.38 * $queryEntityHitCount);

            $coverage = $matched > 0 ? ($matched / max(1, count($queryTerms))) : 0.0;
            $lexicalBoost += (0.55 * $coverage);

            $entityCoverage = !empty($expandedEntityTerms)
                ? ($queryEntityHitCount / max(1, count($expandedEntityTerms)))
                : 0.0;
            $lexicalBoost += (0.62 * $entityCoverage);
            if ($hasStrongEntityIntent) {
                if ($queryEntityHitCount > 0) {
                    $lexicalBoost += 0.48;
                } else {
                    $lexicalBoost -= 0.72;
                }
            }

            $anchorHits = $entityMatches + $keywordMatches + $titleMatches + $auxMatches;
            if (count($queryTerms) >= 2 && $anchorHits === 0 && $contentMatches > 0) {
                $lexicalBoost -= 0.35;
            }
            if ($coverage < 0.34) {
                $lexicalBoost -= 0.18;
            }

            $combinedNorm = trim($entityNorm . ' ' . $titleNorm . ' ' . $keywordsNorm . ' ' . $auxNorm . ' ' . $contentNorm);
            if ($isEnterpriseLikeIntent) {
                if ((bool) preg_match('/\b(corporate|enterprise|business|team)\b/i', $combinedNorm)) {
                    $lexicalBoost += 0.92;
                } else {
                    $lexicalBoost -= 1.05;
                }
                if (!$isDemoLikeIntent && (bool) preg_match('/\b(demo|trial|sample)\b/i', $combinedNorm)) {
                    $lexicalBoost -= 0.55;
                }
            }

            if ($isPricingLikeIntent) {
                if ((bool) preg_match('/\b(subscription|monthly|yearly|recurring|plan|pricing|billing)\b/i', $combinedNorm)) {
                    $lexicalBoost += 0.25;
                }
            }

            $baseScore = (float) ($item['score'] ?? 0.0);
            $hybridScore = $baseScore + $lexicalBoost;

            $item['score'] = $hybridScore;
            $item['hybrid_score'] = $hybridScore;
            $item['semantic_score'] = $baseScore;
            $item['lexical_match'] = [
                'coverage' => round($coverage, 4),
                'entity_coverage' => round($entityCoverage, 4),
                'matched_terms' => $matched,
                'entity_hits' => $entityMatches,
                'entity_query_hits' => $queryEntityHitCount,
                'keyword_hits' => $keywordMatches,
                'title_hits' => $titleMatches,
                'content_hits' => $contentMatches,
                'aux_hits' => $auxMatches,
            ];

            $rescored[] = $item;
        }

        usort($rescored, function (array $a, array $b): int {
            return ((float) ($b['hybrid_score'] ?? $b['score'] ?? 0.0)) <=> ((float) ($a['hybrid_score'] ?? $a['score'] ?? 0.0));
        });

        return $rescored;
    }

    private function extractLexicalTermsForRerank(string $query): array
    {
        $normalized = $this->normalizeSearchText($query);
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $normalized) ?: [];
        $stopWords = [
            'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'do', 'for', 'from', 'how', 'i', 'if',
            'in', 'is', 'it', 'me', 'my', 'of', 'on', 'or', 'our', 'please', 'show', 'tell', 'the',
            'to', 'us', 'we', 'what', 'when', 'where', 'which', 'who', 'with', 'you', 'your',
            'have', 'has', 'had', 'any', 'there', 'about', 'can', 'could', 'would', 'should'
        ];

        $terms = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '' || mb_strlen($part) < 2) {
                continue;
            }

            if (in_array($part, $stopWords, true)) {
                continue;
            }

            $terms[$part] = $part;
        }

        return array_values($terms);
    }

    private function extractPayloadKeywordsForRerank(array $payload): string
    {
        $candidates = [
            $payload['keywords'] ?? null,
            $payload['search_keywords'] ?? null,
            $payload['semantic_text'] ?? null,
            $payload['semantic_terms'] ?? null,
            data_get($payload, 'metadata.keywords'),
            data_get($payload, 'metadata.search_keywords'),
            data_get($payload, 'metadata.semantic_text'),
            data_get($payload, 'metadata.semantic_terms'),
            data_get($payload, 'metadata.csv.keywords'),
            data_get($payload, 'metadata.csv.search_keywords'),
            data_get($payload, 'metadata.csv.semantic_text'),
            data_get($payload, 'metadata.csv.semantic_terms'),
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                $candidate = implode(' ', array_map('strval', $candidate));
            }

            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return '';
    }

    private function extractPayloadEntityTextForRerank(array $payload): string
    {
        $candidates = [
            $payload['entity'] ?? null,
            $payload['primary_entity'] ?? null,
            $payload['entities'] ?? null,
            data_get($payload, 'metadata.entity'),
            data_get($payload, 'metadata.primary_entity'),
            data_get($payload, 'metadata.entities'),
            data_get($payload, 'metadata.csv.entity'),
            data_get($payload, 'metadata.csv.primary_entity'),
            data_get($payload, 'metadata.csv.entities'),
            data_get($payload, 'metadata.product_name'),
            $payload['category'] ?? null,
        ];

        $parts = [];
        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                foreach ($candidate as $value) {
                    if (is_scalar($value)) {
                        $value = trim((string) $value);
                        if ($value !== '') {
                            $parts[] = $value;
                        }
                    }
                }
            } elseif (is_scalar($candidate)) {
                $value = trim((string) $candidate);
                if ($value !== '') {
                    $parts[] = $value;
                }
            }
        }

        if (empty($parts)) {
            return '';
        }

        return implode(' ', array_values(array_unique($parts)));
    }

    private function extractQueryEntityTermsForRerank(array $queryTerms, string $queryNorm): array
    {
        $nonEntityTerms = [
            'price', 'pricing', 'cost', 'plan', 'plans', 'subscription', 'subscriptions',
            'package', 'packages', 'tier', 'tiers', 'billing', 'monthly', 'yearly', 'details',
            'detail', 'compare', 'comparison', 'feature', 'features', 'benefits', 'info', 'information'
        ];

        $entityTerms = [];
        foreach ($queryTerms as $term) {
            if ($term === '' || in_array($term, $nonEntityTerms, true)) {
                continue;
            }
            $entityTerms[$term] = $term;
        }

        if (empty($entityTerms)) {
            foreach ($queryTerms as $term) {
                if ($term !== '') {
                    $entityTerms[$term] = $term;
                }
            }
        }

        if ((bool) preg_match('/\b(corporate|enterprise|business)\b/i', $queryNorm)) {
            $entityTerms['corporate'] = 'corporate';
            $entityTerms['enterprise'] = 'enterprise';
            $entityTerms['business'] = 'business';
        }

        return array_values($entityTerms);
    }

    private function expandEntityAliasesForRerank(array $entityTerms): array
    {
        $aliases = [
            'corporate' => ['corporate', 'enterprise', 'business', 'company', 'team'],
            'enterprise' => ['enterprise', 'corporate', 'business', 'company', 'team'],
            'business' => ['business', 'enterprise', 'corporate', 'team'],
            'clinic' => ['clinic', 'diagnostic', 'diagnostics', 'lab', 'laboratory'],
            'plan' => ['plan', 'plans', 'package', 'packages', 'subscription'],
        ];

        $expanded = [];
        foreach ($entityTerms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }

            $expanded[$term] = $term;
            if (isset($aliases[$term])) {
                foreach ($aliases[$term] as $alias) {
                    $alias = trim((string) $alias);
                    if ($alias !== '') {
                        $expanded[$alias] = $alias;
                    }
                }
            }
        }

        return array_values($expanded);
    }

    private function mergeSearchResultsById(array $resultGroups): array
    {
        $mergedResults = [];
        $resultsIndex = [];

        foreach ($resultGroups as $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $itemId = $item['id'] ?? null;
                if ($itemId === null) {
                    continue;
                }

                if (!isset($resultsIndex[$itemId]) || ((float) ($item['score'] ?? 0.0)) > ((float) ($mergedResults[$resultsIndex[$itemId]]['score'] ?? 0.0))) {
                    if (isset($resultsIndex[$itemId])) {
                        $mergedResults[$resultsIndex[$itemId]] = $item;
                    } else {
                        $resultsIndex[$itemId] = count($mergedResults);
                        $mergedResults[] = $item;
                    }
                }
            }
        }

        return $mergedResults;
    }

    private function searchQdrantByTerms(string $collectionName, string $query, int $limit = 10): array
    {
        $terms = $this->extractExplicitSearchTerms($query);
        if (empty($terms)) {
            return ['results' => []];
        }

        try {
            $response = Http::timeout(20)->post("{$this->baseUrl}/qdrant/search_by_terms", [
                'collection_name' => $collectionName,
                'terms' => $terms,
                'limit' => max(3, min($limit, 20)),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'results' => is_array($data['results'] ?? null) ? $data['results'] : [],
                ];
            }

            Log::warning('Qdrant term search failed', [
                'collection' => $collectionName,
                'status' => $response->status(),
                'body_preview' => substr((string) $response->body(), 0, 200),
            ]);
        } catch (\Exception $e) {
            Log::warning('Qdrant term search exception', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
        }

        return ['results' => []];
    }

    private function extractExplicitSearchTerms(string $query): array
    {
        $terms = [];
        $trimmed = trim($query);
        if ($trimmed === '') {
            return [];
        }

        if (preg_match_all('/"([^"\n]{2,100})"/', $trimmed, $matches)) {
            foreach (($matches[1] ?? []) as $value) {
                $candidate = trim((string) $value);
                if ($candidate !== '') {
                    $terms[] = $candidate;
                }
            }
        }

        if (preg_match_all('/\b(?:titled|called|named)\s+([a-z0-9][a-z0-9\s\-]{2,120})/i', $trimmed, $matches)) {
            foreach (($matches[1] ?? []) as $value) {
                $candidate = trim((string) $value, " \t\n\r\0\x0B.,;:!?\"'");
                if ($candidate !== '') {
                    $terms[] = $candidate;
                }
            }
        }

        if (preg_match_all('/https?:\/\/[^\s]+/i', $trimmed, $matches)) {
            foreach (($matches[0] ?? []) as $rawUrl) {
                $url = rtrim((string) $rawUrl, ".,;:!?)]}");
                $path = (string) (parse_url($url, PHP_URL_PATH) ?? '');
                $slug = trim((string) basename($path), '/');
                if ($slug !== '') {
                    $slugPhrase = trim(preg_replace('/[-_]+/', ' ', $slug) ?? $slug);
                    if ($slugPhrase !== '') {
                        $terms[] = $slugPhrase;
                    }
                }
            }
        }

        if ($this->isEntityFocusedRetrievalQuery($trimmed)) {
            foreach ($this->extractImplicitEntityTermsForSearch($trimmed) as $term) {
                $terms[] = $term;
            }
        }

        $deduped = [];
        foreach ($terms as $term) {
            $normalized = $this->normalizeSearchText((string) $term);
            if ($normalized === '' || mb_strlen($normalized) < 3) {
                continue;
            }
            $deduped[$normalized] = trim((string) $term);
        }

        return array_values($deduped);
    }

    private function isEntityFocusedRetrievalQuery(string $query): bool
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return false;
        }

        if (str_contains($q, '"')) {
            return true;
        }

        return (bool) preg_match(
            '/\b(available|availability|in\s+stock|out\s+of\s+stock|stock|price|pricing|cost|quote|quoted|customi[sz]e|customi[sz]ation|deliver|delivery|eta|sku|item|product|service|test)\b/i',
            $q
        );
    }

    private function extractImplicitEntityTermsForSearch(string $query): array
    {
        $normalized = strtolower((string) preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $query));
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return [];
        }

        $tokens = array_values(array_filter(explode(' ', $normalized), function ($token) {
            return $token !== '' && mb_strlen($token) >= 2;
        }));

        if (count($tokens) < 2) {
            return [];
        }

        $stopwords = [
            'is', 'are', 'was', 'were', 'be', 'been', 'being', 'a', 'an', 'the', 'for', 'to', 'of', 'in', 'on', 'at',
            'and', 'or', 'if', 'yes', 'no', 'can', 'could', 'would', 'should', 'will', 'do', 'does', 'did', 'right',
            'now', 'today', 'tomorrow', 'please', 'with', 'without', 'about', 'any', 'what', 'when', 'where', 'which',
            'who', 'whom', 'this', 'that', 'these', 'those', 'my', 'your', 'our', 'their', 'it', 'its', 'as', 'by',
            'from', 'into', 'even', 'not', 'have', 'has', 'had', 'we', 'you', 'they', 'i', 'me', 'us', 'mean',
            'available', 'availability', 'store', 'shop', 'service', 'item', 'product', 'painting', 'customized',
            'customised', 'cost', 'price', 'pricing', 'delivery', 'deliver', 'stock', 'quote', 'quoted'
        ];

        $phrases = [];
        $maxN = min(4, count($tokens));
        for ($n = $maxN; $n >= 2; $n--) {
            for ($i = 0; $i <= count($tokens) - $n; $i++) {
                $slice = array_slice($tokens, $i, $n);
                $meaningful = array_values(array_filter($slice, function ($token) use ($stopwords) {
                    return !in_array($token, $stopwords, true) && mb_strlen($token) >= 3;
                }));

                if (count($meaningful) < 2) {
                    continue;
                }

                $phrase = trim(implode(' ', $meaningful));
                if ($phrase === '' || mb_strlen($phrase) < 6) {
                    continue;
                }

                $phrases[$phrase] = true;
            }
        }

        return array_slice(array_keys($phrases), 0, 6);
    }

    private function normalizeSearchText(string $value): string
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/https?:\/\/[^\s]+/i', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^a-z0-9]+/i', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
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
        $startTime = microtime(true);
        try {
            $systemPrompt = "Rewrite user query for semantic retrieval.
Rules:
- Keep original intent.
- Preserve exact entity names if present.
- Remove filler words.
- Output a single short retrieval query (max 12 words).
- Do not invent any facts, dates, or prices.
- Return ONLY the rewritten query text.";
            
            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $originalQuery]
            ];
            
            $rewriteModel = $this->getRewriteModel();
            $response = $this->llmChat($messages, $rewriteModel, null, null, [
                'use_vastai' => true,
                'temperature' => 0.0,
                'num_predict' => 48,
            ]);
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response && isset($response['message']['content'])) {
                $rawOutput = trim((string) $response['message']['content']);
                $rewrittenQuery = $this->normalizeRewriteOutput($rawOutput, (string) $originalQuery);
                
                Log::info('Query rewrite timing', [
                    'elapsed_ms' => $elapsed,
                    'original' => $originalQuery,
                    'rewritten' => $rewrittenQuery,
                    'rewrite_model' => $rewriteModel,
                ]);
                
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
                    'rewritten' => $rewrittenQuery,
                    'rewrite_model' => $rewriteModel,
                ]);
                return $rewrittenQuery;
            } else {
                Log::warning('Query rewrite failed, using original query', [
                    'original' => $originalQuery,
                    'response' => $response,
                    'rewrite_model' => $rewriteModel,
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
     * Expand a low-confidence query using LLM (llama3.2:3b via vast.ai).
     *
     * Used as a second-pass retrieval when the first Qdrant search returns a
     * poor semantic score.  The model is asked to unfold abbreviations, add
     * domain-specific synonyms, and produce a richer retrieval phrase.
     *
     * Examples:
     *   "Apply for tgt hindi sanskrit"  →  "TGT teacher vacancy job opening Hindi Sanskrit teaching position recruitment"
     *   "cbc test price"                →  "complete blood count CBC test cost price charges"
     *
     * @param  string $originalQuery
     * @param  float  $bestScore      Score of the best first-pass result (for logging)
     * @return string  Expanded query, or original if expansion fails / adds nothing
     */
    private function expandQueryForLowConfidence(string $originalQuery, float $bestScore): string
    {
        try {
            $systemPrompt = <<<'PROMPT'
You are a search query expander for a knowledge-base retrieval system.
Given a user query, rewrite it into a richer retrieval phrase by:
- Spelling out abbreviations (e.g. TGT → Trained Graduate Teacher, CBC → Complete Blood Count)
- Adding domain-specific synonyms (e.g. "apply" → "apply vacancy job opening recruitment")
- Keeping all original keywords
- Removing filler words (for, the, a, an)
- Output a SINGLE short phrase (max 15 words). No lists, no explanation.
PROMPT;

            $messages = [
                ['role' => 'system', 'content' => trim($systemPrompt)],
                ['role' => 'user',   'content' => $originalQuery],
            ];

            // Use a small fast model for expansion — it is a simple synonym/phrase task.
            // Avoid using mistral-nemo or other large models here as they add 7+ seconds.
            $response = $this->llmChat($messages, 'llama3.2:3b', null, null, [
                'use_vastai'   => true,
                'temperature'  => 0.0,
                'num_predict'  => 60,
            ]);

            if ($response && isset($response['message']['content'])) {
                $expanded = trim((string) $response['message']['content']);
                // Strip leading/trailing quotes if model added them
                $expanded = trim($expanded, '"\' ');
                // Reject if too long or conversational
                if ($expanded === '' ||
                    strlen($expanded) > 200 ||
                    str_word_count($expanded) > 18 ||
                    stripos($expanded, 'I ') === 0 ||
                    stripos($expanded, 'Here') === 0 ||
                    stripos($expanded, 'This') === 0) {
                    return $originalQuery;
                }
                // Reject if the expansion is identical to original (case-insensitive)
                if (strtolower($expanded) === strtolower($originalQuery)) {
                    return $originalQuery;
                }
                Log::info('Query expansion LLM result', [
                    'original'    => $originalQuery,
                    'expanded'    => $expanded,
                    'first_score' => round($bestScore, 4),
                ]);
                return $expanded;
            }
        } catch (\Exception $e) {
            Log::warning('Query expansion failed, using original', [
                'original' => $originalQuery,
                'error'    => $e->getMessage(),
            ]);
        }
        return $originalQuery;
    }

    /**
     * Rewrite follow-up query using explicit conversational context:
     * original user question + assistant answer + current follow-up.
     */
    public function rewriteFollowUpQueryWithContext(string $originalQuestion, string $assistantAnswer, string $followUpQuestion): string
    {
        $startTime = microtime(true);

        $originalQuestion = trim($originalQuestion);
        $assistantAnswer = trim($assistantAnswer);
        $followUpQuestion = trim($followUpQuestion);

        if ($followUpQuestion === '') {
            return '';
        }

        $assistantAnswerForPrompt = mb_substr($assistantAnswer, 0, 700);

        try {
            $systemPrompt = "You rewrite ONLY the current follow-up user message into a single retrieval query.
Use conversation context to resolve references like this/that/it.
Rules:
- Keep exact entity names when present.
- Use original question + assistant answer only as context.
- Output one concise retrieval query (max 16 words).
- No explanations, no labels, no markdown.";

            $userPrompt = "Original question: {$originalQuestion}\n"
                . "Assistant answer: {$assistantAnswerForPrompt}\n"
                . "Current follow-up question: {$followUpQuestion}\n"
                . "Return rewritten retrieval query only.";

            $rewriteModel = $this->getRewriteModel();

            Log::info('Follow-up rewrite input context', [
                'original_question' => $originalQuestion,
                'assistant_answer_preview' => mb_substr($assistantAnswerForPrompt, 0, 220),
                'follow_up_question' => $followUpQuestion,
                'rewrite_model' => $rewriteModel,
            ]);

            $response = $this->llmChat([
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ], $rewriteModel, null, null, [
                'use_vastai' => true,
                'temperature' => 0.0,
                'num_predict' => 64,
            ]);

            $elapsed = round((microtime(true) - $startTime) * 1000, 2);

            if ($response && isset($response['message']['content'])) {
                $rawOutput = trim((string) $response['message']['content']);
                $fallback = trim($originalQuestion . ' ' . $followUpQuestion);
                $rewrittenQuery = $this->normalizeRewriteOutput($rawOutput, $fallback !== '' ? $fallback : $followUpQuestion);

                Log::info('Follow-up rewrite output', [
                    'elapsed_ms' => $elapsed,
                    'follow_up_question' => $followUpQuestion,
                    'rewritten_query' => $rewrittenQuery,
                    'rewrite_model' => $rewriteModel,
                ]);

                if (strlen($rewrittenQuery) > 140 || str_word_count($rewrittenQuery) > 20) {
                    Log::warning('Follow-up rewrite too long; using compact fallback', [
                        'follow_up_question' => $followUpQuestion,
                        'rewritten_query' => $rewrittenQuery,
                    ]);

                    // Preserve the original question so any entity (order ID, product name) is not lost
                    return trim($originalQuestion . ' ' . $followUpQuestion);
                }

                return $rewrittenQuery;
            }

            Log::warning('Follow-up rewrite failed; using follow-up question directly', [
                'follow_up_question' => $followUpQuestion,
                'rewrite_model' => $rewriteModel,
            ]);

            return trim($originalQuestion . ' ' . $followUpQuestion);
        } catch (\Throwable $e) {
            Log::warning('Follow-up rewrite exception; using follow-up question directly', [
                'follow_up_question' => $followUpQuestion,
                'error' => $e->getMessage(),
            ]);

            return trim($originalQuestion . ' ' . $followUpQuestion);
        }
    }

    private function normalizeRewriteOutput(string $rewritten, string $original): string
    {
        $text = trim($rewritten);
        if ($text === '') {
            return trim($original);
        }

        $text = preg_replace('/^```[a-zA-Z]*\s*/', '', $text) ?? $text;
        $text = preg_replace('/```$/', '', $text) ?? $text;

        $lines = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $text) ?: []), function ($line) {
            return $line !== '';
        }));

        if (!empty($lines)) {
            $first = $lines[0];
            $first = preg_replace('/^(?:[-*]|\d+[.)])\s*/', '', $first) ?? $first;
            $first = preg_replace('/^(?:core\s+product\/?category|qualifier\s+keywords?)\s*[:\-]\s*/i', '', $first) ?? $first;
            $text = trim($first);
        }

        if (str_contains($text, ',')) {
            $parts = array_values(array_filter(array_map(function ($part) {
                return trim((string) preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', $part));
            }, explode(',', $text)), function ($part) {
                return $part !== '';
            }));
            if (!empty($parts)) {
                $text = implode(' ', array_slice($parts, 0, 4));
            }
        }

        $text = trim((string) preg_replace('/\s+/', ' ', $text));
        return $text !== '' ? $text : trim($original);
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
    /**
     * Recursively sanitise a value so json_encode never throws on malformed UTF-8.
     * Invalid byte sequences are replaced with the UTF-8 replacement character (U+FFFD).
     */
    private function sanitizeForJson(mixed $value): mixed
    {
        if (is_string($value)) {
            // Replace any invalid UTF-8 sequences
            $clean = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            // Fallback: strip bytes that are still invalid after re-encode
            if (!mb_check_encoding($clean, 'UTF-8')) {
                $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|[\x80-\xFF][\x00-\x3F\x80-\xFF]/u', '', $clean) ?? $value;
            }
            return $clean;
        }
        if (is_array($value)) {
            return array_map(fn($v) => $this->sanitizeForJson($v), $value);
        }
        return $value;
    }

    public function llmChat($messages, $model = null, $userId = null, $organizationId = null, array $options = [])  // Default determined dynamically
    {
        try {
            // Use configured model if none provided
            if (!$model) {
                $model = $this->getLlamaModel();
            }

            // Sanitise message content to prevent json_encode failures on malformed UTF-8
            // (commonly caused by special chars in Shopify product descriptions)
            $messages = $this->sanitizeForJson($messages);

            $payload = [
                'messages' => $messages,
                'model' => $model,
                'backend_type' => $this->getBackendType()
            ];
            if (!empty($options)) {
                $payload['options'] = $options;
            }
            
            // Truncate logged payload to keep logs lean
            $payloadPreview = substr(json_encode($payload), 0, 100);
            Log::info('AI Agent LLM chat request', [
                'url' => "{$this->baseUrl}/llm/chat",
                'payload_preview' => $payloadPreview,
                'payload_length' => strlen(json_encode($payload)),
                'timeout' => 120,
                'model' => $payload['model'],
                'backend_type' => $payload['backend_type'],
                'options_count' => !empty($options) ? count($options) : 0
            ]);

            $response = Http::timeout(120)->post("{$this->baseUrl}/llm/chat", $payload);

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
                    $inputTokens = 0;
                    $outputTokens = 0;

                    // Try to get tokens from response first
                    if (isset($result['usage']['total_tokens'])) {
                        $tokensUsed    = (int) $result['usage']['total_tokens'];
                        $inputTokens   = (int) ($result['usage']['prompt_tokens'] ?? 0);
                        $outputTokens  = (int) ($result['usage']['completion_tokens'] ?? 0);
                        // Fallback if breakdown missing but total present
                        if ($inputTokens === 0 && $outputTokens === 0 && $tokensUsed > 0) {
                            $inputText    = json_encode($messages);
                            $outputText   = $result['message']['content'] ?? '';
                            $inputTokens  = max(1, (int) (strlen($inputText) / 4));
                            $outputTokens = max(1, (int) (strlen($outputText) / 4));
                        }
                    } else {
                        // Estimate tokens if not provided by FastAPI
                        $inputText    = json_encode($messages);
                        $outputText   = isset($result['message']['content']) ? $result['message']['content'] : '';
                        $inputTokens  = max(1, (int) (strlen($inputText) / 4));
                        $outputTokens = max(1, (int) (strlen($outputText) / 4));
                        $tokensUsed   = $inputTokens + $outputTokens;
                    }

                    $this->logTokenUsage(
                        $userId,
                        $organizationId,
                        'llm_chat',
                        $tokensUsed,
                        substr($payloadPreview, 0, 255),
                        $inputTokens,
                        $outputTokens
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
            ]);

            Log::info('OpenAI chat response', [
                'id' => $result->id,
                'model' => $result->model,
                'usage' => $result->usage->toArray(),
            ]);

            Log::info('OpenAI choices debug', [
                'choices_count' => count($result->choices),
                'first_choice' => isset($result->choices[0]) ? [
                    'message_role' => $result->choices[0]->message->role ?? 'null',
                    'message_content' => $result->choices[0]->message->content ?? 'null',
                    'content_length' => strlen($result->choices[0]->message->content ?? ''),
                    'finish_reason' => $result->choices[0]->finishReason ?? 'null',
                ] : 'no_choices',
                'raw_result_keys' => array_keys($result->toArray()),
                'choices_structure' => isset($result->choices[0]) ? $result->choices[0]->toArray() : null,
            ]);

            $content = trim((string) ($result->choices[0]->message->content ?? ''));
            if ($content === '') {
                Log::warning('OpenAI returned empty content; falling back to local model', [
                    'model' => $model,
                    'finish_reason' => $result->choices[0]->finishReason ?? null,
                ]);
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
                'usage_tokens' => $response['usage']['total_tokens'] ?? 0,
                'selected_model' => $model,
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
     * Log token usage for widget streaming responses
     */
    public function logWidgetTokenUsage(int $organizationId, array $messages, string $responseText, string $endpointType = 'llm_chat_stream'): void
    {
        try {
            $inputTokens  = max(1, (int) $this->estimateTokenCount($messages));
            $outputTokens = max(1, (int) (strlen($responseText) / 4));
            $totalTokens  = $inputTokens + $outputTokens;
            $summary = 'in:' . $inputTokens . ' out:' . $outputTokens . ' | ' . substr(json_encode($messages), 0, 200);

            $this->logTokenUsage(null, $organizationId, $endpointType, $totalTokens, $summary, $inputTokens, $outputTokens);
        } catch (\Exception $e) {
            Log::error('Failed to log widget token usage', [
                'organization_id' => $organizationId,
                'error' => $e->getMessage()
            ]);
        }
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
    public function smartLlmChat($messages, $model = null, $userId = null, $organizationId = null, array $options = [])
    {
        if ($this->isOpenAiProvider()) {
            // Always use GPT-5-mini as it's the only allowed model
            $openAiModel = 'gpt-5-mini';
            return $this->openAiChat($messages, $openAiModel, $userId, $organizationId);
        } else {
            $llamaModel = $model ?: $this->getLlamaModel();
            return $this->llmChat($messages, $llamaModel, $userId, $organizationId, $options);
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
            $items = $this->normalizeItemsForQdrant($items);

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
    public function updateDataToQdrant($organizationSlug, $dataType, $items, int $timeoutSeconds = 60)
    {
        try {
            $items = $this->normalizeItemsForQdrant($items);

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

            $response = Http::timeout(max(30, $timeoutSeconds))->post("{$this->baseUrl}/update_data", $payload);

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
                'item_count' => is_array($items) ? count($items) : 0,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Transcribe an audio file using FastAPI personal assistant endpoint.
     */
    public function transcribeAudio(string $audioFilePath, array $options = [])
    {
        try {
            if (!is_file($audioFilePath)) {
                Log::warning('Transcribe audio file not found', ['path' => $audioFilePath]);
                return null;
            }

            $language = $options['language'] ?? 'auto';
            $provider = $options['provider'] ?? 'auto';
            $prompt = $options['prompt'] ?? '';

            $response = Http::timeout(120)
                ->attach('audio', fopen($audioFilePath, 'r'), basename($audioFilePath))
                ->asMultipart()
                ->post("{$this->baseUrl}/voice/transcribe", [
                    'language' => $language,
                    'provider' => $provider,
                    'prompt' => $prompt,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Voice transcription failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'language' => $language,
                'provider' => $provider,
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Voice transcription exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Convert assistant response text to speech.
     */
    public function synthesizeSpeech(string $text, array $options = [])
    {
        try {
            $payload = [
                'text' => $text,
                'provider' => $options['provider'] ?? 'auto',
                'language' => $options['language'] ?? 'en',
                'speaker' => $options['speaker'] ?? '',
            ];

            $response = Http::timeout(120)->post("{$this->baseUrl}/voice/synthesize", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Speech synthesis failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'provider' => $payload['provider'],
                'language' => $payload['language'],
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Speech synthesis exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parse voice command into structured intent/action payload.
     */
    public function parseAssistantCommand(string $query, array $options = [])
    {
        try {
            $payload = [
                'query' => $query,
                'language' => $options['language'] ?? 'en',
                'model' => $options['model'] ?? $this->getLlamaModel(),
                'context' => $options['context'] ?? [],
            ];

            $response = Http::timeout(60)->post("{$this->baseUrl}/assistant/parse_command", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Assistant command parse failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'query_preview' => substr($query, 0, 200),
            ]);
            return null;
        } catch (\Exception $e) {
            Log::error('Assistant command parse exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Log token usage for billing and monitoring purposes
     */
    private function logTokenUsage($userId, $organizationId, $endpointType, $tokensUsed, $requestSummary, int $inputTokens = 0, int $outputTokens = 0)
    {
        try {
            $subscription = null;
            
            // Handle widget usage (no user ID) - assign to organization's first user
            if (!$userId && $organizationId) {
                $organization = \App\Models\Organization::find($organizationId);
                if ($organization) {
                    // Prefer admin user, then legacy org user, then first linked user
                    $firstUser = $organization->users()->where('role', 'admin')->first();
                    if (!$firstUser) {
                        $firstUser = $organization->legacyUsers()->where('role', 'admin')->first();
                    }
                    if (!$firstUser) {
                        $firstUser = $organization->legacyUsers()->first();
                    }
                    if (!$firstUser) {
                        $firstUser = $organization->users()->first();
                    }
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
                'input_tokens' => $inputTokens > 0 ? $inputTokens : null,
                'output_tokens' => $outputTokens > 0 ? $outputTokens : null,
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
                    'plan_limit' => $subscription->subscriptionPlan->token_cap ?? 'unlimited'
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

    private function normalizeItemsForQdrant(array $items): array
    {
        return array_map(function ($item) {
            if (!is_array($item)) {
                return $item;
            }

            $metadata = is_array($item['metadata'] ?? null) ? $item['metadata'] : [];

            $keywords = trim((string) (
                $item['keywords']
                ?? $item['search_keywords']
                ?? data_get($metadata, 'keywords')
                ?? data_get($metadata, 'search_keywords')
                ?? data_get($metadata, 'csv.keywords')
                ?? data_get($metadata, 'csv.search_keywords')
                ?? ''
            ));

            if ($keywords !== '') {
                $item['keywords'] = $keywords;
                $item['search_keywords'] = $keywords;

                $metadata['keywords'] = $keywords;
                $metadata['search_keywords'] = $keywords;

                $csvMeta = is_array($metadata['csv'] ?? null) ? $metadata['csv'] : [];
                if (($csvMeta['keywords'] ?? '') === '') {
                    $csvMeta['keywords'] = $keywords;
                }
                if (($csvMeta['search_keywords'] ?? '') === '') {
                    $csvMeta['search_keywords'] = $keywords;
                }
                $metadata['csv'] = $csvMeta;
            }

            $entity = trim((string) (
                $item['entity']
                ?? $item['primary_entity']
                ?? data_get($metadata, 'entity')
                ?? data_get($metadata, 'primary_entity')
                ?? data_get($metadata, 'csv.entity')
                ?? data_get($metadata, 'csv.primary_entity')
                ?? $keywords
                ?? ''
            ));

            if ($entity === '') {
                $title = trim((string) ($item['title'] ?? ''));
                if ($title !== '') {
                    $entityFromTitle = trim((string) preg_replace('/\|.*$/', '', $title));
                    if ($entityFromTitle !== '') {
                        $entity = $entityFromTitle;
                    }
                }
            }

            if ($entity === '') {
                $entity = trim((string) ($item['category'] ?? data_get($metadata, 'category') ?? ''));
            }

            if ($entity !== '') {
                $item['entity'] = $entity;
                $item['primary_entity'] = $entity;
                $metadata['entity'] = $entity;
                $metadata['primary_entity'] = $entity;

                $csvMeta = is_array($metadata['csv'] ?? null) ? $metadata['csv'] : [];
                if (($csvMeta['entity'] ?? '') === '') {
                    $csvMeta['entity'] = $entity;
                }
                if (($csvMeta['primary_entity'] ?? '') === '') {
                    $csvMeta['primary_entity'] = $entity;
                }
                $metadata['csv'] = $csvMeta;
            }

            [$entity, $keywords, $semanticTerms, $semanticText] = $this->buildOrgNeutralSemanticMetadata(
                $item,
                $metadata,
                $entity,
                $keywords
            );

            if ($entity !== '') {
                $item['entity'] = $entity;
                $item['primary_entity'] = $entity;
                $metadata['entity'] = $entity;
                $metadata['primary_entity'] = $entity;
            }

            if ($keywords !== '') {
                $item['keywords'] = $keywords;
                $item['search_keywords'] = $keywords;
                $metadata['keywords'] = $keywords;
                $metadata['search_keywords'] = $keywords;
            }

            if (!empty($semanticTerms)) {
                $item['semantic_terms'] = array_values($semanticTerms);
                $metadata['semantic_terms'] = array_values($semanticTerms);
            }

            if ($semanticText !== '') {
                $item['semantic_text'] = $semanticText;
                $metadata['semantic_text'] = $semanticText;
            }

            $csvMeta = is_array($metadata['csv'] ?? null) ? $metadata['csv'] : [];
            if ($entity !== '' && ($csvMeta['entity'] ?? '') === '') {
                $csvMeta['entity'] = $entity;
            }
            if ($entity !== '' && ($csvMeta['primary_entity'] ?? '') === '') {
                $csvMeta['primary_entity'] = $entity;
            }
            if ($keywords !== '' && ($csvMeta['keywords'] ?? '') === '') {
                $csvMeta['keywords'] = $keywords;
            }
            if ($keywords !== '' && ($csvMeta['search_keywords'] ?? '') === '') {
                $csvMeta['search_keywords'] = $keywords;
            }
            if (!empty($semanticTerms) && empty($csvMeta['semantic_terms'])) {
                $csvMeta['semantic_terms'] = array_values($semanticTerms);
            }
            if ($semanticText !== '' && ($csvMeta['semantic_text'] ?? '') === '') {
                $csvMeta['semantic_text'] = $semanticText;
            }
            $metadata['csv'] = $csvMeta;

            $item['metadata'] = $metadata;

            return $item;
        }, $items);
    }

    private function buildOrgNeutralSemanticMetadata(array $item, array $metadata, string $entity, string $keywords): array
    {
        $title = trim((string) ($item['title'] ?? ''));
        $content = trim((string) ($item['content'] ?? ''));
        $category = trim((string) ($item['category'] ?? data_get($metadata, 'category') ?? ''));
        $csv = is_array($metadata['csv'] ?? null) ? $metadata['csv'] : [];

        $seedText = trim(implode(' ', array_filter([
            $title,
            $content,
            $category,
            $entity,
            $keywords,
            (string) data_get($metadata, 'description', ''),
            (string) data_get($csv, 'description', ''),
            (string) data_get($csv, 'plan_name', ''),
            (string) data_get($csv, 'plan_type', ''),
            (string) data_get($csv, 'billing_period', ''),
        ])));

        $existingTerms = $this->extractSemanticTerms($keywords);
        $existingSemanticTerms = data_get($metadata, 'semantic_terms');
        if (is_array($existingSemanticTerms)) {
            $existingTerms = array_merge($existingTerms, $this->extractSemanticTerms($existingSemanticTerms));
        } elseif (is_string($existingSemanticTerms)) {
            $existingTerms = array_merge($existingTerms, $this->extractSemanticTerms($existingSemanticTerms));
        }

        $baseTerms = $this->extractLexicalTermsForRerank($seedText);
        $normalizedSeed = $this->normalizeSearchText($seedText);

        $synonymMap = [
            'enterprise' => ['corporate', 'business', 'large organization', 'high volume', 'scalable'],
            'corporate' => ['enterprise', 'business', 'company', 'organization', 'high volume'],
            'business' => ['corporate', 'enterprise', 'company', 'team'],
            'starter' => ['basic', 'entry level', 'small team', 'beginner'],
            'basic' => ['starter', 'entry level', 'simple plan', 'affordable'],
            'free' => ['trial', 'no cost', 'starter free', 'complimentary'],
            'subscription' => ['monthly plan', 'yearly plan', 'recurring billing', 'plan'],
            'plan' => ['package', 'tier', 'subscription', 'pricing option'],
            'pricing' => ['cost', 'price', 'charges', 'fee'],
            'credits' => ['token pack', 'one time package', 'prepaid tokens'],
            'monthly' => ['per month', 'recurring monthly', 'month to month'],
            'yearly' => ['annual', 'per year', 'yearly billing'],
        ];

        $expandedTerms = [];
        foreach ($synonymMap as $trigger => $aliases) {
            if (str_contains($normalizedSeed, $trigger)) {
                foreach ($aliases as $alias) {
                    $expandedTerms[] = $alias;
                }
            }
        }

        if ($this->shouldUseLlmSemanticExpansion()) {
            $llmAliases = $this->generateSemanticAliasesWithLlm($seedText, $entity);
            if (!empty($llmAliases)) {
                $expandedTerms = array_merge($expandedTerms, $llmAliases);
            }
        }

        if ($entity === '' && $title !== '') {
            $entity = trim((string) preg_replace('/\|.*$/', '', $title));
        }
        if ($entity === '') {
            $entity = $category;
        }

        $entityTerms = $this->extractSemanticTerms($entity);
        $allTerms = array_merge($entityTerms, $existingTerms, $baseTerms, $expandedTerms);

        $deduped = [];
        foreach ($allTerms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $norm = $this->normalizeSearchText($term);
            if ($norm === '' || mb_strlen($norm) < 2) {
                continue;
            }
            $deduped[$norm] = $norm;
        }

        $semanticTerms = array_slice(array_values($deduped), 0, 28);
        $semanticText = implode(' ', $semanticTerms);
        $keywordsOut = implode(', ', array_slice($semanticTerms, 0, 16));

        return [$entity, $keywordsOut, $semanticTerms, $semanticText];
    }

    private function shouldUseLlmSemanticExpansion(): bool
    {
        $enabled = env('AI_SEMANTIC_ENRICH_WITH_LLM', false);
        if (is_bool($enabled)) {
            return $enabled;
        }

        return in_array(strtolower((string) $enabled), ['1', 'true', 'yes', 'on'], true);
    }

    private function generateSemanticAliasesWithLlm(string $seedText, string $entity): array
    {
        $seedText = trim($seedText);
        if ($seedText === '') {
            return [];
        }

        try {
            $model = 'llama3.2:1b';
            $systemPrompt = "Generate concise search synonyms and related user intent terms for semantic retrieval. "
                . "Return STRICT JSON only: {\"terms\":[\"term1\",\"term2\"]}. "
                . "Rules: 6-12 terms, lowercase, short phrases allowed, no duplicates, no markdown, no explanations.";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => json_encode([
                    'entity' => $entity,
                    'text' => mb_substr($seedText, 0, 700),
                ])],
            ];

            $response = $this->llmChat($messages, $model, null, null, ['num_predict' => 140, 'temperature' => 0.2]);
            $content = trim((string) data_get($response, 'message.content', ''));
            if ($content === '') {
                return [];
            }

            $parsed = json_decode($content, true);
            if (!is_array($parsed) || !is_array($parsed['terms'] ?? null)) {
                return [];
            }

            $terms = [];
            foreach ($parsed['terms'] as $term) {
                $term = $this->normalizeSearchText((string) $term);
                if ($term !== '' && mb_strlen($term) >= 2) {
                    $terms[$term] = $term;
                }
            }

            return array_slice(array_values($terms), 0, 12);
        } catch (\Throwable $e) {
            Log::warning('LLM semantic enrichment skipped', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    private function extractSemanticTerms($value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $raw = trim((string) $value);
            if ($raw === '') {
                return [];
            }
            $parts = preg_split('/[,;|\/\n]+/', $raw) ?: [];
        }

        $terms = [];
        foreach ($parts as $part) {
            $part = trim((string) $part);
            if ($part === '') {
                continue;
            }

            $norm = $this->normalizeSearchText($part);
            if ($norm === '') {
                continue;
            }
            $terms[] = $norm;
        }

        return $terms;
    }

    /**
     * @internal Kept as no-op placeholder. Abbreviation expansion is now handled
     * per-org via the "Search Synonyms" column in the CSV editor, which embeds
     * synonyms directly into Qdrant vector content at import time — zero runtime cost.
     */
    private function expandMedicalAbbreviations(string $query): string
    {
        return $query;
    }

}
