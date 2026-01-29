<?php

namespace App\Mail;

use App\Models\Lead;
use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadCapturedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public Lead $lead;
    public Organization $organization;
    public ?array $intent;
    public ?string $message;

    public function __construct(Lead $lead, Organization $organization, ?array $intent = null, ?string $message = null)
    {
        $this->lead = $lead;
        $this->organization = $organization;
        $this->intent = $intent;
        $this->message = $message;
    }

    public function build()
    {
        $subject = 'New Lead: ' . ($this->lead->name ?? 'Visitor');

        return $this->subject($subject)
            ->view('emails.lead-captured-notification');
    }
}
