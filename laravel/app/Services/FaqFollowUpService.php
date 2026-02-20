<?php

namespace App\Services;

use App\Models\Organization;

class FaqFollowUpService
{
    public function getFollowUpText(Organization $organization, string $responseText, ?string $override = null, array $context = []): string
    {
        $settings = $organization->settings ?? [];

        $candidate = trim((string) $override);
        if ($candidate === '') {
            $enabled = (bool) ($settings['faq_follow_up_enabled'] ?? false);
            if (!$enabled) {
                return '';
            }
            $candidate = trim((string) ($settings['faq_follow_up_text'] ?? ''));
        }

        if ($candidate === '') {
            return '';
        }

        $resolvedCandidate = $this->resolveDynamicFollowUpText($candidate, $settings, $context);
        if ($resolvedCandidate === '') {
            return '';
        }

        if ($this->responseAlreadyContainsFollowUp($responseText, $resolvedCandidate)) {
            return '';
        }

        if ($this->isNegativeResponse($responseText, $settings)) {
            return '';
        }

        return $resolvedCandidate;
    }

    public function getFollowUpInstruction(Organization $organization): string
    {
        $settings = $organization->settings ?? [];
        $enabled = (bool) ($settings['faq_follow_up_enabled'] ?? false);
        $text = trim((string) ($settings['faq_follow_up_text'] ?? ''));

        if (!$enabled || $text === '') {
            return '';
        }

        $keywords = $this->normalizeKeywords($settings['faq_follow_up_negative_keywords'] ?? []);
        if (empty($keywords)) {
            $keywords = $this->getDefaultNegativeKeywords();
        }

        $examples = implode(', ', array_slice($keywords, 0, 6));
        return "Default follow-up: \"{$text}\". Use it at the end unless the response is negative (e.g., {$examples}).";
    }

    private function isNegativeResponse(string $responseText, array $settings): bool
    {
        $keywords = $this->normalizeKeywords($settings['faq_follow_up_negative_keywords'] ?? []);
        if (empty($keywords)) {
            $keywords = $this->getDefaultNegativeKeywords();
        }

        foreach ($keywords as $keyword) {
            if ($this->containsKeyword($responseText, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function responseAlreadyContainsFollowUp(string $responseText, string $candidate): bool
    {
        $response = trim($responseText);
        if ($response === '') {
            return false;
        }

        return stripos($response, $candidate) !== false;
    }

    private function normalizeKeywords($value): array
    {
        if (is_array($value)) {
            $keywords = $value;
        } else {
            $keywords = preg_split('/[\r\n,]+/', (string) $value) ?: [];
        }

        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords, static function ($kw) {
            return $kw !== '' && mb_strlen($kw) >= 2;
        });

        return array_values($keywords);
    }

    private function containsKeyword(string $text, string $keyword): bool
    {
        $escaped = preg_quote($keyword, '/');
        $pattern = '/(^|[^\p{L}\p{N}])' . $escaped . '([^\p{L}\p{N}]|$)/iu';

        return preg_match($pattern, $text) === 1;
    }

    private function getDefaultNegativeKeywords(): array
    {
        return [
            'no',
            'nope',
            'nah',
            'no thanks',
            'not interested',
            'not really',
            'stop',
            'enough',
            'that\'s all',
            'goodbye',
            'bye',
            'cancel'
        ];
    }

    private function resolveDynamicFollowUpText(string $template, array $settings, array $context): string
    {
        $text = trim($template);
        if ($text === '') {
            return '';
        }

        $locationContacts = $this->normalizeAssociativeMap($settings['faq_follow_up_location_contacts'] ?? []);
        $dynamicVariables = $this->normalizeAssociativeMap($settings['faq_follow_up_dynamic_variables'] ?? []);

        $normalizedContext = $this->normalizeContext($context);
        $locationRaw = (string) ($normalizedContext['location'] ?? '');
        $locationKey = $this->normalizeMapKey($locationRaw);

        $locationContact = '';
        if ($locationKey !== '' && isset($locationContacts[$locationKey])) {
            $locationContact = (string) $locationContacts[$locationKey];
        } elseif (isset($dynamicVariables['default_contact'])) {
            $locationContact = (string) $dynamicVariables['default_contact'];
        }

        $replacements = [
            '{{location}}' => $locationRaw,
            '{{region}}' => (string) ($normalizedContext['region'] ?? ''),
            '{{country}}' => (string) ($normalizedContext['country'] ?? ''),
            '{{location_contact}}' => $locationContact,
            '{{contact_number}}' => $locationContact,
        ];

        foreach ($dynamicVariables as $key => $value) {
            $replacements['{{' . $key . '}}'] = (string) $value;
        }

        $resolved = strtr($text, $replacements);
        $resolved = preg_replace('/\{\{[^}]+\}\}/', '', (string) $resolved) ?? '';
        $resolved = preg_replace('/[ \t]{2,}/', ' ', $resolved) ?? '';
        $resolved = preg_replace('/\n{3,}/', "\n\n", $resolved) ?? '';

        return trim((string) $resolved);
    }

    private function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $normalized[(string) $key] = trim((string) $value);
            }
        }

        if (!empty($context['custom_fields']) && is_array($context['custom_fields'])) {
            foreach ($context['custom_fields'] as $customKey => $customValue) {
                if (is_string($customValue) || is_numeric($customValue)) {
                    $normalized[(string) $customKey] = trim((string) $customValue);
                }
            }
        }

        return $normalized;
    }

    private function normalizeAssociativeMap($value): array
    {
        $pairs = [];

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && (is_string($item) || is_numeric($item))) {
                    $mapKey = $this->normalizeMapKey($key);
                    if ($mapKey !== '') {
                        $pairs[$mapKey] = trim((string) $item);
                    }
                    continue;
                }

                if (is_array($item)) {
                    $itemKey = $this->normalizeMapKey((string) ($item['key'] ?? $item['location'] ?? ''));
                    $itemValue = trim((string) ($item['value'] ?? $item['contact'] ?? ''));
                    if ($itemKey !== '' && $itemValue !== '') {
                        $pairs[$itemKey] = $itemValue;
                    }
                }
            }
        }

        return $pairs;
    }

    private function normalizeMapKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = preg_replace('/\s+/', ' ', $key) ?? '';
        return trim((string) $key);
    }
}
