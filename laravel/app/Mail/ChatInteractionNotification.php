<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChatInteractionNotification extends Mailable
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
        $orgEmail = $this->payload['organization']->contact_email ?? null;
        $subject = "New Chat Interaction - {$orgName}";

        $mail = $this->subject($subject)
            ->view('emails.chat-interaction-notification')
            ->text('emails.chat-interaction-notification-text')
            ->with($this->payload);

        if (!empty($orgEmail)) {
            $mail->replyTo($orgEmail, $orgName);
        }

        return $mail;
    }
}
