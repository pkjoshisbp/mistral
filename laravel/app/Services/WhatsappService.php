<?php

namespace App\Services;

use App\Models\AdminSetting;

class WhatsappService
{
    protected string $version;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $businessAccountId;
    protected string $defaultLanguage;

    public function __construct()
    {
        $this->version = AdminSetting::get('whatsapp_api_version', 'v20.0');
        $this->phoneNumberId = AdminSetting::get('whatsapp_phone_number_id', '');
        $this->accessToken = AdminSetting::get('whatsapp_access_token', '');
        $this->businessAccountId = AdminSetting::get('whatsapp_business_account_id', '');
        $this->defaultLanguage = 'en_US';
    }

    /**
     * Send a text message. Optionally override phoneNumberId/accessToken to send from a specific org's WABA.
     */
    public function sendText(string $to, string $body, ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        return $this->callWhatsapp([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $body],
        ], $phoneNumberId, $accessToken);
    }

    /**
     * Send an image message. Optionally override credentials per send.
     */
    public function sendImage(string $to, string $imageUrl, ?string $caption = null, ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        return $this->callWhatsapp([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => array_filter([
                'link' => $imageUrl,
                'caption' => $caption,
            ]),
        ], $phoneNumberId, $accessToken);
    }

    /**
     * Send a template message with optional header image and body parameters.
     * $components example:
     *   [
     *     ['type' => 'header', 'parameters' => [[ 'type' => 'image', 'image' => ['link' => '...'] ]]],
     *     ['type' => 'body', 'parameters' => [[ 'type' => 'text', 'text' => 'John' ]]],
     *     ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [[ 'type' => 'text', 'text' => 'dynamic-suffix' ]]]
     *   ]
     */
    public function sendTemplate(string $to, string $templateName, string $language = 'en_US', array $components = [], ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        // Ensure component structure is compliant; allow empty components
        $components = array_values($components);
        return $this->callWhatsapp([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ], $phoneNumberId, $accessToken);
    }

    protected function callWhatsapp(array $payload, ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;
        $token = $accessToken ?: $this->accessToken;
        if (!$phoneId || !$token) {
            throw new \RuntimeException('WhatsApp settings not configured.');
        }
        $url = "https://graph.facebook.com/{$this->version}/{$phoneId}/messages";
        // Log attempt (no secrets)
        \Log::info('WhatsApp API send attempt', [
            'to' => $payload['to'] ?? null,
            'type' => $payload['type'] ?? null,
            'phone_number_id' => $phoneId,
            'api_version' => $this->version,
            'has_image' => isset($payload['image']),
            'caption_preview' => isset($payload['image']['caption']) ? substr((string) $payload['image']['caption'], 0, 80) : null,
            'template_name' => $payload['template']['name'] ?? null,
            'has_button_component' => isset($payload['template']['components']) && collect($payload['template']['components'])->contains(function($c){ return ($c['type'] ?? '') === 'button'; })
        ]);

        $resp = \Http::withToken($token)->post($url, $payload);
        if (!$resp->successful()) {
            $msg = 'WhatsApp API error: ' . $resp->status() . ' ' . $resp->body();
            \Log::error($msg);
            throw new \RuntimeException($msg);
        }
        $json = $resp->json();
        \Log::info('WhatsApp API success', [
            'to' => $payload['to'] ?? null,
            'message_id' => $json['messages'][0]['id'] ?? null,
            'status' => $resp->status(),
            'response_preview' => substr($resp->body(), 0, 300)
        ]);
        return $json;
    }

    /**
     * Create a message template in the WABA via Graph API.
     * $template array keys: name, category (MARKETING|UTILITY|AUTHENTICATION), language (optional), body_text,
     * header_type ('IMAGE'|'TEXT'|null), header_text (if TEXT), button_text, button_url.
     */
    public function createTemplate(array $template): array
    {
        $wabaId = $this->businessAccountId;
        if (!$wabaId || !$this->accessToken) {
            throw new \RuntimeException('WhatsApp Business Account ID or Access Token not configured.');
        }
        $lang = $template['language'] ?? $this->defaultLanguage;
        $components = [];

        if (!empty($template['header_type'])) {
            $header = [
                'type' => 'HEADER',
                'format' => strtoupper($template['header_type'])
            ];
            if (strtoupper($template['header_type']) === 'TEXT' && !empty($template['header_text'])) {
                $header['text'] = $template['header_text'];
            }
            $components[] = $header;
        }

        $components[] = [
            'type' => 'BODY',
            'text' => $template['body_text']
        ];

        if (!empty($template['button_text']) && !empty($template['button_url'])) {
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => [[
                    'type' => 'URL',
                    'text' => $template['button_text'],
                    'url' => $template['button_url']
                ]]
            ];
        }

        $payload = [
            'name' => $template['name'],
            'category' => strtoupper($template['category']),
            'language' => $lang,
            'components' => $components
        ];

        $url = "https://graph.facebook.com/{$this->version}/{$wabaId}/message_templates";
        \Log::info('Creating WhatsApp template', ['name' => $template['name'], 'category' => $template['category'], 'language' => $lang]);
        $resp = \Http::withToken($this->accessToken)->post($url, $payload);
        if (!$resp->successful()) {
            $msg = 'WhatsApp template create error: ' . $resp->status() . ' ' . $resp->body();
            \Log::error($msg);
            throw new \RuntimeException($msg);
        }
        return $resp->json();
    }

    /**
     * Get template status by name (returns list filtered by name).
     */
    public function getTemplateStatus(string $name): array
    {
        $wabaId = $this->businessAccountId;
        if (!$wabaId || !$this->accessToken) {
            throw new \RuntimeException('WhatsApp Business Account ID or Access Token not configured.');
        }
        $url = "https://graph.facebook.com/{$this->version}/{$wabaId}/message_templates?name=" . urlencode($name);
        $resp = \Http::withToken($this->accessToken)->get($url);
        if (!$resp->successful()) {
            $msg = 'WhatsApp template status error: ' . $resp->status() . ' ' . $resp->body();
            \Log::error($msg);
            throw new \RuntimeException($msg);
        }
        return $resp->json();
    }
}
