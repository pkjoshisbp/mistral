<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Log;
use App\Services\AiAgentService;
use App\Services\WhatsappService;
use App\Models\Organization;
use App\Models\ChatConversation;
use App\Models\ChatMessage;

class WhatsappWebhookController extends Controller
{
    /**
     * Backwards-compatible verification endpoint without org slug.
     * Tries global token first, then scans orgs for matching token.
     */
    public function verify(Request $request)
    {
        $requestedVerifyToken = $request->hub_verify_token;
        
        // First try global admin verify token (for backwards compatibility)
        $globalVerifyToken = AdminSetting::get('whatsapp_verify_token', '');
        if ($request->hub_mode === 'subscribe' && $requestedVerifyToken === $globalVerifyToken) {
            return response($request->hub_challenge, 200);
        }
        
        // Try organization-specific verify tokens
        $organizations = Organization::whereNotNull('settings')->get();
        foreach ($organizations as $org) {
            $orgSettings = $org->settings ?? [];
            $orgVerifyToken = $orgSettings['whatsapp_verify_token'] ?? null;
            
            if ($orgVerifyToken && $requestedVerifyToken === $orgVerifyToken) {
                Log::info('WhatsApp webhook verified for organization', [
                    'org_id' => $org->id,
                    'org_name' => $org->name
                ]);
                return response($request->hub_challenge, 200);
            }
        }
        
        Log::warning('WhatsApp webhook verification failed', [
            'provided_token' => $requestedVerifyToken,
            'hub_mode' => $request->hub_mode
        ]);
        
        return response('Invalid verify token', 403);
    }

    /**
     * Preferred verification endpoint scoped to a specific organization slug.
     * Only the org's configured verify token will be accepted.
     */
    public function verifyForOrg(Request $request, string $org_slug)
    {
        $requestedVerifyToken = $request->hub_verify_token;
        if ($request->hub_mode !== 'subscribe') {
            return response('Invalid hub.mode', 400);
        }

        $org = Organization::where('slug', $org_slug)->first();
        if (!$org) {
            Log::warning('WhatsApp webhook verification failed - org not found', [
                'org_slug' => $org_slug,
            ]);
            return response('Organization not found', 404);
        }

        $orgSettings = $org->settings ?? [];
        $orgVerifyToken = $orgSettings['whatsapp_verify_token'] ?? null;
        if ($orgVerifyToken && $requestedVerifyToken === $orgVerifyToken) {
            Log::info('WhatsApp webhook verified for organization (slug endpoint)', [
                'org_id' => $org->id,
                'org_slug' => $org->slug,
            ]);
            return response($request->hub_challenge, 200);
        }

        Log::warning('WhatsApp webhook verification failed (slug endpoint)', [
            'org_id' => $org->id,
            'org_slug' => $org->slug,
            'provided_token' => $requestedVerifyToken,
        ]);
        return response('Invalid verify token', 403);
    }

