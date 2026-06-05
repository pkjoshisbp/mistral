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

                        $resolvedInbound = $this->resolveInboundWhatsappQuery((string) $text, $org, $conversation);
                        $searchText = $resolvedInbound['query'];
                        $seedApplied = (bool) ($resolvedInbound['seed_applied'] ?? false);

                        // Build contexted AI reply aligned with widget behavior
                        $ai = app(AiAgentService::class);
                        $actionService = app(\App\Services\ActionService::class);
                        Log::info('WhatsApp LLM search prep', [
                            'org_slug' => $org?->slug,
                            'phone_number_id' => $phoneNumberId,
                            'query_preview' => substr((string)$searchText, 0, 180),
                            'seed_applied' => $seedApplied,
                        ]);
                        
                        $answer = null;
                        $usedContextChars = 0;
                        $context = '';
                        
                        if ($org) {
                            // First, try to execute database actions (e.g., pricing queries)
                            $actionResult = $actionService->processQuery($searchText, $org->id);
                            $liveData = null;
                            
                            if ($actionResult['type'] === 'action_executed' && isset($actionResult['result']['success']) && $actionResult['result']['success']) {
                                $liveData = $actionResult['result']['data'] ?? null;
                                $actionName = $actionResult['action']['action_name'] ?? 'database query';
                                
                                if ($liveData) {
                                    $context .= "\n[LIVE DATA from {$actionName}]:\n";
                                    $context .= json_encode($liveData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
                                    $context .= "[END LIVE DATA]\n\n";
                                    $context .= "IMPORTANT: Use ONLY the LIVE DATA above to answer the question. Format pricing in a user-friendly way.\n\n";
                                }
                            }
                            
                            // Then search Qdrant for additional context (only if no live data or as supplement)
                            if (!$liveData) {
                                $embedding = $ai->embed($searchText);
                                if ($embedding) {
                                    $collection = $org->slug;
                                    $search = $ai->searchQdrant($collection, $embedding, 5) ?: [];
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
                                }
                            }
                            $contextRelevance = $this->applyKnowledgeContextRelevanceGate(
                                $org,
                                (string) $searchText,
                                (string) $context,
                                $ai,
                                $conversation?->conversation_id,
                                'whatsapp_default'
                            );
                            $context = $contextRelevance['context'];
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
                            if ($context) { $system .= "CURRENT CONTEXT:\n{$context}\n"; }
                            $system .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity. ";
                            $system .= $this->buildContextRelevanceInstruction($orgWebsite, $orgEmail, $orgPhone) . ' ';
                            $system .= "Rules: Only use the official website/email/phone above. Do not invent or guess any contact details. If an official contact is not provided, direct the user to the official website instead. ";
                            $system .= "Keep responses concise and direct. Always use plain text only - no HTML or markdown. Include full https URLs when mentioning sites. Do not mention WhatsApp or any specific messaging platform unless the user explicitly asks about it.";

                            $chatMessages = $this->buildWhatsAppChatMessages($org, $conversation, $system, $searchText, $context);

                            // Use the same provider/model selection as widget
                            $provider = $ai->getAiProviderForOrganization($org->id ?? null);
                            if ($provider === 'openai') {
                                $model = $ai->getOpenAiModelForOrganization($org->id ?? null);
                                $resp = $ai->openAiChat($chatMessages, $model, null, $org->id ?? null, null, [
                                    'num_predict' => 350,
                                ]);
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

                        $resolvedInbound = $this->resolveInboundWhatsappQuery((string) $text, $org, $conversation);
                        $searchText = $resolvedInbound['query'];
                        $seedApplied = (bool) ($resolvedInbound['seed_applied'] ?? false);

                        // Build AI reply (slug endpoint) aligned with widget behavior
                        $ai = app(AiAgentService::class);
                        Log::info('WhatsApp LLM search prep (slug endpoint)', [
                            'org_slug' => $org->slug,
                            'phone_number_id' => $phoneNumberId,
                            'query_preview' => substr((string)$searchText, 0, 180),
                            'seed_applied' => $seedApplied,
                        ]);
                        $embedding = $ai->embed($searchText);
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
                            $contextRelevance = $this->applyKnowledgeContextRelevanceGate(
                                $org,
                                (string) $searchText,
                                (string) $context,
                                $ai,
                                $conversation?->conversation_id,
                                'whatsapp_slug'
                            );
                            $context = $contextRelevance['context'];
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
                            if ($context) { $system .= "CURRENT CONTEXT:\n{$context}\n"; }
                            $system .= "Always ground factual answers in CURRENT CONTEXT. Use PRIOR HISTORY only to resolve references or maintain continuity. ";
                            $system .= $this->buildContextRelevanceInstruction($orgWebsite, $orgEmail, $orgPhone) . ' ';
                            $system .= "Rules: Only use the official website/email/phone above. Do not invent or guess any contact details. If an official contact is not provided, direct the user to the official website instead. ";
                            $system .= "Keep responses concise and direct. Always use plain text only - no HTML or markdown. Include full https URLs when mentioning sites. Do not mention WhatsApp or any specific messaging platform unless the user explicitly asks about it.";

                            $chatMessages = $this->buildWhatsAppChatMessages($org, $conversation, $system, $searchText, $context);

                            // Use provider/model selection consistent with widget
                            $provider = $ai->getAiProviderForOrganization($org->id);
                            if ($provider === 'openai') {
                                $model = $ai->getOpenAiModelForOrganization($org->id);
                                $resp = $ai->openAiChat($chatMessages, $model, null, $org->id, null, [
                                    'num_predict' => 350,
                                ]);
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

    private function buildWhatsAppChatMessages(Organization $organization, ?ChatConversation $conversation, string $systemPrompt, string $message, string $context = ''): array
    {
        $hasContext = trim($context) !== '';
        $includeHistory = false;
        $historyLimit = 0;

        if ($this->isShortFollowUp($message)) {
            $includeHistory = true;
            $historyLimit = 4;
        }

        if (!$hasContext) {
            $includeHistory = true;
            $historyLimit = max($historyLimit, 4);
        }

        if ($this->isAffirmativeFollowUp($message) && $this->isPreviousUserAffirmative($conversation)) {
            $includeHistory = true;
            $historyLimit = max($historyLimit, 10);
        }

        if ($includeHistory) {
            $recentMessages = $this->getRecentConversationMessages($conversation, $historyLimit);
            if (!empty($recentMessages)) {
                $systemPrompt .= "\n\nPRIOR HISTORY (use only if the user explicitly refers to it):\n";
                foreach ($recentMessages as $rm) {
                    $label = $rm['role'] === 'user' ? 'User' : 'Assistant';
                    $systemPrompt .= $label . ": " . $rm['content'] . "\n";
                }
            }
        }

        $systemPrompt .= "\nCURRENT QUERY:\n" . $message . "\n";

        return [
            ['role' => 'system', 'content' => trim($systemPrompt)],
            ['role' => 'user', 'content' => $message],
        ];
    }

    /**
     * @return array{context:string,decision:string,confidence:float,threshold:float,reason:string,use_context:bool,model:string}
     */
    private function applyKnowledgeContextRelevanceGate(
        Organization $organization,
        string $question,
        string $context,
        AiAgentService $ai,
        ?string $sessionId = null,
        string $channel = 'whatsapp'
    ): array {
        $question = trim($question);
        $context = trim($context);

        if ($question === '' || $context === '') {
            return [
                'context' => $context,
                'decision' => 'unknown',
                'confidence' => 0.0,
                'threshold' => $ai->getContextRelevanceMinConfidence(),
                'reason' => 'Question or context was empty, so the gate was skipped.',
                'use_context' => true,
                'model' => '',
            ];
        }

        if ($ai->getAiProviderForOrganization($organization->id) === 'openai') {
            Log::info('WhatsApp knowledge context relevance deferred to OpenAI', [
                'channel' => $channel,
                'org_id' => $organization->id,
                'session_id' => $sessionId,
            ]);

            return [
                'context' => $context,
                'decision' => 'deferred_to_openai',
                'confidence' => 0.0,
                'threshold' => $ai->getContextRelevanceMinConfidence(),
                'reason' => 'Single OpenAI response performs private context relevance check.',
                'use_context' => true,
                'model' => $ai->getOpenAiModelForOrganization($organization->id),
            ];
        }

        $assessment = $ai->assessContextRelevance(
            $question,
            $context,
            $organization->id,
            $sessionId
        );

        $useContext = (bool) ($assessment['use_context'] ?? true);

        Log::info('WhatsApp knowledge context relevance assessed', [
            'channel' => $channel,
            'org_id' => $organization->id,
            'session_id' => $sessionId,
            'decision' => $assessment['decision'] ?? 'unknown',
            'use_context' => $useContext,
            'confidence' => $assessment['confidence'] ?? 0.0,
            'threshold' => $assessment['threshold'] ?? $ai->getContextRelevanceMinConfidence(),
            'reason' => $assessment['reason'] ?? '',
            'model' => $assessment['model'] ?? null,
        ]);

        return [
            'context' => $useContext ? $context : '',
            'decision' => (string) ($assessment['decision'] ?? 'unknown'),
            'confidence' => (float) ($assessment['confidence'] ?? 0.0),
            'threshold' => (float) ($assessment['threshold'] ?? $ai->getContextRelevanceMinConfidence()),
            'reason' => (string) ($assessment['reason'] ?? ''),
            'use_context' => $useContext,
            'model' => (string) ($assessment['model'] ?? ''),
        ];
    }

    private function buildContextRelevanceInstruction(string $website, ?string $email, ?string $phone): string
    {
        $contactParts = [];
        if ($email) {
            $contactParts[] = 'email ' . $email;
        }
        if ($phone) {
            $contactParts[] = 'phone ' . $phone;
        }
        $contactParts[] = 'website ' . $website;
        $contact = implode(', ', $contactParts);

        return "Before answering, spend a small amount of private reasoning to decide whether CURRENT CONTEXT actually answers the user's question or whether prior conversation already contains enough verified information for a safe follow-up answer. Never reveal chain-of-thought. If context is directly relevant, use it. If prior conversation is enough, use it without inventing new facts. If context is partially relevant, answer only the supported part and say what is missing. If it is not relevant or not enough, ignore it and respond that you do not have enough information right now, then direct the user to the official contact details: {$contact}. Never present unrelated context as if it answers the question.";
    }

    private function getRecentConversationMessages(?ChatConversation $conversation, int $limit = 4): array
    {
        if (!$conversation) {
            return [];
        }

        $recent = $conversation->messages()
            ->orderBy('sent_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();

        $messages = [];
        foreach ($recent as $msg) {
            $text = trim(strip_tags((string) $msg->message));
            if ($text === '') {
                continue;
            }

            $role = $msg->sender_type === 'user' ? 'user' : 'assistant';
            $messages[] = ['role' => $role, 'content' => $text];
        }

        return $messages;
    }

    private function isPreviousUserAffirmative(?ChatConversation $conversation): bool
    {
        if (!$conversation) {
            return false;
        }

        $lastUserMessage = $conversation->messages()
            ->where('sender_type', 'user')
            ->orderBy('sent_at', 'desc')
            ->first();

        if (!$lastUserMessage) {
            return false;
        }

        $text = trim(strip_tags((string) $lastUserMessage->message));
        if ($text === '') {
            return false;
        }

        return $this->isAffirmativeFollowUp($text);
    }

    private function isShortFollowUp(string $message): bool
    {
        $clean = trim(mb_strtolower($message));
        if ($clean === '') {
            return false;
        }

        if ($this->isAffirmativeFollowUp($message)) {
            return true;
        }

        $negatives = [
            'no', 'nope', 'nah', 'not now', 'dont', 'don\'t', 'do not', 'not really', 'no thanks', 'no thank you',
        ];

        if (in_array($clean, $negatives, true)) {
            return true;
        }

        if (str_word_count($clean) <= 3) {
            if (preg_match('/\b(it|that|those|these|this|they|them|there|here|above|previous|earlier|more|details|explain|expand|continue)\b/', $clean)) {
                return true;
            }
            if (preg_match('/^(and|also|what about|how about)\b/', $clean)) {
                return true;
            }
            return false;
        }

        return false;
    }

    private function isAffirmativeFollowUp(string $text): bool
    {
        $t = trim(mb_strtolower($text));
        if ($t === '') {
            return false;
        }
        $affirm = ['yes', 'yeah', 'yup', 'yep', 'ya', 'yah', 'sure', 'certainly', 'ok', 'okay', 'please', 'go ahead', 'go on', 'continue', 'proceed', 'carry on'];
        foreach ($affirm as $a) {
            if ($t === $a) {
                return true;
            }
        }
        $patterns = [
            '/^yes\b.*more/',
            '/\btell me more\b/',
            '/\bmore details\b/',
            '/\bhow it works\b/',
            '/\bexplain more\b/'
        ];
        foreach ($patterns as $re) {
            if (preg_match($re, $t)) {
                return true;
            }
        }
        if (mb_strlen($t) < 16 && preg_match('/\b(yes|yeah|yup|yep|ya|yah|ok|okay|sure|please)\b/', $t)) {
            return true;
        }
        return false;
    }

    private function resolveInboundWhatsappQuery(string $text, ?Organization $organization, ?ChatConversation $conversation): array
    {
        $message = trim($text);
        if ($message === '' || !$this->isAffirmativeFollowUp($message)) {
            return ['query' => $message, 'seed_applied' => false];
        }

        if (!$this->shouldApplyWhatsappSeed($conversation)) {
            return ['query' => $message, 'seed_applied' => false];
        }

        $seed = $this->getWhatsappAffirmativeSeedQuestion($organization);
        if ($seed === '') {
            return ['query' => $message, 'seed_applied' => false];
        }

        return [
            'query' => trim($seed . ' User replied: ' . $message),
            'seed_applied' => true,
        ];
    }

    private function shouldApplyWhatsappSeed(?ChatConversation $conversation): bool
    {
        if (!$conversation) {
            return true;
        }

        $hasAssistantHistory = $conversation->messages()
            ->whereIn('sender_type', ['ai', 'assistant'])
            ->exists();

        return !$hasAssistantHistory;
    }

    private function getWhatsappAffirmativeSeedQuestion(?Organization $organization): string
    {
        $orgSeed = trim((string) data_get($organization?->settings, 'whatsapp_affirmative_seed_question', ''));
        if ($orgSeed !== '') {
            return $orgSeed;
        }

        $globalSeed = trim((string) AdminSetting::get(
            'whatsapp_default_seed_question',
            'Would you like to know more about our services, products, pricing, or latest offers?'
        ));

        return $globalSeed;
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
