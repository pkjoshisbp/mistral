<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ChatInteractionDigestNotification extends Mailable
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
        $sessionId = $this->payload['conversation']->conversation_id ?? null;
        $subject = $sessionId ? "Chat Digest - {$orgName} (Session {$sessionId})" : "Chat Digest - {$orgName}";

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $mail = $this->subject($subject)
            ->from($fromAddress, $fromName)
            ->view('emails.chat-interaction-digest-notification')
            ->text('emails.chat-interaction-digest-notification-text')
            ->with($this->payload);



        $mail->withSymfonyMessage(function ($message) use ($fromAddress) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('List-Unsubscribe', "<mailto:{$fromAddress}>");
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        });

        return $mail;
    }
}
