<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class IntentDetectionService
{
    private AiAgentService $aiAgent;

    // Rule-based keywords for quick intent detection
    private const INTENT_KEYWORDS = [
        'booking' => [
            'reserve', 'book', 'booking', 'reservation', 'availability', 'available',
            'schedule', 'appointment', 'check availability', 'room available',
            'slot available', 'can i book', 'can i reserve'
        ],
        'pricing' => [
            'price', 'cost', 'fee', 'charge', 'rate', 'pricing', 'how much',
            'tuition', 'fees', 'payment', 'bill', 'invoice', 'expense'
        ],
        'realtime_data' => [
            'current', 'now', 'today', 'live', 'real-time', 'status', 'check',
            'inventory', 'stock', 'balance', 'account', 'latest', 'update'
        ],
        'lookup' => [
            'find', 'search', 'lookup', 'get', 'show', 'list', 'display',
            'which', 'what', 'where', 'when', 'who', 'how many'
        ],
        'static_info' => [
            'policy', 'rule', 'guideline', 'procedure', 'faq', 'help',
            'information', 'about', 'description', 'explain', 'what is'
        ]
    ];

    // Action trigger patterns
    private const ACTION_PATTERNS = [
        'availability_check' => [
            'available', 'vacancy', 'free', 'open slot', 'check availability'
        ],
        'data_retrieval' => [
            'get data', 'show me', 'list all', 'find records', 'search for'
        ],
        'status_check' => [
            'status', 'current state', 'live data', 'real-time'
        ]
    ];

    public function __construct(AiAgentService $aiAgent)
    {
        $this->aiAgent = $aiAgent;
    }

    /**
     * Detect intent from user query using hybrid approach
     */
    public function detectIntent(string $query, int $organizationId): array
    {
        $query = strtolower(trim($query));
        
        Log::info('Intent detection started', [
            'query' => $query,
            'organization_id' => $organizationId
        ]);

        // Step 1: Rule-based quick detection
        $ruleBasedIntent = $this->detectIntentByRules($query);
        
        // Step 2: Enhanced detection with LLM for ambiguous cases
        $confidence = $ruleBasedIntent['confidence'];
        $llmIntent = null;
        
        if ($confidence < 0.8) {
            $llmIntent = $this->detectIntentWithLLM($query);
        }

        // Step 3: Combine results
        $finalIntent = $this->combineIntentResults($ruleBasedIntent, $llmIntent);
        
        Log::info('Intent detection completed', [
            'query' => $query,
            'rule_based' => $ruleBasedIntent,
            'llm_based' => $llmIntent,
            'final_intent' => $finalIntent
        ]);

        return $finalIntent;
    }

    /**
     * Rule-based intent detection using keywords
     */
    private function detectIntentByRules(string $query): array
    {
        $scores = [];
        
        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            $score = 0;
            $matches = 0;
            
            foreach ($keywords as $keyword) {
                if (Str::contains($query, $keyword)) {
                    $score += 1;
                    $matches++;
                    
                    // Boost score for exact matches
                    if ($keyword === $query || Str::startsWith($query, $keyword)) {
                        $score += 0.5;
                    }
                }
            }
            
            if ($score > 0) {
                // Normalize score based on keyword density and query length
                $normalizedScore = min($score / max(1, str_word_count($query) / 2), 1.0);
                $scores[$intent] = $normalizedScore;
            }
        }

        if (empty($scores)) {
            return [
                'intent' => 'static_info',
                'confidence' => 0.3,
                'method' => 'rule_default',
                'matches' => []
            ];
        }

        // Get highest scoring intent
        arsort($scores);
        $topIntent = array_key_first($scores);
        $topScore = $scores[$topIntent];

        return [
            'intent' => $topIntent,
            'confidence' => $topScore,
            'method' => 'rule_based',
            'all_scores' => $scores
        ];
    }

    /**
     * LLM-based intent detection for complex queries
     */
    private function detectIntentWithLLM(string $query): ?array
    {
        try {
            $systemPrompt = "You are an intent classifier. Classify the user query into one of these categories:

            1. 'booking' - User wants to make a reservation, check availability, or book something
            2. 'pricing' - User asks about costs, fees, prices, or payment information  
            3. 'realtime_data' - User needs current/live data like status, inventory, balances
            4. 'lookup' - User wants to find/search specific information or records
            5. 'static_info' - User asks about policies, FAQs, general information

            Also determine if this requires:
            - 'action_needed': true if user wants to DO something (book, check live data, search records)
            - 'action_needed': false if user just wants information/explanation

            Return ONLY JSON: {\"intent\":\"category\", \"confidence\":0.0-1.0, \"action_needed\":true/false, \"reasoning\":\"brief explanation\"}";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query]
            ];

            $response = $this->aiAgent->smartLlmChat($messages);
            
            if ($response && isset($response['message']['content'])) {
                $content = trim($response['message']['content']);
                $result = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    return array_merge($result, ['method' => 'llm_based']);
                }
            }

        } catch (\Exception $e) {
            Log::error('LLM intent detection failed', [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Combine rule-based and LLM results intelligently
     */
    private function combineIntentResults(array $ruleResult, ?array $llmResult): array
    {
        // If LLM failed or wasn't called, use rule-based result
        if (!$llmResult) {
            return $ruleResult;
        }

        $ruleConfidence = $ruleResult['confidence'];
        $llmConfidence = $llmResult['confidence'] ?? 0;

        // If rule-based is high confidence, trust it
        if ($ruleConfidence >= 0.8) {
            return array_merge($ruleResult, [
                'llm_backup' => $llmResult,
                'method' => 'rule_primary'
            ]);
        }

        // If LLM is high confidence, trust it
        if ($llmConfidence >= 0.8) {
            return array_merge($llmResult, [
                'rule_backup' => $ruleResult,
                'method' => 'llm_primary'
            ]);
        }

        // Both moderate confidence - combine them
        if ($ruleResult['intent'] === $llmResult['intent']) {
            // Intents agree - boost confidence
            return array_merge($llmResult, [
                'confidence' => min(($ruleConfidence + $llmConfidence) / 1.5, 1.0),
                'method' => 'combined_agreement',
                'rule_backup' => $ruleResult
            ]);
        }

        // Intents disagree - use higher confidence one
        if ($llmConfidence > $ruleConfidence) {
            return array_merge($llmResult, [
                'method' => 'llm_preferred',
                'rule_backup' => $ruleResult
            ]);
        } else {
            return array_merge($ruleResult, [
                'method' => 'rule_preferred',
                'llm_backup' => $llmResult
            ]);
        }
    }

    /**
     * Check if query matches specific action patterns
     */
    public function matchesActionPattern(string $query, array $actionKeywords = []): float
    {
        $query = strtolower($query);
        $matches = 0;
        $totalKeywords = count($actionKeywords);

        if ($totalKeywords === 0) {
            return 0.0;
        }

        foreach ($actionKeywords as $keyword) {
            if (Str::contains($query, strtolower($keyword))) {
                $matches++;
            }
        }

        return $matches / $totalKeywords;
    }

    /**
     * Determine if query requires real-time action vs static knowledge
     */
    public function requiresAction(array $intentResult): bool
    {
        $intent = $intentResult['intent'] ?? 'static_info';
        $actionNeeded = $intentResult['action_needed'] ?? false;

        // These intents typically require actions
        $actionIntents = ['booking', 'realtime_data', 'lookup', 'pricing'];
        
        return $actionNeeded || in_array($intent, $actionIntents);
    }

    /**
     * Get recommended action types for the detected intent
     */
    public function getRecommendedActionTypes(array $intentResult): array
    {
        $intent = $intentResult['intent'] ?? 'static_info';

        return match ($intent) {
            'booking' => ['availability', 'booking', 'schedule'],
            'pricing' => ['pricing', 'cost', 'rates'],
            'realtime_data' => ['status', 'inventory', 'balance'],
            'lookup' => ['search', 'records', 'custom'],
            'static_info' => [], // Use knowledge base instead
            default => ['search', 'custom']
        };
    }

    /**
     * Extract temporal context (dates, times) from query
     */
    public function extractTemporalContext(string $query): array
    {
        $query = strtolower($query);
        $context = [];

        // Date patterns
        $datePatterns = [
            'today' => date('Y-m-d'),
            'tomorrow' => date('Y-m-d', strtotime('+1 day')),
            'next week' => date('Y-m-d', strtotime('+1 week')),
            'next month' => date('Y-m-d', strtotime('+1 month')),
        ];

        foreach ($datePatterns as $pattern => $date) {
            if (Str::contains($query, $pattern)) {
                $context['date'] = $date;
                $context['date_source'] = $pattern;
                break;
            }
        }

        // Time patterns
        if (preg_match('/(\d{1,2})(:\d{2})?\s*(am|pm)/i', $query, $matches)) {
            $context['time'] = $matches[0];
        }

        // Quantity patterns
        if (preg_match('/(\d+)\s*(guest|people|person|room|night)/i', $query, $matches)) {
            $context['quantity'] = (int)$matches[1];
            $context['quantity_type'] = $matches[2];
        }

        return $context;
    }
}