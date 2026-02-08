<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;

class EmailWebhookController extends Controller
{
    public function handle(string $provider, Request $request)
    {
        $payload = $request->all();
        $token = $payload['token'] ?? $payload['tracking_token'] ?? null;
        $event = $payload['event'] ?? $payload['type'] ?? $payload['event_type'] ?? null;
        $messageId = $payload['message_id'] ?? $payload['message-id'] ?? $payload['Message-ID'] ?? null;

        if (!$token && $messageId) {
            $recipient = EmailCampaignRecipient::where('message_id', $messageId)->first();
        } else {
            $recipient = $token ? EmailCampaignRecipient::where('tracking_token', $token)->first() : null;
        }

        if ($recipient) {
            $recipient->provider = $provider;
            $recipient->message_id = $recipient->message_id ?: $messageId;
            $recipient->last_event = $event ?: $recipient->last_event;
            $recipient->last_event_at = now();

            if (in_array($event, ['delivered', 'delivery', 'accepted'], true)) {
                $recipient->delivered_at = $recipient->delivered_at ?: now();
                $recipient->delivery_status = $event;
            }

            $recipient->save();
        }

        return response()->json(['ok' => true]);
    }
}
