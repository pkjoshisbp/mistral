<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Support\Carbon;

class EscalationMagicConsole extends Component
{
    public ?ChatConversation $conversation = null;
    public string $replyMessage = '';
    public bool $isValid = false;
    public string $errorMessage = '';
    public string $orgTimezone = 'UTC';
    public int $conversationId = 0;
    public string $token = '';

    public function mount($conversation, $token)
    {
        if ($conversation instanceof \Illuminate\Support\Collection) {
            $conversation = $conversation->first();
        }

        if ($conversation instanceof \Illuminate\Database\Eloquent\Collection) {
            $conversation = $conversation->first();
        }

        if ($conversation instanceof ChatConversation) {
            $conv = $conversation->load(['messages', 'organization']);
        } else {
            $conv = ChatConversation::with(['messages', 'organization'])->find($conversation);
        }
        if (!$conv) {
            $this->setError('This escalation link is invalid.');
            return;
        }

        $this->conversationId = (int) $conv->id;
        $this->token = (string) $token;

        if (!$this->validateMagicLink($conv, $this->token, true)) {
            return;
        }

        $this->conversation = $conv;
        $this->orgTimezone = $conv->organization?->timezone ?: config('app.timezone', 'UTC');
        $this->isValid = true;
    }

    public function refreshConversation()
    {
        if ($this->conversationId <= 0) {
            return;
        }

        $conv = ChatConversation::with(['messages', 'organization'])->find($this->conversationId);
        if (!$conv) {
            $this->setError('This escalation link is invalid.');
            return;
        }

        if (!$this->validateMagicLink($conv, $this->token, false)) {
            return;
        }

        $this->conversation = $conv;
        $this->orgTimezone = $conv->organization?->timezone ?: config('app.timezone', 'UTC');
        $this->isValid = true;
    }

    public function sendAgentReply()
    {
        if (!$this->isValid || !$this->conversation) {
            $this->setError('This escalation link is invalid or expired.');
            return;
        }

        if (!$this->validateMagicLink($this->conversation, $this->token, true)) {
            return;
        }

        $message = trim($this->replyMessage);
        if ($message === '') {
            $this->setError('Reply cannot be empty.');
            return;
        }

        ChatMessage::create([
            'conversation_id' => $this->conversation->id,
            'sender_type' => 'agent',
            'sender_name' => 'Escalation Agent',
            'message' => $message,
            'sent_at' => now(),
            'metadata' => [
                'source' => 'escalation_magic_link',
            ],
        ]);

        $this->conversation->update([
            'agent_status' => 'agent_active',
            'agent_last_active_at' => now(),
            'last_activity_at' => now(),
        ]);

        $this->conversation->refresh()->load(['messages', 'organization']);
        $this->replyMessage = '';
        session()->flash('success', 'Reply sent.');
    }

    private function validateMagicLink(ChatConversation $conv, string $token, bool $touchUsage): bool
    {
        $meta = $conv->metadata ?? [];
        $magic = $meta['escalation_magic'] ?? null;
        $tokenHash = is_array($magic) ? ($magic['token_hash'] ?? null) : null;
        $expiresAtRaw = is_array($magic) ? ($magic['expires_at'] ?? null) : null;

        if (!$tokenHash || !$expiresAtRaw) {
            $this->setError('This escalation link is invalid or expired.');
            return false;
        }

        $expiresAt = Carbon::parse($expiresAtRaw);
        if (now()->greaterThan($expiresAt)) {
            $this->setError('This escalation link has expired.');
            return false;
        }

        $incomingHash = hash('sha256', (string) $token);
        if (!hash_equals($tokenHash, $incomingHash)) {
            $this->setError('This escalation link is invalid.');
            return false;
        }

        if ($touchUsage) {
            $magic['last_used_at'] = now()->toIso8601String();
            $meta['escalation_magic'] = $magic;
            $conv->metadata = $meta;
            $conv->save();
        }

        return true;
    }

    private function setError(string $message): void
    {
        $this->errorMessage = $message;
        $this->isValid = false;
    }

    public function render()
    {
        return view('livewire.public.escalation-magic-console')->layout('layouts.public');
    }
}
