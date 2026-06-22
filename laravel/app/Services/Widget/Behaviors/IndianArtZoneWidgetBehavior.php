<?php

namespace App\Services\Widget\Behaviors;

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Models\OrganizationFaq;
use App\Services\Widget\OrganizationWidgetBehavior;

class IndianArtZoneWidgetBehavior implements OrganizationWidgetBehavior
{
    private const ORGANIZATION_SLUG = 'indian-art-zone';
    private const SELLER_MATCH_SOURCE = 'organization_behavior:indian-art-zone:seller_onboarding';

    public function supports(Organization $organization): bool
    {
        return strtolower(trim((string) $organization->slug)) === self::ORGANIZATION_SLUG;
    }

    public function preferredFaqMatch(Organization $organization, string $message): ?array
    {
        if (!$this->isArtistSellingIntent($message)) {
            return null;
        }

        $faqs = OrganizationFaq::query()
            ->where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get(['id', 'question', 'answer', 'follow_up', 'keywords', 'category', 'updated_at']);

        $best = null;
        foreach ($faqs as $faq) {
            $question = $this->normalize((string) $faq->question);
            $answer = $this->normalize((string) $faq->answer);
            $keywords = $this->normalize((string) ($faq->keywords ?? ''));
            $category = $this->normalize((string) ($faq->category ?? ''));
            $candidate = trim("{$question} {$answer} {$keywords} {$category}");

            if ($candidate === '' || !preg_match('/\b(sell|sale|seller|selling)\b/u', $candidate)) {
                continue;
            }

            $isArtistFaq = str_contains($category, 'artist')
                || preg_match('/\b(artist|art\s*work|artwork|painting|paintings|profile|upload|submission|publishing)\b/u', $candidate);
            if (!$isArtistFaq) {
                continue;
            }

            $score = 2.6;
            $score += str_contains($category, 'artist') ? 1.1 : 0.0;
            $score += preg_match('/\bsell\b.*\bpainting|painting.*\bsell\b/u', $question) ? 1.4 : 0.0;
            $score += preg_match('/\b(upload|profile|review|submission|publishing)\b/u', $candidate) ? 1.0 : 0.0;
            $score += preg_match('/\bwhatsapp|support\b/u', $candidate) ? 0.7 : 0.0;

            if ($best !== null
                && ($score < $best['score']
                    || ($score === $best['score'] && (string) $faq->updated_at <= (string) $best['updated_at']))) {
                continue;
            }

            $rawAnswer = trim((string) $faq->answer);
            if ($rawAnswer === '') {
                continue;
            }

            $best = [
                'score' => $score,
                'updated_at' => (string) $faq->updated_at,
                'response' => $rawAnswer,
                'payload' => [
                    'item_id' => 'faq_' . $faq->id,
                    'data_type' => 'faq',
                    'type' => 'faq',
                    'title' => $faq->question,
                    'content' => $rawAnswer,
                    'follow_up' => $faq->follow_up,
                    'category' => $faq->category,
                    'keywords' => $faq->keywords,
                ],
                'match_debug' => [
                    'organization_behavior' => self::ORGANIZATION_SLUG,
                    'seller_onboarding_intent' => true,
                ],
            ];
        }

        if ($best === null) {
            return null;
        }

        unset($best['updated_at']);
        $best['match_source'] = self::SELLER_MATCH_SOURCE;

        return $best;
    }

    public function isRelatedFollowUp(
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        array $previousContextPayloads,
        ?array $pendingFollowUpState
    ): bool {
        if (!$this->previousTurnHasSellerAnchor(
            $lastUserMessage,
            $lastAssistantMessage,
            $previousContextPayloads,
            $pendingFollowUpState
        )) {
            return false;
        }

        return $this->isAffirmative($message) || $this->isSellerOperationalFollowUp($message);
    }

    public function enrichFollowUpSearchQuery(
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage,
        bool $isAffirmativeFollowUp
    ): ?string {
        $buyerCatalogQuery = $this->buildBuyerCatalogFollowUpSearchQuery($message, $lastUserMessage, $lastAssistantMessage);
        if ($buyerCatalogQuery !== null) {
            return $buyerCatalogQuery;
        }

        if (!$this->isSellerOperationalFollowUp($message)
            && !($isAffirmativeFollowUp && $this->previousTurnHasSellerAnchor($lastUserMessage, $lastAssistantMessage))) {
            return null;
        }

        return trim('sell painting artist profile upload artwork IndianArtZone seller guide ' . $message);
    }

    public function shouldSkipFaqPolish(?array $match): bool
    {
        return is_array($match) && (($match['match_source'] ?? '') === self::SELLER_MATCH_SOURCE);
    }

