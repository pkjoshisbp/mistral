<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiAgentService
{
    private $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_agent.url', 'http://localhost:8111');
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
                    Log::info('AI Agent embed response', ['response' => $data]);
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
            // Increase limit to get more results for filtering
            $response = Http::timeout(30)->post("{$this->baseUrl}/qdrant/search", [
                'collection_name' => $collectionName,
                'query_vector' => $queryVector,
                'limit' => max($limit, 10)
            ]);

            if ($response->successful()) {
                $data = $response->json();
                // Prioritize MRI/magnetic resonance results in PHP before returning
                if (isset($data['results']) && is_array($data['results'])) {
                    $mriResults = [];
                    $otherResults = [];
                    foreach ($data['results'] as $result) {
                        $payload = $result['payload'] ?? [];
                        $content = $payload['content'] ?? '';
                        if (stripos($content, 'MRI') !== false || stripos($content, 'magnetic resonance') !== false) {
                            $mriResults[] = $result;
                        } else {
                            $otherResults[] = $result;
                        }
                    }
                    // Merge MRI results first, then others, and trim to requested limit
                    $data['results'] = array_slice(array_merge($mriResults, $otherResults), 0, $limit);
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
     * Get LLM answer
     */
    public function llmAnswer($prompt, $model = 'mistral:7b')
    {
        try {
            $response = Http::timeout(60)->post("{$this->baseUrl}/llm/answer", [
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
    public function llmChat($messages, $model = 'mistral:7b')  // Default to mistral:7b for better answers
    {
        try {
            $payload = [
                'messages' => $messages,
                'model' => $model
            ];
            
            Log::info('AI Agent LLM chat request', [
                'url' => "{$this->baseUrl}/llm/chat",
                'payload' => $payload,
                'timeout' => 90
            ]);

            $response = Http::timeout(90)->post("{$this->baseUrl}/llm/chat", $payload);

            Log::info('AI Agent LLM chat response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body_length' => strlen($response->body()),
                'body_preview' => substr($response->body(), 0, 200)
            ]);

            return $response->successful() ? $response->json() : null;
        } catch (\Exception $e) {
            Log::error('AI Agent LLM chat exception', ['error' => $e->getMessage()]);
            return null;
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
}
