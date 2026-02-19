<?php

namespace App\Services;

use App\Models\OrganizationAction;
use App\Models\Organization;
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
            $useIntent = $this->aiAgent->useIntentAndRewrite();
            $intentResult = $useIntent
                ? $this->intentDetector->detectIntent($query, $organizationId)
                : ['intent' => 'general_qna', 'confidence' => 0.0];

            if (!$useIntent) {
                Log::info('Intent gating disabled for actions', [
                    'organization_id' => $organizationId
                ]);
            }

            // Step 2: Find matching actions (even if intent says static_info) so we can run high-confidence actions
            $matchingActions = $this->findMatchingActions($query, $organizationId, $intentResult, $useIntent);
            $topAction = $matchingActions[0] ?? null;
            $topScore = $topAction['score'] ?? 0;
            $scoreThreshold = $topAction && isset($topAction['action']->min_score_threshold)
                ? (float) $topAction['action']->min_score_threshold
                : 0.7;

            $requiresAction = $useIntent ? $this->intentDetector->requiresAction($intentResult) : false;
            $topMethod = $topAction['method'] ?? null;
            $shouldExecuteAction = $requiresAction || ($topAction && ($topMethod === 'keyword' || $topScore >= $scoreThreshold));

            if (!$shouldExecuteAction) {
                Log::info('No action required, using knowledge base', [
                    'intent' => $intentResult,
                    'top_action_score' => $topScore,
                    'score_threshold' => $scoreThreshold,
                ]);
                
                return [
                    'type' => 'knowledge_base',
                    'intent' => $intentResult,
                    'message' => 'Use knowledge base for this query'
                ];
            }

            // Step 3: If we decided to execute an action, ensure we have a match
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

            // Ensure API actions receive the raw user query when not extracted
            if (!array_key_exists('query', $parameters) && $bestAction['action']->source_type === 'api') {
                $parameters['query'] = $query;
            }

            // Provide raw query for other action types (e.g., google_sheets) when missing
            if (!array_key_exists('query', $parameters)) {
                $parameters['query'] = $query;
            }

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
    private function findMatchingActions(string $query, int $organizationId, array $intentResult, bool $useIntent = true): array
    {
        // Get active actions for this organization
        $actions = OrganizationAction::forOrganization($organizationId)
            ->active()
            ->get();

        if ($actions->isEmpty()) {
            return [];
        }

        // Get recommended action types based on intent (optional)
        $recommendedTypes = $useIntent
            ? $this->intentDetector->getRecommendedActionTypes($intentResult)
            : [];

        $matches = [];
        $queryLower = strtolower($query);
        $pricingRequiresKeywords = $this->shouldRequirePricingKeywords($organizationId);
        $pricingKeywords = $this->getPricingKeywordsForOrganization($organizationId);

        // First try keyword-based matching (more reliable)
        foreach ($actions as $action) {
            // Skip if action type doesn't match intent (unless no specific types recommended)
            if (!empty($recommendedTypes) && !in_array($action->action_type, $recommendedTypes)) {
                continue;
            }

            if ($pricingRequiresKeywords
                && $action->action_type === 'pricing'
                && !$this->queryHasPricingSignals($queryLower, $pricingKeywords)) {
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

        // If no keyword matches, allow a safe pricing-intent fallback for a single pricing action
        if (empty($matches)) {
            $intent = $intentResult['intent'] ?? null;
            $pricingActions = $actions->filter(function ($action) use ($recommendedTypes) {
                if (!empty($recommendedTypes) && !in_array($action->action_type, $recommendedTypes)) {
                    return false;
                }
                return in_array($action->action_type, ['pricing', 'cost', 'rates'], true);
            })->values();

            if ($useIntent && $intent === 'pricing'
                && $this->queryHasPricingSignals($queryLower, $pricingKeywords)
                && $pricingActions->count() === 1) {
                $fallbackAction = $pricingActions->first();
                $matches[] = [
                    'action' => $fallbackAction,
                    'score' => (float) ($fallbackAction->min_score_threshold ?? 0.7),
                    'method' => 'intent_fallback',
                    'matched_terms' => $pricingKeywords
                ];

                Log::info('Pricing intent fallback matched action', [
                    'action_id' => $fallbackAction->id,
                    'action_name' => $fallbackAction->name,
                    'reason' => 'pricing intent with pricing keywords and single pricing action'
                ]);
            }
        }

        // If still no matches, try semantic similarity as fallback
        if (empty($matches)) {
            try {
                $queryEmbedding = $this->aiAgent->embed($query);

                if ($queryEmbedding) {
                    foreach ($actions as $action) {
                        // Skip if action type doesn't match intent (unless no specific types recommended)
                        if (!empty($recommendedTypes) && !in_array($action->action_type, $recommendedTypes)) {
                            continue;
                        }

                        if ($pricingRequiresKeywords
                            && $action->action_type === 'pricing'
                            && !$this->queryHasPricingSignals($queryLower, $pricingKeywords)) {
                            continue;
                        }

                        // Generate embedding for action description + aliases
                        $actionText = $action->getTextForEmbedding();
                        $actionEmbedding = $this->aiAgent->embed($actionText);

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

    private function shouldRequirePricingKeywords(int $organizationId): bool
    {
        $org = Organization::find($organizationId);
        if (!$org) {
            return false;
        }

        $settings = $org->settings ?? [];
        return (bool) ($settings['pricing_action_requires_keywords'] ?? false);
    }

    private function getPricingKeywordsForOrganization(int $organizationId): array
    {
        $defaults = [
            'price', 'pricing', 'cost', 'fees', 'fee', 'quote', 'estimate', 'budget',
            'charges', 'charge', 'rate', 'rates', 'plan', 'plans', 'package', 'packages',
            'discount', 'offer', 'promo', 'promotion', 'deal', 'sale'
        ];

        $org = Organization::find($organizationId);
        $settings = $org?->settings ?? [];
        $custom = $settings['intent_keywords']['pricing'] ?? [];
        if (is_string($custom)) {
            $custom = array_filter(array_map('trim', preg_split('/[,\n]/', $custom)));
        }
        if (!is_array($custom)) {
            $custom = [];
        }

        return array_values(array_unique(array_filter(array_merge($defaults, $custom))));
    }

    private function queryHasPricingSignals(string $queryLower, array $keywords): bool
    {
        foreach ($keywords as $kw) {
            if ($kw === '') {
                continue;
            }
            if (str_contains($queryLower, strtolower($kw))) {
                return true;
            }
        }

        return false;
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
            // Use organization slug as collection name
            $collectionName = $action->organization->slug;
            
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
            
            // Use integer ID for Qdrant (actions use 10000+ range)
            $qdrantId = 10000 + $action->id;
            
            // Prepare payload with action metadata
            $payload = [
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
                $qdrantId
            );
            
            Log::info('Action synced to vector database', [
                'action_id' => $action->id,
                'qdrant_id' => $qdrantId,
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
            // Use organization slug as collection name
            $collectionName = $action->organization->slug;
            $qdrantId = 10000 + $action->id; // Same ID format as syncActionToVectorDB
            
            // Use the deleteDataFromQdrant method from AiAgentService
            $result = $this->aiAgent->deleteDataFromQdrant(
                $collectionName,
                $qdrantId,
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