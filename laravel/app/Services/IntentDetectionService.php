<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use App\Models\Organization;
use App\Models\AdminSetting;

class IntentDetectionService
{
    private AiAgentService $aiAgent;

    private const LEGACY_ROUTE_SIGNAL_KEY_MAP = [
        'product_stock' => 'availability_checks',
        'product_price' => 'pricing_requests',
        'shipping_question' => 'fulfillment_questions',
        'return_policy' => 'policy_questions',
        'store_hours' => 'schedule_questions',
    ];

    private const ROUTE_SIGNAL_KEYWORDS = [
        'availability_checks' => [
            'available', 'availability', 'in stock', 'out of stock', 'stock', 'inventory',
            'have in stock', 'do you have', 'is it available'
        ],
        'pricing_requests' => [
            'price', 'pricing', 'cost', 'how much', 'quote', 'rate', 'charges', 'fee', 'fees'
        ],
        'fulfillment_questions' => [
            'ship', 'shipping', 'deliver', 'delivery', 'send', 'sent', 'dispatch', 'courier', 'eta', 'arrive'
        ],
        'policy_questions' => [
            'return', 'refund', 'exchange', 'replacement', 'cancel', 'cancellation', 'warranty',
            'help', 'support', 'assistance'
        ],
        'schedule_questions' => [
            'hours', 'timing', 'timings', 'open', 'close', 'closing', 'working hours', 'business hours'
        ],
    ];

    private const ROUTE_FILLER_PATTERNS = [
        '/\b(can\s+you|could\s+you|would\s+you|do\s+you|did\s+you|are\s+you|is\s+it|i\s+want\s+to|want\s+to|wanted\s+to|need\s+to|please|kindly)\b/i',
        '/\b(ship|shipping|deliver|delivery|dispatch|courier|eta|arrive|available|availability|in\s+stock|out\s+of\s+stock|stock|inventory|price|pricing|cost|quote|rate|charges|fee|fees)\b/i',
        '/\b(return|refund|exchange|replacement|cancel|cancellation|warranty|policy|policies)\b/i',
        '/\b(what|which|where|when|who|how|much|many|about|for|the|a|an|this|that|these|those|item|product|service)\b/i',
    ];

    private const PRODUCT_CANDIDATE_REJECT_TERMS = [
        'outside', 'internationally', 'international', 'abroad', 'worldwide', 'usa', 'us', 'uk', 'india',
        'shipping', 'delivery', 'return', 'refund', 'policy', 'policies', 'hours', 'timing', 'timings',
        'open', 'close', 'closed', 'store', 'shop', 'customer service', 'support', 'help', 'assistance',
        'request', 'requests', 'status', 'tracking', 'track', 'follow up'
    ];

    private const PRODUCT_CANDIDATE_REJECT_TOKENS = [
        'can', 'could', 'would', 'should', 'it', 'this', 'that', 'these', 'those',
        'be', 'been', 'is', 'are', 'was', 'were', 'ship', 'shipped', 'shipping',
        'deliver', 'delivered', 'delivery', 'send', 'sent'
    ];

