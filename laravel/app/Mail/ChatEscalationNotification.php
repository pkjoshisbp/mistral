<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChatEscalationNotification extends Mailable
{
    use Queueable, SerializesModels;

    public array $payload;

    public function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public function build()
    {
        $orgName = $this->payload['organization']->name ?? 'Organization';
        $subject = "Chat Escalation - {$orgName}";

        $conversationId = $this->payload['conversation']?->conversation_id ?? null;
        if ($conversationId) {
            $subject .= " (Session: {$conversationId})";
        }

        $mail = $this->subject($subject)
            ->view('emails.chat-escalation-notification')
            ->text('emails.chat-escalation-notification-text')
            ->with($this->payload);

        $replyTo = $this->payload['reply_to'] ?? null;
        if (!empty($replyTo)) {
            $mail->replyTo($replyTo, $orgName);
        } else {
            $orgEmail = $this->payload['organization']->contact_email ?? null;
            if (!empty($orgEmail)) {
                $mail->replyTo($orgEmail, $orgName);
            }
        }

        return $mail;
    }
}
