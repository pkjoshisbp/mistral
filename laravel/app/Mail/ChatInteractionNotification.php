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

        $fromAddress = config('mail.from.address');
        $fromName = config('mail.from.name');

        $mail = $this->subject($subject)
            ->from($fromAddress, $fromName)
            ->view('emails.chat-interaction-notification')
            ->text('emails.chat-interaction-notification-text')
            ->with($this->payload);

        $replyTo = $this->payload['reply_to'] ?? null;
        if (!empty($replyTo)) {
            $mail->replyTo($replyTo, $orgName);
        } elseif (!empty($orgEmail)) {
            $mail->replyTo($orgEmail, $orgName);
        }

        $mail->withSymfonyMessage(function ($message) use ($fromAddress) {
            $headers = $message->getHeaders();
            $headers->addTextHeader('List-Unsubscribe', "<mailto:{$fromAddress}>");
            $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        });

        return $mail;
    }
}
