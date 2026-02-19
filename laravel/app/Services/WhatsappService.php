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
        $this->version = AdminSetting::get('whatsapp_api_version', env('WHATSAPP_API_VERSION', 'v20.0'));
        $this->phoneNumberId = AdminSetting::get('whatsapp_phone_number_id', env('WHATSAPP_PHONE_NUMBER_ID', ''));
        $this->accessToken = AdminSetting::get('whatsapp_access_token', env('WHATSAPP_ACCESS_TOKEN', ''));
        $this->businessAccountId = AdminSetting::get('whatsapp_business_account_id', env('WHATSAPP_WABA_ID', ''));
        $this->defaultLanguage = 'en_US';
    }

    public function getBusinessAccountId(): string
    {
        return (string) $this->businessAccountId;
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
     * Send an image message. Provide either $imageUrl OR $imageMediaId.
     * Optionally override credentials per send.
     */
    public function sendImage(string $to, ?string $imageUrl = null, ?string $caption = null, ?string $phoneNumberId = null, ?string $accessToken = null, ?string $imageMediaId = null): array
    {
        $imagePayload = [];
        if ($imageMediaId) {
            $imagePayload['id'] = $imageMediaId;
        } elseif ($imageUrl) {
            $imagePayload['link'] = $imageUrl;
        }
        if ($caption !== null && $caption !== '') {
            $imagePayload['caption'] = $caption;
        }
        return $this->callWhatsapp([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => $imagePayload,
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
            $status = $resp->status();
            $body = $resp->body();
            $json = $resp->json();
            $err = $json['error'] ?? null;
            $code = $err['code'] ?? null;
            $subcode = $err['error_subcode'] ?? null;
            $msg = 'WhatsApp API error: ' . $status . ' ' . $body;
            \Log::error($msg);
            // Detect expired/invalid token and throw specific exception
            if ($code === 190 && in_array($subcode, [463, 467, 460, 490], true)) {
                throw new \App\Exceptions\WhatsappTokenExpiredException($msg, $status, $code, $subcode);
            }
            throw new \App\Exceptions\WhatsappApiException($msg, $status, $code, $subcode);
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
     * Mark an inbound message as read.
     * Returns API response; logs warning if the API call fails but does not throw.
     */
    public function markAsRead(string $messageId, ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;
        $token = $accessToken ?: $this->accessToken;
        if (!$phoneId || !$token) {
            throw new \RuntimeException('WhatsApp settings not configured.');
        }
        $url = "https://graph.facebook.com/{$this->version}/{$phoneId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId,
        ];
        \Log::info('WhatsApp mark-as-read attempt', ['message_id' => $messageId]);
        $resp = \Http::withToken($token)->post($url, $payload);
        if (!$resp->successful()) {
            \Log::warning('WhatsApp mark-as-read failed', ['status' => $resp->status(), 'body' => substr($resp->body(), 0, 300)]);
        } else {
            \Log::info('WhatsApp mark-as-read success', ['message_id' => $messageId]);
        }
        return $resp->json() ?: [];
    }

    /**
     * Send a reaction to a specific incoming message (lightweight immediate feedback).
     */
    public function sendReaction(string $to, string $messageId, string $emoji = "✍️", ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        return $this->callWhatsapp([
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'reaction',
            'reaction' => [
                'message_id' => $messageId,
                'emoji' => $emoji,
            ],
        ], $phoneNumberId, $accessToken);
    }

    /**
     * Show a typing indicator for a specific incoming message. Also marks the message as read.
     * Note: Some API versions auto-hide the indicator after ~25s or when a reply is sent.
     */
    public function sendTypingIndicator(string $incomingMessageId, string $type = 'text', ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;
        $token = $accessToken ?: $this->accessToken;
        if (!$phoneId || !$token) {
            throw new \RuntimeException('WhatsApp settings not configured.');
        }
        $url = "https://graph.facebook.com/{$this->version}/{$phoneId}/messages";
        $payload = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $incomingMessageId,
            'typing_indicator' => [ 'type' => $type ],
        ];
        \Log::info('WhatsApp typing-indicator attempt', ['message_id' => $incomingMessageId, 'type' => $type]);
        $resp = \Http::withToken($token)->post($url, $payload);
        if (!$resp->successful()) {
            \Log::warning('WhatsApp typing-indicator failed', ['status' => $resp->status(), 'body' => substr($resp->body(), 0, 300)]);
        } else {
            \Log::info('WhatsApp typing-indicator success', ['message_id' => $incomingMessageId]);
        }
        return $resp->json() ?: [];
    }
    /**
     * Upload media to WhatsApp and return media ID.
     * $localPath must be an absolute filesystem path accessible to this process.
     */
    public function uploadMedia(string $localPath, string $mimeType = null, ?string $phoneNumberId = null, ?string $accessToken = null): array
    {
        $phoneId = $phoneNumberId ?: $this->phoneNumberId;
        $token = $accessToken ?: $this->accessToken;
        if (!$phoneId || !$token) {
            throw new \RuntimeException('WhatsApp settings not configured.');
        }

        if ($mimeType === null) {
            // Detect mime type best-effort
            $detected = @mime_content_type($localPath);
            $mimeType = $detected ?: 'application/octet-stream';
        }
        $url = "https://graph.facebook.com/{$this->version}/{$phoneId}/media";
        \Log::info('WhatsApp media upload attempt', [
            'phone_number_id' => $phoneId,
            'mime' => $mimeType,
            'filename' => basename($localPath),
        ]);
        $resp = \Http::asMultipart()
            ->withToken($token)
            ->attach('file', fopen($localPath, 'r'), basename($localPath))
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'type' => $mimeType,
            ]);
        if (!$resp->successful()) {
            $status = $resp->status();
            $body = $resp->body();
            $json = $resp->json();
            $err = $json['error'] ?? null;
            $code = $err['code'] ?? null;
            $subcode = $err['error_subcode'] ?? null;
            $msg = 'WhatsApp media upload error: ' . $status . ' ' . $body;
            \Log::error($msg);
            if ($code === 190 && in_array($subcode, [463, 467, 460, 490], true)) {
                throw new \App\Exceptions\WhatsappTokenExpiredException($msg, $status, $code, $subcode);
            }
            throw new \App\Exceptions\WhatsappApiException($msg, $status, $code, $subcode);
        }
        $json = $resp->json();
        \Log::info('WhatsApp media upload success', [
            'media_id' => $json['id'] ?? null,
        ]);
        return $json; // { id: 'MEDIA_ID' }
    }

    /**
     * Upload media by URL for template header examples (WABA media upload).
     * Returns media ID that can be used as header_handle in template creation.
     */
    public function uploadTemplateMediaFromUrl(string $fileUrl, ?string $mimeType = null): array
    {
        $wabaId = $this->businessAccountId;
        $token = $this->accessToken;
        if (!$wabaId || !$token) {
            throw new \RuntimeException('WhatsApp Business Account ID or Access Token not configured.');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'file_url' => $fileUrl,
        ];
        if ($mimeType) {
            $payload['type'] = $mimeType;
        }

        $url = "https://graph.facebook.com/{$this->version}/{$wabaId}/media";
        \Log::info('WhatsApp template media upload attempt', [
            'waba_id' => $wabaId,
            'file_url' => $fileUrl,
            'mime' => $mimeType,
        ]);
        $resp = \Http::asForm()->withToken($token)->post($url, $payload);
        if (!$resp->successful()) {
            $status = $resp->status();
            $body = $resp->body();
            $json = $resp->json();
            $err = $json['error'] ?? null;
            $code = $err['code'] ?? null;
            $subcode = $err['error_subcode'] ?? null;
            $msg = 'WhatsApp template media upload error: ' . $status . ' ' . $body;
            \Log::error($msg);
            if ($code === 190 && in_array($subcode, [463, 467, 460, 490], true)) {
                throw new \App\Exceptions\WhatsappTokenExpiredException($msg, $status, $code, $subcode);
            }
            throw new \App\Exceptions\WhatsappApiException($msg, $status, $code, $subcode);
        }
        $json = $resp->json();
        \Log::info('WhatsApp template media upload success', [
            'media_id' => $json['id'] ?? null,
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
        if ($lang === 'en') {
            $lang = 'en_US';
        }
        $components = [];

        if (!empty($template['header_type'])) {
            $header = [
                'type' => 'HEADER',
                'format' => strtoupper($template['header_type'])
            ];
            if (strtoupper($template['header_type']) === 'TEXT' && !empty($template['header_text'])) {
                $headerText = $template['header_text'];
                $header['text'] = $headerText;
                if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $headerText, $matches)) {
                    $varCount = count(array_unique($matches[1] ?? []));
                    if ($varCount > 0) {
                        $exampleValues = [];
                        for ($i = 1; $i <= $varCount; $i++) {
                            $exampleValues[] = 'Example ' . $i;
                        }
                        $header['example'] = [
                            'header_text' => $exampleValues,
                        ];
                    }
                }
            }
            if (strtoupper($template['header_type']) === 'IMAGE' && !empty($template['header_example'])) {
                $header['example'] = [
                    'header_handle' => [$template['header_example']]
                ];
            }
            $components[] = $header;
        }

        $bodyText = $template['body_text'] ?? '';
        $bodyComponent = [
            'type' => 'BODY',
            'text' => $bodyText,
        ];

        // If the body includes variables like {{1}}, include example values.
        if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches)) {
            $varCount = count(array_unique($matches[1] ?? []));
            if ($varCount > 0) {
                $exampleValues = [];
                for ($i = 1; $i <= $varCount; $i++) {
                    $exampleValues[] = 'Example ' . $i;
                }
                $bodyComponent['example'] = [
                    'body_text' => [$exampleValues],
                ];
            }
        }

        $components[] = $bodyComponent;

        if (!empty($template['button_text']) && !empty($template['button_url'])) {
            $button = [
                'type' => 'URL',
                'text' => $template['button_text'],
                'url' => $template['button_url']
            ];
            if (preg_match('/\{\{\s*1\s*\}\}/', (string) $template['button_url'])) {
                $button['example'] = ['https://example.com/abc123'];
            }
            $components[] = [
                'type' => 'BUTTONS',
                'buttons' => [$button]
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
            $body = $resp->json();
            $error = $body['error'] ?? [];
            $userTitle = $error['error_user_title'] ?? null;
            $userMsg = $error['error_user_msg'] ?? null;
            $detail = trim(($userTitle ? ($userTitle . ': ') : '') . ($userMsg ?? ''));
            $msg = 'WhatsApp template create error: ' . $resp->status() . ' ' . ($detail !== '' ? $detail : $resp->body());
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

    /**
     * Fetch templates from WABA (optionally filtered by status).
     */
    public function fetchTemplates(?string $status = 'APPROVED'): array
    {
        $wabaId = $this->businessAccountId;
        if (!$wabaId || !$this->accessToken) {
            throw new \RuntimeException('WhatsApp Business Account ID or Access Token not configured.');
        }

        $fields = 'name,language,status,category,components';
        $params = [
            'fields' => $fields,
            'limit' => 200,
        ];
        if ($status) {
            $params['status'] = $status;
        }

        $url = "https://graph.facebook.com/{$this->version}/{$wabaId}/message_templates";
        $templates = [];

        while ($url) {
            $resp = \Http::withToken($this->accessToken)->get($url, $params);
            if (!$resp->successful()) {
                $msg = 'WhatsApp template fetch error: ' . $resp->status() . ' ' . $resp->body();
                \Log::error($msg);
                throw new \RuntimeException($msg);
            }

            $json = $resp->json();
            foreach (($json['data'] ?? []) as $tpl) {
                $templates[] = $tpl;
            }

            $next = $json['paging']['next'] ?? null;
            $url = $next ?: null;
            $params = [];
        }

        return $templates;
    }
}