    public function shouldSuppressPromotionResponse(string $message): bool
    {
        return $this->isArtistSellingIntent($message);
    }

    public function answerFamilyLabels(string $text): array
    {
        $query = $this->normalize($text);
        $hasSellerContext = (bool) preg_match('/\b(artist|artists|sell|sale|selling|seller)\b/u', $query);
        $hasArtworkContext = (bool) preg_match('/\b(painting|paintings|art\s+work|artwork|artworks|canvas|art)\b/u', $query);
        $hasOnboardingContext = (bool) preg_match('/\bartist\s+profile\b|\b(upload|submit|submission|publish|publishing|post|posting|add)\b.*\b(painting|paintings|art\s+work|artwork|artworks)\b/u', $query);

        return (($hasSellerContext && $hasArtworkContext) || $hasOnboardingContext)
            ? ['artist_selling']
            : [];
    }

    public function catalogBudgetResponse(
        Organization $organization,
        string $message,
        string $searchQuery,
        array $orderedResults = []
    ): ?string {
        $combined = trim($message . ' ' . $searchQuery);
        if (!$this->isPaintingBuyerBudgetIntent($combined)) {
            return null;
        }

        $budget = $this->extractInrBudget($combined);
        if ($budget === null) {
            return null;
        }

        $themeTerms = $this->extractPaintingThemeTerms($combined);
        $matches = $this->findCatalogPaintingsForBudget($organization, $themeTerms, $budget);
        $themeLabel = $this->formatThemeLabel($themeTerms);
        $budgetLabel = '₹' . number_format($budget, 0, '.', ',');

        if (!empty($matches['within_budget'])) {
            $lines = [
                "I found these {$themeLabel}painting options at or below {$budgetLabel}:",
            ];

            foreach (array_slice($matches['within_budget'], 0, 3) as $item) {
                $lines[] = $this->formatCatalogBudgetLine($item);
            }

            return implode("\n", $lines);
        }

        if (!empty($matches['nearest'])) {
            $startPrice = '₹' . number_format((float) $matches['nearest'][0]['price'], 0, '.', ',');
            $lines = [
                "I couldn't find a {$themeLabel}painting at or below {$budgetLabel} in our synced catalog. The closest matching options I found start from {$startPrice}:",
            ];

            foreach (array_slice($matches['nearest'], 0, 3) as $item) {
                $lines[] = $this->formatCatalogBudgetLine($item);
            }

            return implode("\n", $lines);
        }

        return "I couldn't find a {$themeLabel}painting at or below {$budgetLabel} in our synced catalog. Please contact us at sales@indianartzone.com or +91-9585759933 so we can confirm any lower-budget availability.";
    }

    public function isArtistSellingIntent(string $message): bool
    {
        $query = $this->normalize($message);
        if ($query === '' || preg_match('/\b(buy|purchase|order|checkout|cart)\b/u', $query)) {
            return false;
        }

        if (preg_match('/\b(sketch|paper|print|prints|drawing|drawings)\b/u', $query)) {
            return false;
        }

        $hasSaleVerb = (bool) preg_match('/\b(sell|sale|seller|selling|list|listing|register|registration|artist|profile|upload|submit|submission|publish|publishing|post|posting|add)\b/u', $query);
        $hasArtworkNoun = (bool) preg_match('/\b(painting|paintings|art\s+work|artwork|artworks|canvas|art)\b/u', $query);
        $hasArtistOnboardingIntent = (bool) preg_match(
            '/\b(?:create|make|open|register|set\s+up|setup|upload|submit|post|add)\b.*\bartist\s+profile\b|\bartist\s+profile\b.*\b(?:create|make|open|register|set\s+up|setup|upload|submit|post|add)\b/u',
            $query
        );

        return ($hasSaleVerb && $hasArtworkNoun)
            || $hasArtistOnboardingIntent
            || (bool) preg_match('/\bmy\s+painting\s+sell\b|\bpainting\s+sell\b|\bsell\s+my\s+painting\b/u', $query);
    }

    private function buildBuyerCatalogFollowUpSearchQuery(
        string $message,
        ?string $lastUserMessage,
        ?string $lastAssistantMessage
    ): ?string {
        $current = $this->normalize($message);
        $previous = $this->normalize(trim((string) $lastUserMessage . ' ' . (string) $lastAssistantMessage));

        if (!$this->hasBudgetSignal($current)
            || !$this->hasPaintingBuyerCatalogContext($previous)) {
            return null;
        }

        $parts = ['painting'];
        foreach ($this->extractPaintingThemeTerms($previous . ' ' . $current) as $term) {
            $parts[] = $term;
        }
        $parts[] = 'catalog';
        $parts[] = 'price';
        $parts[] = $message;

        return trim(implode(' ', array_unique(array_filter($parts))));
    }

