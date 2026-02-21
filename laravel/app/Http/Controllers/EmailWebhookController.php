<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;

class EmailWebhookController extends Controller
{
    public function handle(string $provider, Request $request)
    {
        $payload = $request->all();
        $eventData = $payload['event-data'] ?? $payload['eventData'] ?? [];
        if (is_string($eventData)) {
            $decoded = json_decode($eventData, true);
            $eventData = is_array($decoded) ? $decoded : [];
        }

        $token = $payload['token']
            ?? $payload['tracking_token']
            ?? data_get($payload, 'user-variables.tracking_token')
            ?? data_get($payload, 'user_variables.tracking_token')
            ?? data_get($eventData, 'user-variables.tracking_token')
            ?? data_get($eventData, 'user_variables.tracking_token')
            ?? null;

        $event = $payload['event']
            ?? $payload['type']
            ?? $payload['event_type']
            ?? data_get($eventData, 'event')
            ?? null;

        $messageId = $payload['message_id']
            ?? $payload['message-id']
            ?? $payload['Message-ID']
            ?? data_get($eventData, 'message.headers.message-id')
            ?? data_get($eventData, 'message.headers.Message-Id')
            ?? data_get($eventData, 'Message-Id')
            ?? null;

        if (is_string($messageId)) {
            $messageId = trim($messageId, '<>');
        }

        if (!$token && $messageId) {
            $recipient = EmailCampaignRecipient::where('message_id', $messageId)
                ->orWhere('message_id', '<' . $messageId . '>')
                ->first();
        } else {
            $recipient = $token ? EmailCampaignRecipient::where('tracking_token', $token)->first() : null;
        }

        if ($recipient) {
            $recipient->provider = $provider;
            $recipient->message_id = $recipient->message_id ?: $messageId;
            $recipient->last_event = $event ?: $recipient->last_event;
            $recipient->last_event_at = now();

            if (in_array($event, ['accepted', 'queued', 'processed'], true)) {
                $recipient->status = 'sent';
                $recipient->delivery_status = 'sent';
            }

            if (in_array($event, ['delivered', 'delivery'], true)) {
                $recipient->status = 'sent';
                $recipient->delivered_at = $recipient->delivered_at ?: now();
                $recipient->delivery_status = 'delivered';
            }

            if (in_array($event, ['opened', 'open'], true)) {
                $recipient->opened_at = $recipient->opened_at ?: now();
                $recipient->last_opened_at = now();
                $recipient->open_count = ($recipient->open_count ?? 0) + 1;
            }

            if (in_array($event, ['clicked', 'click'], true)) {
                $recipient->clicked_at = $recipient->clicked_at ?: now();
                $recipient->last_clicked_at = now();
                $recipient->click_count = ($recipient->click_count ?? 0) + 1;
            }

            if (in_array($event, ['failed', 'bounced', 'bounce', 'dropped', 'rejected', 'complained'], true)) {
                $recipient->status = 'failed';
                $recipient->delivery_status = $event;
            }

            $recipient->save();
        }

        return response()->json(['ok' => true]);
    }
}
