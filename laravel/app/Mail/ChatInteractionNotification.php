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
        $subject = "New Chat Interaction - {$orgName}";

        return $this->subject($subject)
            ->view('emails.chat-interaction-notification')
            ->with($this->payload);
    }
}