    private function isPaintingBuyerBudgetIntent(string $text): bool
    {
        $query = $this->normalize($text);
        if ($query === '' || $this->isArtistSellingIntent($query)) {
            return false;
        }

        $hasBuyerPaintingContext = (bool) preg_match('/\b(painting|paintings|artwork|artworks|canvas|art)\b/u', $query)
            && (bool) preg_match('/\b(need|want|looking|buy|purchase|show|find|option|options|theme|category|style|devotional|contemporary|modern|abstract)\b/u', $query);
        return $hasBuyerPaintingContext && $this->hasBudgetSignal($query);
    }

    private function hasBudgetSignal(string $text): bool
    {
        $query = $this->normalize($text);

        return (bool) preg_match('/\b(budget|under|below|within|upto|up\s+to|less\s+than|rs|inr)\b/u', $query)
            && (bool) preg_match('/\b\d{3,}\b/u', $query);
    }

    private function hasPaintingBuyerCatalogContext(string $text): bool
    {
        $query = $this->normalize($text);
        if ($query === '' || $this->isArtistSellingIntent($query)) {
            return false;
        }

        return (bool) preg_match('/\b(painting|paintings|artwork|artworks|canvas|art)\b/u', $query)
            && (bool) preg_match('/\b(need|want|looking|buy|purchase|show|find|option|options|theme|category|style|devotional|contemporary|modern|abstract)\b/u', $query);
    }

    private function extractInrBudget(string $text): ?float
    {
        $normalized = mb_strtolower($text);

        if (preg_match('/(?:₹|rs\.?|inr)\s*([0-9][0-9,]*(?:\.\d{1,2})?)/iu', $normalized, $match)
            || preg_match('/([0-9][0-9,]*(?:\.\d{1,2})?)\s*(?:₹|rs\.?|inr)\b/iu', $normalized, $match)
            || preg_match('/\b(?:budget|under|below|within|upto|up\s+to|less\s+than)\D{0,12}([0-9][0-9,]*(?:\.\d{1,2})?)/iu', $normalized, $match)) {
            $value = (float) str_replace(',', '', (string) ($match[1] ?? ''));
            return $value > 0 ? $value : null;
        }

        return null;
    }

    private function extractPaintingThemeTerms(string $text): array
    {
        $query = $this->normalize($text);
        $themes = [];

        foreach (['contemporary', 'devotional', 'modern', 'abstract', 'landscape', 'floral', 'religious', 'spiritual'] as $term) {
            if (preg_match('/\b' . preg_quote($term, '/') . '\b/u', $query)) {
                $themes[] = $term;
            }
        }

        return array_values(array_unique($themes));
    }

    /**
     * @return array{within_budget:array<int,array<string,mixed>>,nearest:array<int,array<string,mixed>>}
     */
    private function findCatalogPaintingsForBudget(Organization $organization, array $themeTerms, float $budget): array
    {
        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'product')
            ->where(function ($where) {
                $where->where('name', 'like', '%painting%')
                    ->orWhere('description', 'like', '%painting%')
                    ->orWhere('content', 'like', '%painting%')
                    ->orWhere('name', 'like', '%art%')
                    ->orWhere('description', 'like', '%art%')
                    ->orWhere('content', 'like', '%art%');
            })
            ->orderByDesc('updated_at')
            ->limit(3000)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $haystack = $this->normalize(implode(' ', array_filter([
                (string) $row->name,
                (string) $row->description,
                (string) $row->content,
                (string) data_get($row->metadata, 'meta_description', ''),
                (string) data_get($row->metadata, 'category', ''),
            ])));

            if (!empty($themeTerms)) {
                $matchedTheme = false;
                foreach ($themeTerms as $theme) {
                    if (str_contains($haystack, $theme)) {
                        $matchedTheme = true;
                        break;
                    }
                }

                if (!$matchedTheme) {
                    continue;
                }
            }

            $price = $this->extractCatalogPrice($row);
            if ($price === null) {
                continue;
            }

