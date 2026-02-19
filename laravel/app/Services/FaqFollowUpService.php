<?php

namespace App\Services;

use App\Models\Organization;

class FaqFollowUpService
{
    public function getFollowUpText(Organization $organization, string $responseText, ?string $override = null): string
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

        if ($this->responseAlreadyContainsFollowUp($responseText, $candidate)) {
            return '';
        }

        if ($this->isNegativeResponse($responseText, $settings)) {
            return '';
        }

        return $candidate;
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
}