    // Rule-based keywords for quick intent detection.
    // IMPORTANT: 'available'/'availability' are NOT booking signals — they indicate stock/status checks
    // (realtime_data). Only classify as 'booking' when the user explicitly wants to MAKE a reserveration
    // or appointment. This avoids mis-classifying product catalog queries as booking requests.
    private const INTENT_KEYWORDS = [
        'booking' => [
            'reserve', 'book a', 'book an', 'booking', 'reservation',
            'appointment', 'can i book', 'can i reserve', 'can i schedule',
            'make a reservation', 'schedule an appointment', 'book a slot',
            'book a table', 'book a room'
        ],
        'pricing' => [
            'price', 'cost', 'fee', 'charge', 'rate', 'pricing', 'how much',
            'tuition', 'fees', 'payment', 'bill', 'invoice', 'expense'
        ],
        'realtime_data' => [
            'available', 'availability', 'check availability', 'in stock', 'out of stock',
            'is it available', 'slot available', 'room available', 'in your store',
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

    // Example phrases for embedding-based intent matching.
    // Booking examples must be appointment/reservation-making phrases ONLY.
    // Availability/stock checking belongs to realtime_data, not booking.
    private const INTENT_EXAMPLES = [
        'booking' => [
            'book an appointment',
            'reserve a slot for me',
            'I want to make a reservation',
            'can I schedule an appointment'
        ],
        'pricing' => [
            'what is the price',
            'how much does it cost',
            'pricing and fees',
            'what is the cost of this product'
        ],
        'realtime_data' => [
            'is this item available',
            'is it in stock',
            'check availability of the product',
            'do you have this in your store',
            'live inventory status'
        ],
        'lookup' => [
            'search for a record',
            'find a product',
            'look up details',
            'show me information about'
        ],
        'static_info' => [
            'return policy information',
            'tell me about your services',
            'general FAQ and help',
            'what is your refund policy'
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
        $rawQuery = trim($query);
        $query = strtolower($rawQuery);

        $settings = $this->getIntentSettings($organizationId);
        $strategy = $settings['intent_strategy'];
        $ruleThreshold = $settings['intent_rule_threshold'];
        $embeddingThreshold = $settings['intent_embedding_threshold'];
        $useLlm = $settings['intent_use_llm'];
        
        Log::info('Intent detection started', [
            'query' => $query,
            'organization_id' => $organizationId
        ]);

        // Step 1: Rule-based quick detection
        $ruleBasedIntent = $this->detectIntentByRules($query, $organizationId);

        if ($strategy === 'rules_only' || $ruleBasedIntent['confidence'] >= $ruleThreshold) {
            $finalIntent = array_merge($ruleBasedIntent, [
                'method' => 'rule_primary'
            ]);
        } else {
            $embeddingIntent = null;
            $llmIntent = null;

            if (in_array($strategy, ['rules_then_embedding', 'hybrid'], true)) {
                $embeddingIntent = $this->detectIntentWithEmbeddings($query);
                if ($embeddingIntent && ($embeddingIntent['confidence'] ?? 0) >= $embeddingThreshold) {
                    $finalIntent = array_merge($embeddingIntent, [
                        'rule_backup' => $ruleBasedIntent,
                        'method' => 'embedding_primary'
                    ]);
                }
            }

            if (!isset($finalIntent) && in_array($strategy, ['rules_then_llm', 'hybrid'], true) && $useLlm) {
                $llmIntent = $this->detectIntentWithLLM($query, $settings);
            }

            if (!isset($finalIntent)) {
                $finalIntent = $this->combineIntentResults($ruleBasedIntent, $llmIntent);
                if ($embeddingIntent) {
                    $finalIntent['embedding_backup'] = $embeddingIntent;
                }
            }
        }
        
        $finalIntent['route_analysis'] = $this->analyzeRoutePlan($rawQuery, $organizationId);

        Log::info('Intent detection completed', [
            'query' => $query,
            'rule_based' => $ruleBasedIntent,
            'llm_based' => $llmIntent ?? null,
            'final_intent' => $finalIntent
        ]);

        return $finalIntent;
    }

    public function analyzeRoutePlan(string $query, int $organizationId): array
    {
        $query = trim($query);
        if ($query === '') {
            return [
                'primary_route' => 'unknown',
                'signals' => [],
                'slots' => [],
                'requires_product_resolution' => false,
                'policy_only' => false,
            ];
        }

        $normalized = mb_strtolower($this->normalizeQueryForSignalDetection($query, $organizationId));
        $signalMatches = [];
        $orgSignals = $this->getOrgRouteSignalKeywords($organizationId);

        foreach (self::ROUTE_SIGNAL_KEYWORDS as $signal => $keywords) {
            $mergedKeywords = array_merge($keywords, $orgSignals[$signal] ?? []);
            $matches = [];

            foreach ($mergedKeywords as $keyword) {
                $keyword = trim(mb_strtolower((string) $keyword));
                if ($keyword !== '' && Str::contains($normalized, $keyword)) {
                    $matches[] = $keyword;
                }
            }

            if (!empty($matches)) {
                $signalMatches[$signal] = array_values(array_unique($matches));
            }
        }

        $signals = array_keys($signalMatches);
        $productCandidate = $this->extractProductCandidate($query, $signals);
        $destination = $this->extractDestinationSlot($query);
        $deliveryDeadline = $this->extractDeliveryDeadlineSlot($query);

        if ($this->shouldTreatFulfillmentAsPolicyOnly($query, $productCandidate, $signals, $destination)) {
            $productCandidate = '';
        }

        if ($this->shouldRejectSupportStatusCandidate($query, $productCandidate, $signals)) {
            $productCandidate = '';
        }

        if ($productCandidate !== '' && !isset($signalMatches['availability_checks']) && !isset($signalMatches['pricing_requests'])) {
            $signalMatches['product_lookup'] = [$productCandidate];
        }

        $signals = array_keys($signalMatches);
        $requiresProductResolution = $productCandidate !== ''
            && !empty(array_intersect($signals, ['fulfillment_questions', 'availability_checks', 'pricing_requests', 'product_lookup']));

        $policyOnly = empty($productCandidate)
            && !empty(array_intersect($signals, ['fulfillment_questions', 'policy_questions', 'schedule_questions']));

        return [
            'primary_route' => $this->determinePrimaryRoute($signals),
            'signals' => $signals,
            'signal_matches' => $signalMatches,
            'slots' => array_filter([
                'product_candidate' => $productCandidate,
                'destination' => $destination,
                'delivery_deadline' => $deliveryDeadline,
            ], static fn ($value) => is_string($value) ? trim($value) !== '' : !empty($value)),
            'requires_product_resolution' => $requiresProductResolution,
            'policy_only' => $policyOnly,
            'policy_topic' => $this->buildPolicyTopic($query, $signals, $destination, $deliveryDeadline),
        ];
    }

    /**
     * Rule-based intent detection using keywords
     */
    private function detectIntentByRules(string $query, int $organizationId = 0): array
    {
        $query = $this->normalizeQueryForSignalDetection($query, $organizationId);
        $scores = [];
        $orgKeywords = $this->getOrgIntentKeywords($organizationId);
        
        foreach (self::INTENT_KEYWORDS as $intent => $keywords) {
            $mergedKeywords = array_merge($keywords, $orgKeywords[$intent] ?? []);
            $score = 0;
            $matches = 0;
            
            foreach ($mergedKeywords as $keyword) {
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

    private function getOrgIntentKeywords(int $organizationId): array
    {
        if (!$organizationId) {
            return [];
        }

        $org = Organization::find($organizationId);
        if (!$org) {
            return [];
        }

        $keywords = $org->settings['intent_keywords'] ?? [];
        if (!is_array($keywords)) {
            return [];
        }

        return $keywords;
    }

    private function getOrgRouteSignalKeywords(int $organizationId): array
    {
        if (!$organizationId) {
            return [];
        }

        $org = Organization::find($organizationId);
        if (!$org) {
            return [];
        }

        $keywords = $org->settings['route_signal_keywords'] ?? [];
        if (!is_array($keywords)) {
            return [];
        }

        return $this->normalizeRouteSignalKeywords($keywords);
    }

    private function normalizeQueryForSignalDetection(string $query, int $organizationId): string
    {
        $normalized = trim(mb_strtolower($query));
        if ($normalized === '') {
            return '';
        }

        $map = $this->getQueryNormalizationMap($organizationId);
        if (empty($map)) {
            return $normalized;
        }

        uksort($map, static function ($left, $right) {
            return mb_strlen((string) $right) <=> mb_strlen((string) $left);
        });

        foreach ($map as $from => $to) {
            $from = trim((string) $from);
            $to = trim((string) $to);

            if ($from === '' || $to === '') {
                continue;
            }

            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($from, '/') . '(?![\p{L}\p{N}])/iu';
            $normalized = preg_replace($pattern, $to, $normalized) ?? $normalized;
        }

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }

    private function getQueryNormalizationMap(int $organizationId): array
    {
        $map = [];

        $this->mergeQueryNormalizationMap($map, AdminSetting::get('global_query_translation_map', []), false, true);
        $this->mergeQueryNormalizationMap($map, AdminSetting::get('global_query_alias_map', []), true, false);

        if ($organizationId > 0) {
            $org = Organization::find($organizationId);
            if ($org) {
                $settings = is_array($org->settings ?? null) ? $org->settings : [];
                $this->mergeQueryNormalizationMap($map, $settings['query_translation_map'] ?? [], false, true);
                $this->mergeQueryNormalizationMap($map, $settings['query_alias_map'] ?? [], true, false);
            }
        }

        return $map;
    }

    private function mergeQueryNormalizationMap(array &$map, $configured, bool $forceAliasMode, bool $allowLegacyAliasGroups): void
    {
        foreach ($this->queryNormalizationRows($configured) as $row) {
            $line = trim((string) $row);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/=>|=|\|/', $line, 2) ?: [];
            if (count($parts) < 2) {
                continue;
            }

            $from = $this->normalizeQueryNormalizationValue((string) ($parts[0] ?? ''));
            $to = $this->normalizeQueryNormalizationValue((string) ($parts[1] ?? ''));
            if ($from === '' || $to === '') {
                continue;
            }

            $aliases = array_values(array_unique(array_filter(array_map(function ($value) {
                return $this->normalizeQueryNormalizationValue((string) $value);
            }, preg_split('/,/', $to) ?: []))));

            $shouldTreatAsAliasGroup = $forceAliasMode || ($allowLegacyAliasGroups && count($aliases) > 1);
            if ($shouldTreatAsAliasGroup) {
                $map[$from] = $from;
                foreach ($aliases as $alias) {
                    if ($alias !== '') {
                        $map[$alias] = $from;
                    }
                }
                continue;
            }

            $map[$from] = $aliases[0] ?? $to;
        }
    }

    private function queryNormalizationRows($configured): array
    {
        if (is_string($configured)) {
            return preg_split('/\r\n|\r|\n/', $configured) ?: [];
        }

        if (!is_array($configured)) {
            return [];
        }

        $rows = [];
        foreach ($configured as $from => $to) {
            if (is_int($from) && is_string($to)) {
                $rows[] = $to;
                continue;
            }

            $rows[] = (string) $from . ' = ' . (string) $to;
        }

        return $rows;
    }

    private function normalizeQueryNormalizationValue(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function determinePrimaryRoute(array $signals): string
    {
        foreach (['availability_checks', 'pricing_requests', 'fulfillment_questions', 'policy_questions', 'schedule_questions', 'product_lookup'] as $priority) {
            if (in_array($priority, $signals, true)) {
                return $priority;
            }
        }

        return 'unknown';
    }

    private function extractProductCandidate(string $query, array $signals = []): string
    {
        $query = trim($query);
        if ($query === '') {
            return '';
        }

        if (preg_match('/["“](.+?)["”]/u', $query, $matches)) {
            return $this->sanitizeProductCandidate($matches[1]);
        }

        if (preg_match('/\b(?:can|could|would|should)\s+(.+?)\s+be\s+(?:shipped|delivered|sent)\b/i', $query, $matches)) {
            $candidate = $this->sanitizeProductCandidate($matches[1]);
            if ($candidate !== '' && !$this->looksLikeQuestionFragmentProductCandidate($candidate)) {
                return $candidate;
            }
        }

        if (in_array('fulfillment_questions', $signals, true)
            && preg_match('/\b(?:ship|deliver|send)\s+(.+?)(?:\s+to\b|\s+by\b|[?.!,]|$)/i', $query, $matches)) {
            $candidate = $this->sanitizeProductCandidate($matches[1]);
            if ($candidate !== '' && !$this->looksLikeQuestionFragmentProductCandidate($candidate)) {
                return $candidate;
            }
        }

        $candidate = $query;
        $candidate = preg_replace('/\bto\s+[\p{L}][\p{L}\s.-]{1,40}(?=\s+by\b|\s+before\b|\s+on\b|\s+tomorrow\b|\s+today\b|\s+next\b|\s+this\b|[?.!,]|$)/iu', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/\bby\s+[\p{L}\d][\p{L}\d\s,\/-]{0,30}(?=[?.!,]|$)/iu', ' ', $candidate) ?? $candidate;

        foreach (self::ROUTE_FILLER_PATTERNS as $pattern) {
            $candidate = preg_replace($pattern, ' ', $candidate) ?? $candidate;
        }

        $candidate = $this->sanitizeProductCandidate($candidate);

        if ($candidate === '' || $this->looksLikeQuestionFragmentProductCandidate($candidate)) {
            return '';
        }

        return $candidate;
    }

    private function looksLikeQuestionFragmentProductCandidate(string $candidate): bool
    {
        $normalized = mb_strtolower(trim($candidate));
        if ($normalized === '') {
            return false;
        }

        if ((bool) preg_match('/\b(?:how\s+long|how\s+soon|long\s+does\s+it\s+take|does\s+it\s+take|when\s+will\s+(?:it|this|that|these|those))\b/u', $normalized)) {
            return true;
        }

        return str_word_count($normalized) <= 5
            && (bool) preg_match('/\b(?:do|does|did|can|could|would|should|will|is|are|was|were|has|have|had)\b/u', $normalized)
            && (bool) preg_match('/\b(?:it|this|that|these|those)\b/u', $normalized);
    }

    private function sanitizeProductCandidate(string $candidate): string
    {
        $candidate = trim(strip_tags($candidate));
        $candidate = preg_replace('/[[:punct:]]+/u', ' ', $candidate) ?? $candidate;
        $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate));

        if ($candidate === '') {
            return '';
        }

        $lower = mb_strtolower($candidate);
        foreach (self::PRODUCT_CANDIDATE_REJECT_TERMS as $term) {
            if ($lower === $term || Str::startsWith($lower, $term . ' ') || Str::endsWith($lower, ' ' . $term)) {
                return '';
            }
        }

        $tokens = array_values(array_filter(explode(' ', $lower), static fn ($token) => $token !== ''));
        $meaningfulTokens = array_values(array_filter($tokens, function ($token) {
            return !in_array($token, ['to', 'by', 'for', 'with', 'outside', 'from'], true);
        }));

        $nonRejectTokens = array_values(array_filter($meaningfulTokens, function ($token) {
            return !in_array($token, self::PRODUCT_CANDIDATE_REJECT_TOKENS, true);
        }));

        if (empty($meaningfulTokens)) {
            return '';
        }

        if (empty($nonRejectTokens)) {
            return '';
        }

        if (count($meaningfulTokens) === 1 && in_array($meaningfulTokens[0], self::PRODUCT_CANDIDATE_REJECT_TERMS, true)) {
            return '';
        }

        if (preg_match('/\b(?:ship|shipped|shipping|deliver|delivered|delivery|send|sent)\b/i', $candidate)
            && count($nonRejectTokens) <= 1) {
            return '';
        }

        return trim($candidate);
    }

    private function shouldTreatFulfillmentAsPolicyOnly(string $query, string $productCandidate, array $signals, string $destination): bool
    {
        if ($productCandidate === '' || !in_array('fulfillment_questions', $signals, true)) {
            return false;
        }

        if (!preg_match('/\b(?:ship|shipping|deliver|delivery|send|sent|dispatch|courier)\b/i', $query)) {
            return false;
        }

        if (!preg_match('/\bfrom\s+[\p{L}][\p{L}\s.-]{1,40}\s+to\s+[\p{L}][\p{L}\s.-]{1,40}\b/iu', $query) && $destination === '') {
            return false;
        }

        if (preg_match('/["“][^"”]+["”]/u', $query)) {
            return false;
        }

        $normalizedQuery = mb_strtolower($query);
        $normalizedCandidate = mb_strtolower($productCandidate);
        $looksLikeRequestDescriptor = (bool) preg_match('/^(?:a|an|any|some|my|our|one|two|three|\d+|old|new|used|custom)\b/i', $productCandidate);
        $hasRequestPhrasing = Str::contains($normalizedQuery, [
            'want to send',
            'wanted to send',
            'need to send',
            'can i send',
            'send my',
            'ship my',
            'deliver my',
        ]);

        if (!$looksLikeRequestDescriptor && !$hasRequestPhrasing) {
            return false;
        }

        return preg_match('/\bfrom\s+[\p{L}]/iu', $normalizedCandidate) === 1 || str_word_count($productCandidate) >= 3;
    }

    private function shouldRejectSupportStatusCandidate(string $query, string $productCandidate, array $signals): bool
    {
        if ($productCandidate === '') {
            return false;
        }

        $normalizedCandidate = mb_strtolower(trim($productCandidate));
        $normalizedQuery = mb_strtolower(trim($query));

        if ($normalizedCandidate === '') {
            return false;
        }

        $supportLikeSignals = array_intersect($signals, ['policy_questions', 'fulfillment_questions']);
        if (empty($supportLikeSignals)) {
            return false;
        }

        if (preg_match('/\b(help|support|assistance|request|status|tracking|track|follow\s+up|checking\s+up)\b/u', $normalizedCandidate)) {
            return true;
        }

        if (preg_match('/\b(return|refund|exchange|replacement|cancel|warranty|help|support|assistance)\b/u', $normalizedQuery)
            && preg_match('/\b(request|status|help|support|assistance)\b/u', $normalizedCandidate)) {
            return true;
        }

        return false;
    }

    private function extractDestinationSlot(string $query): string
    {
        if (preg_match('/\bto\s+([\p{L}][\p{L}\s.-]{1,40}?)(?=\s+by\b|\s+before\b|\s+on\b|\s+tomorrow\b|\s+today\b|\s+next\b|\s+this\b|[?.!,]|$)/iu', $query, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function extractDeliveryDeadlineSlot(string $query): string
    {
        if (preg_match('/\bby\s+([\p{L}\d][\p{L}\d\s,\/-]{0,30}?)(?=[?.!,]|$)/iu', $query, $matches)) {
            return trim($matches[1]);
        }

        if (preg_match('/\b(today|tomorrow|tonight|monday|tuesday|wednesday|thursday|friday|saturday|sunday|next\s+week|next\s+month)\b/i', $query, $matches)) {
            return trim($matches[1]);
        }

        return '';
    }

    private function buildPolicyTopic(string $query, array $signals, string $destination, string $deliveryDeadline): string
    {
        $normalized = mb_strtolower($query);

        if (in_array('fulfillment_questions', $signals, true)) {
            if ((bool) preg_match('/\b(outside\s+usa|outside\s+the\s+usa|outside\s+us|outside\s+the\s+us|international)\b/i', $normalized)) {
                return 'whether we ship outside the USA';
            }

            if ($destination !== '' && $deliveryDeadline !== '') {
                return 'whether we can ship to ' . $destination . ' by ' . $deliveryDeadline;
            }

            if ($destination !== '') {
                return 'whether we can ship to ' . $destination;
            }

            if ($deliveryDeadline !== '') {
                return 'whether we can deliver by ' . $deliveryDeadline;
            }

            return 'shipping';
        }

        if (in_array('policy_questions', $signals, true)) {
            return 'our return, refund, or exchange policy';
        }

        if (str_contains($normalized, 'warranty') || str_contains($normalized, 'guarantee')) {
            return 'our warranty or guarantee policy';
        }

        if (in_array('schedule_questions', $signals, true)) {
            return 'our store hours';
        }

        return 'that';
    }

    private function normalizeRouteSignalKeywords(array $keywords): array
    {
        $normalized = [];

        foreach ($keywords as $key => $values) {
            $targetKey = self::LEGACY_ROUTE_SIGNAL_KEY_MAP[$key] ?? $key;
            if (!isset(self::ROUTE_SIGNAL_KEYWORDS[$targetKey])) {
                continue;
            }

            $current = $normalized[$targetKey] ?? [];
            $incoming = is_array($values) ? $values : [];
            $normalized[$targetKey] = array_values(array_unique(array_merge($current, $incoming)));
        }

        foreach (array_keys(self::ROUTE_SIGNAL_KEYWORDS) as $key) {
            $normalized[$key] = array_values(array_filter($normalized[$key] ?? []));
        }

        return $normalized;
    }

    /**
     * LLM-based intent detection for complex queries
     */
    private function detectIntentWithLLM(string $query, array $settings = []): ?array
    {
        try {
            // Use Vast.ai GPU with llama3.1:8b for faster intent detection (3-4s vs 26s local)
            $intentModel = 'llama3.1:8b';
            $maxTokens = $settings['intent_llm_max_tokens'] ?? 64;
            $temperature = $settings['intent_llm_temperature'] ?? 0.1;
            $topP = $settings['intent_llm_top_p'] ?? 0.85;
            $repeatPenalty = $settings['intent_llm_repeat_penalty'] ?? 1.05;

            $systemPrompt = "You are an intent classifier. Choose exactly one intent: booking, pricing, realtime_data, lookup, or static_info. Rules: (1) Use 'booking' ONLY when the user explicitly wants to MAKE or SCHEDULE an appointment/reservation — NOT for stock or product availability checks. (2) Use 'realtime_data' when the user is checking availability, stock status, or whether a product/service exists. (3) 'Is X available?' about a product = realtime_data. 'Book an appointment' = booking. Mark action_needed=true only when the user wants to do something (book, check live data, search/lookup). Respond with JSON only: {\\\"intent\\\":\\\"...\\\", \\\"confidence\\\":0-1, \\\"action_needed\\\":true/false, \\\"reasoning\\\":\\\"<=12 words\\\"}. No prose.";

            $options = [
                'num_predict' => $maxTokens,
                'temperature' => $temperature,
                'top_p' => $topP,
                'repeat_penalty' => $repeatPenalty,
                'use_vastai' => true,  // Force Vast.ai GPU for speed
            ];

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $query]
            ];

            $response = $this->aiAgent->smartLlmChat($messages, $intentModel, null, null, $options);
            
            if ($response && isset($response['message']['content'])) {
                $content = trim($response['message']['content']);
                $content = trim($content, "\n\r\t \"");
                $result = json_decode($content, true);
                
                if (json_last_error() === JSON_ERROR_NONE && is_array($result)) {
                    return array_merge($result, ['method' => 'llm_based']);
                }

                $parsed = $this->parseLooseIntentResponse($content);
                if ($parsed) {
                    return array_merge($parsed, ['method' => 'llm_based']);
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

    private function parseLooseIntentResponse(string $content): ?array
    {
        $allowedIntents = ['booking', 'pricing', 'realtime_data', 'lookup', 'static_info'];

        $intent = null;
        if (preg_match('/intent\s*[:=]\s*([a-z_]+)/i', $content, $match)) {
            $intent = strtolower($match[1]);
        }
        if (!$intent || !in_array($intent, $allowedIntents, true)) {
            return null;
        }

        $confidence = null;
        if (preg_match('/confidence\s*[:=]\s*([0-9]+(?:\.[0-9]+)?)/i', $content, $match)) {
            $confidence = (float) $match[1];
            if ($confidence > 1) {
                $confidence = $confidence / 100.0;
            }
            $confidence = max(0.0, min(1.0, $confidence));
        }

        $actionNeeded = null;
        if (preg_match('/action\s*needed\s*[:=]\s*(true|false)/i', $content, $match)) {
            $actionNeeded = strtolower($match[1]) === 'true';
        }

        if ($actionNeeded === null) {
            $actionNeeded = in_array($intent, ['booking', 'realtime_data', 'lookup', 'pricing'], true);
        }

        $reasoning = null;
        if (preg_match('/reasoning\s*[:=]\s*([^\n\r\}]+)/i', $content, $match)) {
            $reasoning = trim($match[1]);
        }

        return [
            'intent' => $intent,
            'confidence' => $confidence ?? 0.6,
            'action_needed' => $actionNeeded,
            'reasoning' => $reasoning ?? 'llm_loose_parse',
        ];
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
     * Embedding-based intent detection for fast, stable routing
     */
    private function detectIntentWithEmbeddings(string $query): ?array
    {
        try {
            $queryEmbedding = $this->aiAgent->embed($query);
            if (!$queryEmbedding || !is_array($queryEmbedding)) {
                return null;
            }

            $intentEmbeddings = $this->getCachedIntentEmbeddings();
            if (empty($intentEmbeddings)) {
                return null;
            }

            $bestIntent = null;
            $bestScore = 0.0;

            foreach ($intentEmbeddings as $intent => $embeddings) {
                foreach ($embeddings as $embedding) {
                    $score = $this->calculateCosineSimilarity($queryEmbedding, $embedding);
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIntent = $intent;
                    }
                }
            }

            if (!$bestIntent) {
                return null;
            }

            return [
                'intent' => $bestIntent,
                'confidence' => $bestScore,
                'action_needed' => in_array($bestIntent, ['booking', 'realtime_data', 'lookup', 'pricing'], true),
                'reasoning' => 'embedding_match',
                'method' => 'embedding'
            ];
        } catch (\Exception $e) {
            Log::warning('Embedding intent detection failed', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function getCachedIntentEmbeddings(): array
    {
        return Cache::remember('intent_embeddings_v2', 3600, function () {
            $intentEmbeddings = [];
            $allTexts = [];
            $intentMap = [];

            foreach (self::INTENT_EXAMPLES as $intent => $examples) {
                foreach ($examples as $example) {
                    $intentMap[] = $intent;
                    $allTexts[] = $example;
                }
            }

            $embeddings = $this->aiAgent->embedBatch($allTexts);
            if (!$embeddings || !is_array($embeddings)) {
                return [];
            }

            foreach ($embeddings as $index => $embedding) {
                $intent = $intentMap[$index] ?? null;
                if (!$intent || !$embedding || !is_array($embedding)) {
                    continue;
                }
                $intentEmbeddings[$intent][] = $embedding;
            }

            return $intentEmbeddings;
        });
    }

    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        $length = min(count($vectorA), count($vectorB));
        for ($i = 0; $i < $length; $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $normA += $vectorA[$i] * $vectorA[$i];
            $normB += $vectorB[$i] * $vectorB[$i];
        }

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / (sqrt($normA) * sqrt($normB));
    }

    private function getIntentSettings(int $organizationId): array
    {
        $org = Organization::find($organizationId);
        $settings = $org?->settings ?? [];

        $getGlobal = function (string $key, $default = null) {
            return class_exists(AdminSetting::class)
                ? AdminSetting::get($key, $default)
                : $default;
        };

        return [
            'intent_strategy' => $settings['intent_strategy']
                ?? $getGlobal('intent_strategy', 'hybrid'),
            'intent_rule_threshold' => (float) ($settings['intent_rule_threshold']
                ?? $getGlobal('intent_rule_threshold', 0.8)),
            'intent_embedding_threshold' => (float) ($settings['intent_embedding_threshold']
                ?? $getGlobal('intent_embedding_threshold', 0.75)),
            'intent_use_llm' => (bool) ($settings['intent_use_llm']
                ?? $getGlobal('intent_use_llm', true)),
            'intent_llm_model' => $settings['intent_llm_model']
                ?? $getGlobal('intent_llm_model', null),
            'intent_llm_max_tokens' => (int) ($settings['intent_llm_max_tokens']
                ?? $getGlobal('intent_llm_max_tokens', 64)),
            'intent_llm_temperature' => (float) ($settings['intent_llm_temperature']
                ?? $getGlobal('intent_llm_temperature', 0.1)),
            'intent_llm_top_p' => (float) ($settings['intent_llm_top_p']
                ?? $getGlobal('intent_llm_top_p', 0.85)),
            'intent_llm_repeat_penalty' => (float) ($settings['intent_llm_repeat_penalty']
                ?? $getGlobal('intent_llm_repeat_penalty', 1.05)),
        ];
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