            $items[] = [
                'title' => trim((string) $row->name) ?: 'Catalog Item',
                'price' => $price,
                'url' => $this->catalogUrl($row),
            ];
        }

        usort($items, static fn (array $a, array $b): int => ((float) $a['price']) <=> ((float) $b['price']));

        return [
            'within_budget' => array_values(array_filter($items, static fn (array $item): bool => (float) $item['price'] <= $budget)),
            'nearest' => array_values(array_filter($items, static fn (array $item): bool => (float) $item['price'] > $budget)),
        ];
    }

    private function extractCatalogPrice(OrganizationData $row): ?float
    {
        $metadata = is_array($row->metadata ?? null) ? $row->metadata : [];
        $candidates = [
            $metadata['price'] ?? null,
            $metadata['sale_price'] ?? null,
            $metadata['special_price'] ?? null,
            $metadata['regular_price'] ?? null,
            $metadata['price_inr'] ?? null,
        ];

        $content = (string) ($row->content ?? '');
        if ($content !== ''
            && preg_match('/(?:^|\n)\s*(?:Price|Retail\s*Price|Sale\s*Price|selling_price|price_inr)\s*[=:]\s*["\']?(\d[\d,\.]*)/im', $content, $match)) {
            $candidates[] = $match[1];
        }

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $normalized = preg_replace('/[^0-9.]/', '', (string) $candidate) ?? '';
            if ($normalized === '') {
                continue;
            }

            $value = (float) $normalized;
            if ($value > 0) {
                return $value;
            }
        }

        return null;
    }

    private function catalogUrl(OrganizationData $row): string
    {
        foreach (['product_url', 'url', 'link', 'website_url'] as $key) {
            $value = data_get($row->metadata, $key);
            if (is_string($value) && preg_match('/^https?:\/\//i', $value)) {
                return $value;
            }
        }

        return '';
    }

    private function formatThemeLabel(array $themeTerms): string
    {
        if (empty($themeTerms)) {
            return '';
        }

        if (count($themeTerms) === 1) {
            return $themeTerms[0] . ' ';
        }

        $last = array_pop($themeTerms);
        return implode(', ', $themeTerms) . ' or ' . $last . ' ';
    }

    private function formatCatalogBudgetLine(array $item): string
    {
        $line = '- ' . trim((string) ($item['title'] ?? 'Catalog Item'))
            . ': ₹' . number_format((float) ($item['price'] ?? 0), 0, '.', ',');

        $url = trim((string) ($item['url'] ?? ''));
        if ($url !== '') {
            $line .= ' - ' . $url;
        }

        return $line;
    }

    private function isSellerOperationalFollowUp(string $message): bool
    {
        $query = $this->normalize($message);
        if ($query === '') {
            return false;
        }

        return $this->isArtistSellingIntent($query)
            || (bool) preg_match('/\b(upload|uploads|register|registration|artist\s+profile|profile|submit|submission|publish|publishing|post|posting|add|list|listing|seller\s+guide|how\s+to\s+sell|app|application|mobile\s+app|android|ios)\b/u', $query);
    }

    private function previousTurnHasSellerAnchor(
        ?string $lastUserMessage,
        ?string $lastAssistantMessage = null,
        array $previousContextPayloads = [],
        ?array $pendingFollowUpState = null
    ): bool {
        $parts = [(string) $lastUserMessage, (string) $lastAssistantMessage];

        foreach ($previousContextPayloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $parts[] = implode(' ', array_filter([
                (string) ($payload['title'] ?? ''),
                (string) ($payload['category'] ?? ''),
                (string) ($payload['keywords'] ?? ''),
                (string) ($payload['content'] ?? ''),
                (string) ($payload['follow_up'] ?? ''),
            ]));
        }

        if (is_array($pendingFollowUpState)) {
            $parts[] = implode(' ', array_filter([
                (string) ($pendingFollowUpState['question'] ?? ''),
                (string) ($pendingFollowUpState['resolved_anchor'] ?? ''),
                (string) ($pendingFollowUpState['entity'] ?? ''),
                $this->listToText($pendingFollowUpState['topic_hints'] ?? []),
                $this->listToText($pendingFollowUpState['topics_covered'] ?? []),
            ]));
        }

        $combined = $this->normalize(implode(' ', array_filter($parts)));
        $hasArtistContext = (bool) preg_match('/\b(artist|seller|sell|sale|selling|profile|upload|submission|publish|publishing|whatsapp)\b/u', $combined);
        $hasArtworkContext = (bool) preg_match('/\b(painting|paintings|art\s+work|artwork|artworks|canvas|art)\b/u', $combined);

        return $hasArtistContext && $hasArtworkContext;
    }

    private function isAffirmative(string $message): bool
    {
        $query = $this->normalize($message);

        if (in_array($query, ['yes', 'yeah', 'yup', 'yep', 'ya', 'yah', 'sure', 'ok', 'okay', 'please', 'go ahead'], true)) {
            return true;
        }

        return mb_strlen($query) <= 24
            && (bool) preg_match('/^(yes|yeah|yup|yep|ya|yah|sure|ok|okay)\b/u', $query);
    }

    private function listToText($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return is_array($value) ? implode(' ', array_map('strval', $value)) : '';
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim(strip_tags($value)));
        $normalized = preg_replace('/[^a-z0-9\s]+/i', ' ', $normalized) ?? $normalized;

        return trim((string) preg_replace('/\s+/', ' ', $normalized));
    }
}
