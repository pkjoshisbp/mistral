<?php

namespace App\Services;

use App\Models\OrganizationAction;
use Illuminate\Support\Facades\Log;

class ActionService
{
    private AiAgentService $aiAgent;
    private IntentDetectionService $intentDetector;
    private ActionExecutorService $executor;

    public function __construct(
        AiAgentService $aiAgent,
        IntentDetectionService $intentDetector,
        ActionExecutorService $executor
    ) {
        $this->aiAgent = $aiAgent;
        $this->intentDetector = $intentDetector;
        $this->executor = $executor;
    }

    /**
     * Main entry point: analyze query and execute actions or return KB results
     */
    public function processQuery(string $query, int $organizationId, array $context = []): array
    {
        Log::info('ActionService processing query', [
            'query' => $query,
            'organization_id' => $organizationId
        ]);

        try {
            // Step 1: Detect intent
            $intentResult = $this->intentDetector->detectIntent($query, $organizationId);
            
            // Step 2: Check if action is needed
            if (!$this->intentDetector->requiresAction($intentResult)) {
                Log::info('No action required, using knowledge base', [
                    'intent' => $intentResult
                ]);
                
                return [
                    'type' => 'knowledge_base',
                    'intent' => $intentResult,
                    'message' => 'Use knowledge base for this query'
                ];
            }

            // Step 3: Find matching actions
            $matchingActions = $this->findMatchingActions($query, $organizationId, $intentResult);
            
            if (empty($matchingActions)) {
                Log::info('No matching actions found, fallback to knowledge base', [
                    'intent' => $intentResult,
                    'organization_id' => $organizationId
                ]);
                
                return [
                    'type' => 'knowledge_base',
                    'intent' => $intentResult,
                    'message' => 'No matching actions configured, using knowledge base'
                ];
            }

            // Step 4: Execute the best matching action
            $bestAction = $matchingActions[0];
            $parameters = $this->executor->extractParameters($query, $bestAction['action']);
            
            // Add temporal context to parameters
            $temporalContext = $this->intentDetector->extractTemporalContext($query);
            $parameters = array_merge($parameters, $temporalContext);

            Log::info('Executing action', [
                'action_id' => $bestAction['action']->id,
                'action_name' => $bestAction['action']->name,
                'similarity_score' => $bestAction['score'],
                'parameters' => $parameters
            ]);

            $actionResult = $this->executor->executeAction($bestAction['action'], $parameters);
            
            return [
                'type' => 'action_executed',
                'intent' => $intentResult,
                'action' => [
                    'id' => $bestAction['action']->id,
                    'name' => $bestAction['action']->name,
                    'type' => $bestAction['action']->action_type,
                    'similarity_score' => $bestAction['score']
                ],
                'parameters' => $parameters,
                'result' => $actionResult,
                'live_data' => $actionResult['success'] ? $actionResult['data'] : null
            ];

        } catch (\Exception $e) {
            Log::error('ActionService processing failed', [
                'query' => $query,
                'organization_id' => $organizationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'type' => 'error',
                'error' => 'Action processing failed: ' . $e->getMessage(),
                'fallback_to_kb' => true
            ];
        }
    }

    /**
     * Find actions that match the user query using semantic similarity
     */
    private function findMatchingActions(string $query, int $organizationId, array $intentResult): array
    {
        // Get active actions for this organization
        $actions = OrganizationAction::forOrganization($organizationId)
            ->active()
            ->get();

        if ($actions->isEmpty()) {
            return [];
        }

        // Get recommended action types based on intent
        $recommendedTypes = $this->intentDetector->getRecommendedActionTypes($intentResult);

        $matches = [];
        $queryLower = strtolower($query);

        // First try keyword-based matching (more reliable)
        foreach ($actions as $action) {
            // Skip if action type doesn't match intent (unless no specific types recommended)
            if (!empty($recommendedTypes) && !in_array($action->action_type, $recommendedTypes)) {
                continue;
            }

            // Check for keyword matches
            $keywordScore = 0;
            $matchedKeywords = [];
            
            foreach ($action->keywords ?? [] as $keyword) {
                if (str_contains($queryLower, strtolower($keyword))) {
                    $keywordScore += 0.2; // Each keyword match adds 0.2
                    $matchedKeywords[] = $keyword;
                }
            }
            
            // Check aliases too
            foreach ($action->aliases ?? [] as $alias) {
                if (str_contains($queryLower, strtolower($alias))) {
                    $keywordScore += 0.15;
                    $matchedKeywords[] = $alias;
                }
            }
            
            // If we have keyword matches, use that score
            if ($keywordScore > 0) {
                $matches[] = [
                    'action' => $action,
                    'score' => min($keywordScore, 1.0), // Cap at 1.0
                    'method' => 'keyword',
                    'matched_terms' => $matchedKeywords
                ];
                continue;
            }
        }

        // If no keyword matches, try semantic similarity as fallback
        if (empty($matches)) {
            try {
                $queryEmbedding = $this->aiAgentService->generateEmbedding($query);

                if ($queryEmbedding) {
                    foreach ($actions as $action) {
                        // Skip if action type doesn't match intent (unless no specific types recommended)
                        if (!empty($recommendedTypes) && !in_array($action->action_type, $recommendedTypes)) {
                            continue;
                        }

                        // Generate embedding for action description + aliases
                        $actionText = $action->getTextForEmbedding();
                        $actionEmbedding = $this->aiAgentService->generateEmbedding($actionText);

                        if (!$actionEmbedding) {
                            continue;
                        }

                        // Calculate cosine similarity
                        $similarity = $this->calculateCosineSimilarity($queryEmbedding, $actionEmbedding);
                        
                        Log::debug('Action similarity calculated', [
                            'action_id' => $action->id,
                            'action_name' => $action->name,
                            'similarity' => $similarity,
                            'threshold' => $action->min_score_threshold
                        ]);

                        // Check if similarity meets threshold
                        if ($similarity >= $action->min_score_threshold) {
                            $matches[] = [
                                'action' => $action,
                                'score' => $similarity,
                                'method' => 'semantic',
                                'action_text' => $actionText
                            ];
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Semantic matching failed, falling back to basic keyword matching', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Sort by similarity score (highest first)
        usort($matches, function($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        Log::info('Action matching completed', [
            'total_actions' => $actions->count(),
            'matches_found' => count($matches),
            'top_match_score' => !empty($matches) ? $matches[0]['score'] : null
        ]);

        return $matches;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;

        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Store action metadata in vector database for better semantic search
     */
    public function syncActionToVectorDB(OrganizationAction $action): bool
    {
        try {
            $collectionName = "org_{$action->organization_id}";
            
            // Prepare text for embedding
            $textForEmbedding = $action->getTextForEmbedding();
            
            // Generate embedding
            $embedding = $this->aiAgent->embed($textForEmbedding);
            
            if (!$embedding) {
                Log::warning('Failed to generate embedding for action', [
                    'action_id' => $action->id
                ]);
                return false;
            }
            
            // Prepare payload with action metadata
            $payload = [
                'id' => "action_{$action->id}",
                'source_type' => 'action',
                'action_id' => $action->id,
                'action_type' => $action->action_type,
                'action_name' => $action->name,
                'description' => $action->description,
                'source_data_type' => $action->source_type,
                'organization_id' => $action->organization_id,
                'min_score_threshold' => $action->min_score_threshold,
                'is_active' => $action->is_active,
                'text_for_search' => $textForEmbedding
            ];
            
            // Store in vector database
            $result = $this->aiAgent->addToQdrant(
                $collectionName,
                $embedding,
                $payload,
                "action_{$action->id}"
            );
            
            Log::info('Action synced to vector database', [
                'action_id' => $action->id,
                'collection' => $collectionName,
                'success' => $result !== null
            ]);
            
            return $result !== null;
            
        } catch (\Exception $e) {
            Log::error('Failed to sync action to vector DB', [
                'action_id' => $action->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Delete action from vector database
     */
    public function removeActionFromVectorDB(OrganizationAction $action): bool
    {
        try {
            $collectionName = "org_{$action->organization_id}";
            $actionId = "action_{$action->id}";
            
            // Use the deleteDataFromQdrant method from AiAgentService
            $result = $this->aiAgent->deleteDataFromQdrant(
                $collectionName,
                $actionId,
                'action'
            );
            
            Log::info('Action removed from vector database', [
                'action_id' => $action->id,
                'collection' => $collectionName,
                'success' => $result
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Failed to remove action from vector DB', [
                'action_id' => $action->id,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Format live data for AI context
     */
    private function formatLiveDataForAI(array $data, string $actionType): string
    {
        if (empty($data)) {
            return "No data found for this query.";
        }

        $formatted = "Here's the current live data:\n\n";
        
        // Format based on action type
        switch ($actionType) {
            case 'pricing':
                $formatted .= $this->formatPricingData($data);
                break;
            case 'availability':
                $formatted .= $this->formatAvailabilityData($data);
                break;
            case 'inventory':
                $formatted .= $this->formatInventoryData($data);
                break;
            default:
                $formatted .= $this->formatGenericData($data);
        }

        return $formatted;
    }    /**
     * Format tabular data for better AI readability
     */
    private function formatTableData(array $data): string
    {
        if (empty($data)) {
            return "No records found.";
        }

        $count = count($data);
        $sample = array_slice($data, 0, 5); // Show max 5 records to avoid token limits
        
        $formatted = "";
        
        foreach ($sample as $index => $record) {
            $formatted .= "Record " . ($index + 1) . ":\n";
            
            if (is_array($record)) {
                foreach ($record as $key => $value) {
                    $formatted .= "  {$key}: {$value}\n";
                }
            } else {
                $formatted .= "  {$record}\n";
            }
            
            $formatted .= "\n";
        }
        
        if ($count > 5) {
            $formatted .= "... and " . ($count - 5) . " more records.\n";
        }
        
        return $formatted;
    }

    /**
     * Get action statistics for organization
     */
    public function getActionStats(int $organizationId): array
    {
        $actions = OrganizationAction::forOrganization($organizationId)->get();
        
        $stats = [
            'total_actions' => $actions->count(),
            'active_actions' => $actions->where('is_active', true)->count(),
            'by_source_type' => $actions->groupBy('source_type')->map->count(),
            'by_action_type' => $actions->groupBy('action_type')->map->count(),
        ];
        
        return $stats;
    }
}