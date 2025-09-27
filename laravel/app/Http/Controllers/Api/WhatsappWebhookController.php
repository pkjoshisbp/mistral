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
    public function verify(Request $request)
    {
        $verifyToken = AdminSetting::get('whatsapp_verify_token', '');
        if ($request->hub_mode === 'subscribe' && $request->hub_verify_token === $verifyToken) {
            return response($request->hub_challenge, 200);
        }
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

                        // Resolve organization by phone_number_id if any org stores it in settings
                        $org = Organization::where('settings->whatsapp_phone_number_id', $phoneNumberId)->first();
                        if (!$org) {
                            // Fallback: use default org from settings if configured (admin-wide phone id)
                            $org = Organization::find(3); // safe fallback to demo org
                        }

                        // Respect per-organization auto-reply toggle (default true)
                        $autoReply = $org ? ($org->settings['whatsapp_auto_reply'] ?? true) : true;
                        if ($autoReply === false) {
                            Log::info('WhatsApp auto-reply disabled for org; skipping response', ['org_id' => $org?->id, 'from' => $from]);
                            continue;
                        }

                        // Persist/associate conversation before AI processing
                        $contactName = null;
                        try {
                            if (!empty($value['contacts'][0]['profile']['name'])) {
                                $contactName = $value['contacts'][0]['profile']['name'];
                            }
                        } catch (\Throwable $t) { /* ignore */ }

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

                        // Build contexted AI reply
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
                            foreach (($search['results'] ?? []) as $res) {
                                $payloadRes = $res['payload'] ?? [];
                                foreach ($payloadRes as $k => $v) {
                                    if (is_string($v) && $k !== 'org_id' && !empty($v)) {
                                        $context .= ucfirst($k) . ': ' . $v . "\n";
                                    }
                                }
                                $context .= "\n";
                            }
                            $usedContextChars = strlen($context);

                            // Prefer chat endpoint for better formatting and reliability
                            $chatMessages = [];
                            $system = "You are a helpful WhatsApp assistant for {$org->name}. Keep replies short and friendly.\n";
                            if ($context) { $system .= "Context:\n{$context}"; }
                            $chatMessages[] = ['role' => 'system', 'content' => $system];
                            $chatMessages[] = ['role' => 'user', 'content' => $text];

                            $resp = $ai->llmChat($chatMessages, null, null, $org->id ?? null);
                            $answer = $resp['message']['content'] ?? null;
                        }
                        if (!$answer || trim($answer) === '') {
                            Log::warning('LLM returned empty answer; using default fallback', [
                                'org_id' => $org?->id, 'from' => $from, 'context_chars' => $usedContextChars
                            ]);
                            $answer = "Thanks for your message. Our assistant will get back to you shortly.";
                        }

                        // Send the reply using either org-specific token/phone or admin defaults
                        $svc = app(WhatsappService::class);
                        $orgPhoneId = $org->settings['whatsapp_phone_number_id'] ?? null;
                        $orgToken = $org->settings['whatsapp_access_token'] ?? null;
                        try {
                            $svc->sendText($from, $answer, $orgPhoneId ?: $phoneNumberId, $orgToken);
                            Log::info('WhatsApp auto-reply sent', [
                                'to' => $from,
                                'org_id' => $org?->id,
                                'answer_len' => strlen($answer)
                            ]);
                            if (isset($conversation)) {
                                ChatMessage::create([
                                    'conversation_id' => $conversation->id,
                                    'sender_type' => 'ai',
                                    'sender_name' => 'AI Assistant',
                                    'message' => $answer,
                                    'metadata' => [
                                        'source' => 'whatsapp',
                                        'direction' => 'outbound'
                                    ],
                                    'sent_at' => now(),
                                ]);
                                $conversation->update(['last_activity_at' => now()]);
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
}
