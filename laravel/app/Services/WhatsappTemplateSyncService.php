<?php

namespace App\Services;

use App\Models\WhatsappTemplate;

class WhatsappTemplateSyncService
{
    public function syncTemplates(string $status = 'APPROVED'): int
    {
        $svc = app(WhatsappService::class);
        $wabaId = $svc->getBusinessAccountId();
        $templates = $svc->fetchTemplates($status);
        $count = 0;

        foreach ($templates as $tpl) {
            $normalized = $this->normalizeTemplatePayload($tpl, $wabaId);
            WhatsappTemplate::updateOrCreate(
                [
                    'name' => $normalized['name'],
                    'language' => $normalized['language'],
                ],
                $normalized
            );
            $count++;
        }

        return $count;
    }

    private function normalizeTemplatePayload(array $tpl, string $wabaId): array
    {
        $components = $tpl['components'] ?? [];
        $headerType = null;
        $headerText = null;
        $headerMediaUrl = null;
        $bodyText = null;
        $footerText = null;
        $buttons = [];

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));
            if ($type === 'HEADER') {
                $headerType = strtoupper((string) ($component['format'] ?? '')) ?: null;
                if ($headerType === 'TEXT') {
                    $headerText = $component['text'] ?? null;
                }
            }
            if ($type === 'BODY') {
                $bodyText = $component['text'] ?? null;
            }
            if ($type === 'FOOTER') {
                $footerText = $component['text'] ?? null;
            }
            if ($type === 'BUTTONS') {
                $buttons = $component['buttons'] ?? [];
            }
        }

        $bodyText = is_string($bodyText) ? $bodyText : null;
        $bodyVariableCount = 0;
        if ($bodyText) {
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches);
            $bodyVariableCount = count(array_unique($matches[1] ?? []));
        }

        return [
            'name' => (string) ($tpl['name'] ?? ''),
            'language' => $this->normalizeLanguage($tpl['language'] ?? null),
            'category' => $tpl['category'] ?? null,
            'status' => $tpl['status'] ?? null,
            'header_type' => $headerType,
            'header_text' => $headerText,
            'header_media_url' => $headerMediaUrl,
            'body_text' => $bodyText,
            'footer_text' => $footerText,
            'body_variable_count' => $bodyVariableCount,
            'buttons' => $buttons,
            'raw_components' => $components,
            'raw_payload' => $tpl,
            'waba_id' => $wabaId,
            'is_active' => true,
        ];
    }

    private function normalizeLanguage(?string $language): ?string
    {
        if (!$language) {
            return null;
        }
        if ($language === 'en') {
            return 'en_US';
        }
        return $language;
    }
}
