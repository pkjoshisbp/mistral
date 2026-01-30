<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailgunInboundController extends Controller
{
    public function handle(Request $request)
    {
        if (!$this->isValidSignature($request)) {
            return response()->json(['status' => 'invalid_signature'], 403);
        }

        $recipient = strtolower((string) $request->input('recipient'));
        $subject = (string) $request->input('subject');
        $from = (string) ($request->input('from') ?: $request->input('sender'));
        $senderEmail = (string) $request->input('sender');
        $message = (string) ($request->input('stripped-text') ?: $request->input('body-plain'));
        $message = trim($message);

        if ($message === '') {
            return response()->json(['status' => 'empty_message'], 200);
        }

        $conversationId = $this->extractConversationId($recipient, $subject);
        if (!$conversationId) {
            Log::warning('Mailgun inbound: missing conversation id', [
                'recipient' => $recipient,
                'subject' => $subject,
            ]);
            return response()->json(['status' => 'missing_conversation'], 202);
        }

        $conversation = ChatConversation::where('conversation_id', $conversationId)->first();
        if (!$conversation) {
            Log::warning('Mailgun inbound: conversation not found', [
                'conversation_id' => $conversationId,
            ]);
            return response()->json(['status' => 'conversation_not_found'], 202);
        }

        $senderName = $this->extractSenderName($from, $senderEmail);

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_name' => $senderName,
            'message' => $message,
            'sent_at' => now(),
            'metadata' => [
                'channel' => 'mailgun',
                'sender_email' => $senderEmail ?: $from,
            ]
        ]);

        $conversation->update([
            'agent_status' => 'agent_active',
            'assigned_agent_id' => $conversation->assigned_agent_id,
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        return response()->json(['status' => 'ok'], 200);
    }

    private function extractConversationId(string $recipient, string $subject): ?string
    {
        if (preg_match('/ai-chat-support\+([^@]+)@/i', $recipient, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Session(?:\s*ID)?:\s*([\w\-]+)/i', $subject, $matches)) {
            return $matches[1];
        }

        if (preg_match('/Conversation(?:\s*ID)?:\s*([\w\-]+)/i', $subject, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function extractSenderName(string $from, string $senderEmail): string
    {
        if ($from && preg_match('/^([^<]+)</', $from, $matches)) {
            return trim($matches[1]);
        }

        if ($senderEmail) {
            return $senderEmail;
        }

        return 'Support Agent';
    }

    private function isValidSignature(Request $request): bool
    {
        $signingKey = env('MAILGUN_SIGNING_KEY');
        if (!$signingKey) {
            return true;
        }

        $timestamp = $request->input('timestamp');
        $token = $request->input('token');
        $signature = $request->input('signature');

        if (!$timestamp || !$token || !$signature) {
            return false;
        }

        if (abs(time() - (int) $timestamp) > 900) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $token, $signingKey);
        return hash_equals($expected, $signature);
    }
}