    public function receive(Request $request)
    {
        // Log raw webhook body to avoid Monolog deep normalization truncation
        $raw = (string) $request->getContent();
        Log::info('WhatsApp webhook raw', [
            'length' => strlen($raw),
            'preview' => substr($raw, 0, 4000) // cap to keep logs readable
        ]);

        $payload = $request->all();
        Log::info('WhatsApp webhook received (parsed)', [
            'has_entry' => isset($payload['entry']),
            'entry_count' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0
        ]);

        // Handle message notifications
        try {
            $entries = $payload['entry'] ?? [];
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    $messages = $value['messages'] ?? [];
                    $metadata = $value['metadata'] ?? [];
                    if (empty($messages) || empty($metadata)) { continue; }

                    $phoneNumberId = $metadata['phone_number_id'] ?? null;
                    // Resolve organization by incoming phone_number_id
                    $org = null;
                    if ($phoneNumberId) {
                        $org = Organization::where('settings->whatsapp_phone_number_id', $phoneNumberId)
                            ->orWhere('settings->whatsapp->phone_number_id', $phoneNumberId)
                            ->orWhere('settings->whatsapp_phone_id', $phoneNumberId)
                            ->first();
                        if (!$org) {
                            // Fallback: if incoming phone matches admin default, try routing to ai-chat-support org
                            $adminDefaultPhone = AdminSetting::get('whatsapp_phone_number_id', '');
                            if ($adminDefaultPhone && $adminDefaultPhone === $phoneNumberId) {
                                $org = Organization::where('slug', 'ai-chat-support')->first();
                                if ($org) {
                                    Log::info('WhatsApp webhook receive - resolved org via admin default phone mapping', [
                                        'incoming_phone_number_id' => $phoneNumberId,
                                        'org_id' => $org->id,
                                        'org_slug' => $org->slug,
                                    ]);
                                }
                            }
                            if (!$org) {
                                Log::warning('WhatsApp webhook receive - org not resolved from phone_number_id', [
                                    'incoming_phone_number_id' => $phoneNumberId
                                ]);
                            }
                        }
                    }
                    // Extract contact name if available
                    $contactName = null;
                    if (!empty($value['contacts'][0]['profile']['name'])) {
                        $contactName = $value['contacts'][0]['profile']['name'];
                    }
                    foreach ($messages as $msg) {
                        // Extract sender and normalize inbound text across message types
                        $from = $msg['from'] ?? null;
                        $type = $msg['type'] ?? null;
                        $text = null;
                        $waMessageId = $msg['id'] ?? null;
                        if ($type === 'text' && isset($msg['text']['body'])) {
                            $text = $msg['text']['body'];
                        } elseif ($type === 'interactive') {
                            $interactive = $msg['interactive'] ?? [];
                            $iType = $interactive['type'] ?? null; // 'button' or 'list_reply'
                            if ($iType === 'button' && isset($interactive['button_reply']['title'])) {
                                $text = $interactive['button_reply']['title'];
                            } elseif ($iType === 'list_reply' && isset($interactive['list_reply']['title'])) {
                                $text = $interactive['list_reply']['title'];
                            }
                        } elseif ($type === 'image') {
                            $text = $msg['image']['caption'] ?? null;
                        } elseif ($type === 'button' && isset($msg['button']['text'])) {
                            $text = $msg['button']['text'];
                        }

                        Log::info('Inbound WhatsApp message extracted', [
                            'from' => $from,
                            'type' => $type,
                            'has_text' => $text !== null && trim((string)$text) !== '',
                            'text_preview' => $text ? substr((string)$text, 0, 180) : null
                        ]);

                        if (!$from || !$text) { continue; }

                        // Idempotency: ensure we don't process the same WA message twice
                        if ($waMessageId) {
                            $already = ChatMessage::where('metadata->wa_message_id', $waMessageId)->exists();
                            if ($already) {
                                Log::info('Skipping duplicate WhatsApp message (idempotent)', ['wa_message_id' => $waMessageId]);
                                continue;
                            }
                        }

                        // We intend to reply; show typing indicator immediately (before heavy work)
                        try {
                            $waSvcTmp = app(WhatsappService::class);
                            $waSvcTmp->sendTypingIndicator($waMessageId, 'text', $phoneNumberId, null);
                        } catch (\Throwable $te) {
                            Log::warning('WhatsApp immediate feedback failed', ['error' => $te->getMessage()]);
                        }

                        $conversation = null;
                        if ($org) {
                            $conversation = ChatConversation::where('organization_id', $org->id)
                                ->where('visitor_phone', $from)
                                ->where('metadata->source', 'whatsapp')
                                ->first();
                            if (!$conversation) {
                                $conversation = ChatConversation::create([
                                    'conversation_id' => 'wa:' . ($phoneNumberId ?: 'unknown') . ':' . $from,
                                    'organization_id' => $org->id,
                                    'visitor_phone' => $from,
                                    'visitor_name' => $contactName,
                                    'status' => 'active',
                                    'metadata' => [
                                        'source' => 'whatsapp',
                                        'phone_number_id' => $phoneNumberId,
                                        'waba_display_phone' => $metadata['display_phone_number'] ?? null,
                                    ],
                                    'last_activity_at' => now(),
                                ]);
                            } else {
                                $conversation->update(['last_activity_at' => now()]);
                            }
                        }

                        if ($conversation) {
                            ChatMessage::create([
                                'conversation_id' => $conversation->id,
                                'sender_type' => 'user',
                                'sender_name' => $contactName,
                                'message' => $text,
                                'metadata' => [
                                    'source' => 'whatsapp',
                                    'wa_message_id' => $waMessageId,
                                    'type' => $type,
                                    'raw' => $msg,
                                ],
                                'sent_at' => now(),
                            ]);
                        }

                        // Build contexted AI reply aligned with widget behavior
                        $ai = app(AiAgentService::class);
                        Log::info('WhatsApp LLM search prep', [
                            'org_slug' => $org?->slug,
                            'phone_number_id' => $phoneNumberId,
                            'query_preview' => substr((string)$text, 0, 180)
                        ]);
                        $embedding = $ai->embed($text);
                        $answer = null;
                        $usedContextChars = 0;
                        if ($org && $embedding) {
                            $collection = $org->slug;
                            $search = $ai->searchQdrant($collection, $embedding, 5) ?: [];
                            $context = '';
                            $maxContextChars = 1500; // Limit context to prevent timeouts
                            foreach (($search['results'] ?? []) as $res) {
                                $payloadRes = $res['payload'] ?? [];
                                $relevantFields = ['title', 'content', 'answer', 'description'];
                                foreach ($relevantFields as $field) {
                                    if (isset($payloadRes[$field]) && is_string($payloadRes[$field]) && !empty($payloadRes[$field])) {
                                        $fieldContent = ucfirst($field) . ': ' . $payloadRes[$field] . "\n";
                                        if (strlen($context . $fieldContent) > $maxContextChars) { break 2; }
                                        $context .= $fieldContent;
                                    }
                                }
                                $context .= "\n";
                                if (strlen($context) > $maxContextChars) { break; }
                            }
                            $usedContextChars = strlen($context);

                            // Compose system prompt with official org contacts and widget-style rules
                            $orgWebsite = $org->website ?: config('app.url');
                            $orgEmail = $org->contact_email ?? null;
                            $orgPhone = $org->contact_phone ?? null;
                            $orgDesc  = $org->description ? trim(preg_replace('/\s+/', ' ', strip_tags($org->description))) : null;
                            $assistantName = $org->settings['assistant_display_name'] ?? 'AI Assistant';
                            $system = "You are {$assistantName}, a helpful customer service assistant for {$org->name}. ";
                            if ($orgDesc) { $system .= "About: {$orgDesc}. "; }
                            $system .= "\nOrganization info:\n";
                            $system .= "- Official website: {$orgWebsite}\n";
                            $system .= $orgEmail ? "- Official email: {$orgEmail}\n" : "- Official email: (not provided)\n";
                            $system .= $orgPhone ? "- Official phone: {$orgPhone}\n" : "- Official phone: (not provided)\n";
                            if ($context) { $system .= "Use this info:\n{$context}\n"; }
                            $system .= "Rules: Only use the official website/email/phone above. Do not invent or guess any contact details. If an official contact is not provided, direct the user to the official website instead. ";
                            $system .= "Keep responses concise and direct. Always use plain text only - no HTML or markdown. Include full https URLs when mentioning sites. Do not mention WhatsApp or any specific messaging platform unless the user explicitly asks about it.";

                            $chatMessages = [
                                ['role' => 'system', 'content' => $system],
                                ['role' => 'user', 'content' => $text],
                            ];

                            // Use the same provider/model selection as widget
                            $provider = $ai->getAiProviderForOrganization($org->id ?? null);
                            if ($provider === 'openai') {
                                $model = $ai->getOpenAiModelForOrganization($org->id ?? null);
                                $resp = $ai->openAiChat($chatMessages, $model, null, $org->id ?? null);
                            } else {
                                $model = $ai->getLlamaModelForOrganization($org->id ?? null);
                                $resp = $ai->llmChat($chatMessages, $model, null, $org->id ?? null);
                            }
                            $answer = $resp['message']['content'] ?? null;
                        }
                        if (!$answer || trim($answer) === '') {
                            Log::warning('LLM returned empty answer; using default fallback', [
                                'org_id' => $org?->id, 'from' => $from, 'context_chars' => $usedContextChars
                            ]);
                            $answer = "Thanks for your message. Our assistant will get back to you shortly.";
                        }

                        // Ensure WhatsApp receives plain text with linkified URLs (no HTML)
                        $answer = $this->toWhatsappPlainText($answer);

                        // Send the reply using either org-specific token/phone or admin defaults
                        $svc = app(WhatsappService::class);
                        $orgPhoneId = $org ? ($org->settings['whatsapp_phone_number_id'] ?? null) : null;
                        $orgToken = $org ? ($org->settings['whatsapp_access_token'] ?? null) : null;
                        try {
                            $svc->sendText($from, $answer, $orgPhoneId ?: $phoneNumberId, $orgToken);
                            Log::info('WhatsApp auto-reply sent', [
                                'to' => $from,
                                'org_id' => $org?->id,
                                'answer_len' => strlen($answer)
                            ]);
                            if (isset($conversation)) {
                                $assistantName = $org->settings['assistant_display_name'] ?? 'AI Assistant';
                                ChatMessage::create([
                                    'conversation_id' => $conversation->id,
                                    'sender_type' => 'ai',
                                    'sender_name' => $assistantName,
                                    'message' => $answer,
                                    'metadata' => [
                                        'source' => 'whatsapp',
                                        'direction' => 'outbound'
                                    ],
                                    'sent_at' => now(),
                                ]);
                                $conversation->update(['last_activity_at' => now()]);
                            }
                        } catch (\App\Exceptions\WhatsappTokenExpiredException $e) {
                            // Mark org token as expired and try fallback to admin token if configured
                            if ($org) {
                                $settings = $org->settings ?? [];
                                $settings['whatsapp_token_expired'] = true;
                                $org->settings = $settings;
                                $org->save();
                            }
                            Log::error('WhatsApp org token expired; attempting fallback to admin token', ['org_id' => $org?->id, 'error' => $e->getMessage()]);
                            try {
                                $svc->sendText($from, $answer, null, null); // use admin defaults (phone_number_id + token)
                                Log::info('WhatsApp auto-reply sent via admin fallback', ['to' => $from, 'org_id' => $org?->id]);
                            } catch (\Throwable $e2) {
                                Log::error('Fallback send via admin token failed', ['error' => $e2->getMessage()]);
                            }
                        } catch (\Throwable $e) {
                            Log::error('Failed to send WhatsApp reply', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing error', ['error' => $e->getMessage()]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Inbound webhook endpoint scoped to organization slug.
     * Resolves organization by slug (primary) and enforces isolation.
     */
    public function receiveForOrg(Request $request, string $org_slug)
    {
        // Log raw webhook body
        $raw = (string) $request->getContent();
        Log::info('WhatsApp webhook raw (slug endpoint)', [
            'org_slug' => $org_slug,
            'length' => strlen($raw),
            'preview' => substr($raw, 0, 4000)
        ]);

        $org = Organization::where('slug', $org_slug)->first();
        if (!$org) {
            Log::warning('WhatsApp webhook receive - org not found (slug endpoint)', ['org_slug' => $org_slug]);
            return response()->json(['status' => 'error', 'message' => 'Organization not found'], 404);
        }

        // Use the same processing as receive(), but force $org for routing and replies
        $payload = $request->all();
        Log::info('WhatsApp webhook received (parsed, slug endpoint)', [
            'org_id' => $org->id,
            'has_entry' => isset($payload['entry']),
            'entry_count' => isset($payload['entry']) && is_array($payload['entry']) ? count($payload['entry']) : 0
        ]);

        try {
            $entries = $payload['entry'] ?? [];
            foreach ($entries as $entry) {
                $changes = $entry['changes'] ?? [];
                foreach ($changes as $change) {
                    $value = $change['value'] ?? [];
                    $messages = $value['messages'] ?? [];
                    $metadata = $value['metadata'] ?? [];
                    if (empty($messages) || empty($metadata)) { continue; }

                    $phoneNumberId = $metadata['phone_number_id'] ?? null;
                    // Optional: validate metadata phone number ID matches org settings when configured
                    $orgPhoneId = $org->settings['whatsapp_phone_number_id'] ?? null;
                    if ($orgPhoneId && $phoneNumberId && $orgPhoneId !== $phoneNumberId) {
                        Log::warning('WhatsApp webhook phone_number_id mismatch', [
                            'org_id' => $org->id,
                            'org_slug' => $org->slug,
                            'configured' => $orgPhoneId,
                            'incoming' => $phoneNumberId,
                        ]);
                    }

                    foreach ($messages as $msg) {
                        $from = $msg['from'] ?? null;
                        $type = $msg['type'] ?? null;
                        $text = null;
                        $waMessageId = $msg['id'] ?? null;
                        if ($type === 'text' && isset($msg['text']['body'])) {
                            $text = $msg['text']['body'];
                        } elseif ($type === 'interactive') {
                            $interactive = $msg['interactive'] ?? [];
                            $iType = $interactive['type'] ?? null; // 'button' or 'list_reply'
                            if ($iType === 'button' && isset($interactive['button_reply']['title'])) {
                                $text = $interactive['button_reply']['title'];
                            } elseif ($iType === 'list_reply' && isset($interactive['list_reply']['title'])) {
                                $text = $interactive['list_reply']['title'];
                            }
                        } elseif ($type === 'image') {
                            $text = $msg['image']['caption'] ?? null;
                        } elseif ($type === 'button' && isset($msg['button']['text'])) {
                            $text = $msg['button']['text'];
                        }

                        Log::info('Inbound WhatsApp message extracted (slug endpoint)', [
                            'org_id' => $org->id,
                            'from' => $from,
                            'type' => $type,
                            'has_text' => $text !== null && trim((string)$text) !== '',
                            'text_preview' => $text ? substr((string)$text, 0, 180) : null
                        ]);

                        if (!$from || !$text) { continue; }

                        // Idempotency check
                        if ($waMessageId) {
                            $already = ChatMessage::where('metadata->wa_message_id', $waMessageId)->exists();
                            if ($already) {
                                Log::info('Skipping duplicate WhatsApp message (idempotent, slug endpoint)', ['wa_message_id' => $waMessageId]);
                                continue;
                            }
                        }

                        // We intend to reply; show typing indicator
                        try {
                            $waSvcTmp = app(WhatsappService::class);
                            $waSvcTmp->sendTypingIndicator($waMessageId, 'text', $phoneNumberId, null);
                        } catch (\Throwable $te) {
                            Log::warning('WhatsApp immediate feedback failed (slug endpoint)', ['error' => $te->getMessage()]);
                        }

                        // Persist/associate conversation with explicit org
                        $contactName = null;
                        try {
                            if (!empty($value['contacts'][0]['profile']['name'])) {
                                $contactName = $value['contacts'][0]['profile']['name'];
                            }
                        } catch (\Throwable $t) { /* ignore */ }

                        $conversation = ChatConversation::where('organization_id', $org->id)
                            ->where('visitor_phone', $from)
                            ->where('metadata->source', 'whatsapp')
                            ->first();
                        if (!$conversation) {
                            $conversation = ChatConversation::create([
                                'conversation_id' => 'wa:' . ($phoneNumberId ?: 'unknown') . ':' . $from,
                                'organization_id' => $org->id,
                                'visitor_phone' => $from,
                                'visitor_name' => $contactName,
                                'status' => 'active',
                                'metadata' => [
                                    'source' => 'whatsapp',
                                    'phone_number_id' => $phoneNumberId,
                                    'waba_display_phone' => $metadata['display_phone_number'] ?? null,
                                ],
                                'last_activity_at' => now(),
                            ]);
                        } else {
                            $conversation->update(['last_activity_at' => now()]);
                        }

                        if ($conversation) {
                            ChatMessage::create([
                                'conversation_id' => $conversation->id,
                                'sender_type' => 'user',
                                'sender_name' => $contactName,
                                'message' => $text,
                                'metadata' => [
                                    'source' => 'whatsapp',
                                    'wa_message_id' => $waMessageId,
                                    'type' => $type,
                                    'raw' => $msg,
                                ],
                                'sent_at' => now(),
                            ]);
                        }

                        // Build AI reply (slug endpoint) aligned with widget behavior
                        $ai = app(AiAgentService::class);
                        Log::info('WhatsApp LLM search prep (slug endpoint)', [
                            'org_slug' => $org->slug,
                            'phone_number_id' => $phoneNumberId,
                            'query_preview' => substr((string)$text, 0, 180)
                        ]);
                        $embedding = $ai->embed($text);
                        $answer = null;
                        $usedContextChars = 0;
                        if ($embedding) {
                            $collection = $org->slug;
                            $search = $ai->searchQdrant($collection, $embedding, 5) ?: [];
                            $context = '';
                            $maxContextChars = 1500;
                            foreach (($search['results'] ?? []) as $res) {
                                $payloadRes = $res['payload'] ?? [];
                                $relevantFields = ['title', 'content', 'answer', 'description'];
                                foreach ($relevantFields as $field) {
                                    if (isset($payloadRes[$field]) && is_string($payloadRes[$field]) && !empty($payloadRes[$field])) {
                                        $fieldContent = ucfirst($field) . ': ' . $payloadRes[$field] . "\n";
                                        if (strlen($context . $fieldContent) > $maxContextChars) {
                                            break 2;
                                        }
                                        $context .= $fieldContent;
                                    }
                                }
                                $context .= "\n";
                                if (strlen($context) > $maxContextChars) { break; }
                            }
                            $usedContextChars = strlen($context);

                            // Compose system prompt with official org contacts and widget-style rules
                            $orgWebsite = $org->website ?: config('app.url');
                            $orgEmail = $org->contact_email ?? null;
                            $orgPhone = $org->contact_phone ?? null;
                            $orgDesc  = $org->description ? trim(preg_replace('/\s+/', ' ', strip_tags($org->description))) : null;
                            $assistantName = $org->settings['assistant_display_name'] ?? 'AI Assistant';
                            $system = "You are {$assistantName}, a helpful customer service assistant for {$org->name}. ";
                            if ($orgDesc) { $system .= "About: {$orgDesc}. "; }
                            $system .= "\nOrganization info:\n";
                            $system .= "- Official website: {$orgWebsite}\n";
                            $system .= $orgEmail ? "- Official email: {$orgEmail}\n" : "- Official email: (not provided)\n";
                            $system .= $orgPhone ? "- Official phone: {$orgPhone}\n" : "- Official phone: (not provided)\n";
                            if ($context) { $system .= "Use this info:\n{$context}\n"; }
                            $system .= "Rules: Only use the official website/email/phone above. Do not invent or guess any contact details. If an official contact is not provided, direct the user to the official website instead. ";
                            $system .= "Keep responses concise and direct. Always use plain text only - no HTML or markdown. Include full https URLs when mentioning sites. Do not mention WhatsApp or any specific messaging platform unless the user explicitly asks about it.";

                            $chatMessages = [
                                ['role' => 'system', 'content' => $system],
                                ['role' => 'user', 'content' => $text],
                            ];

                            // Use provider/model selection consistent with widget
                            $provider = $ai->getAiProviderForOrganization($org->id);
                            if ($provider === 'openai') {
                                $model = $ai->getOpenAiModelForOrganization($org->id);
                                $resp = $ai->openAiChat($chatMessages, $model, null, $org->id);
                            } else {
                                $model = $ai->getLlamaModelForOrganization($org->id);
                                $resp = $ai->llmChat($chatMessages, $model, null, $org->id);
                            }
                            $answer = $resp['message']['content'] ?? null;
                        }
                        if (!$answer || trim($answer) === '') {
                            Log::warning('LLM returned empty answer; using default fallback (slug endpoint)', [
                                'org_id' => $org->id, 'from' => $from, 'context_chars' => $usedContextChars
                            ]);
                            $answer = "Thanks for your message. Our assistant will get back to you shortly.";
                        }

                        // Ensure WhatsApp receives plain text with linkified URLs (no HTML)
                        $answer = $this->toWhatsappPlainText($answer);

                        // Send reply with org-scoped credentials
                        $svc = app(WhatsappService::class);
                        $orgAccessToken = $org->settings['whatsapp_access_token'] ?? null;
                        try {
                            $svc->sendText($from, $answer, $orgPhoneId ?: $phoneNumberId, $orgAccessToken);
                            Log::info('WhatsApp auto-reply sent (slug endpoint)', [
                                'to' => $from,
                                'org_id' => $org->id,
                                'answer_len' => strlen($answer)
                            ]);
                            if (isset($conversation)) {
                                $assistantName = $org->settings['assistant_display_name'] ?? 'AI Assistant';
                                ChatMessage::create([
                                    'conversation_id' => $conversation->id,
                                    'sender_type' => 'ai',
                                    'sender_name' => $assistantName,
                                    'message' => $answer,
                                    'metadata' => [
                                        'source' => 'whatsapp',
                                        'direction' => 'outbound'
                                    ],
                                    'sent_at' => now(),
                                ]);
                                $conversation->update(['last_activity_at' => now()]);
                            }
                        } catch (\App\Exceptions\WhatsappTokenExpiredException $e) {
                            $settings = $org->settings ?? [];
                            $settings['whatsapp_token_expired'] = true;
                            $org->settings = $settings;
                            $org->save();
                            Log::error('WhatsApp org token expired (slug endpoint); attempting admin fallback', ['org_id' => $org->id, 'error' => $e->getMessage()]);
                            try {
                                $svc->sendText($from, $answer, null, null);
                                Log::info('WhatsApp auto-reply sent via admin fallback (slug endpoint)', ['to' => $from, 'org_id' => $org->id]);
                            } catch (\Throwable $e2) {
                                Log::error('Fallback send via admin token failed (slug endpoint)', ['error' => $e2->getMessage()]);
                            }
                        } catch (\Throwable $e) {
                            Log::error('Failed to send WhatsApp reply (slug endpoint)', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing error (slug endpoint)', ['error' => $e->getMessage(), 'org_slug' => $org->slug]);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Convert any HTML-ish content into WhatsApp-safe plain text while preserving URLs.
     * - Anchor tags become: "Text (https://example.com)" or just the URL if no text
     * - Line breaks preserved for <br> and block elements
     * - All other tags stripped; entities decoded; whitespace normalized
     */
    private function toWhatsappPlainText($html): string
    {
        if ($html === null) { return ''; }
        $text = (string) $html;

        // Normalize common block/line-break tags before stripping
        $replacements = [
            '/<\s*br\s*\/?\s*>/i' => "\n",
            '/<\/(p|div|h[1-6]|li|ul|ol|blockquote)\s*>/i' => "\n",
            '/<\s*li\s*>/i' => "- ",
        ];
        foreach ($replacements as $pattern => $rep) {
            $text = preg_replace($pattern, $rep, $text);
        }

        // Convert anchors to "text (URL)" or just URL
        $text = preg_replace_callback(
            '/<a\s+[^>]*href=["\']?([^"\'>\s]+)["\']?[^>]*>(.*?)<\/a>/is',
            function ($m) {
                $href = $m[1] ?? '';
                $label = trim(strip_tags($m[2] ?? ''));
                if ($label === '' || strcasecmp($label, $href) === 0) {
                    return $href;
                }
                return $label . ' (' . $href . ')';
            },
            $text
        );

        // Strip remaining tags and decode entities
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Normalize whitespace and newlines
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Collapse 3+ blank lines to max 2
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        // Collapse excessive spaces
        $text = preg_replace('/[\t ]{2,}/', ' ', $text);

        return trim($text);
    }
}

