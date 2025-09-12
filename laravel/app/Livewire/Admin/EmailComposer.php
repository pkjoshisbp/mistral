<?php

namespace App\Livewire\Admin;

use App\Models\EmailCampaign;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class EmailComposer extends Component
{
    public $subject = '';
    public $content = '';
    public $sender_email = '';
    public $sender_name = '';
    public $recipients = '';
    public $bcc_recipients = '';
    public $showPreview = false;
    public $recipientList = [];
    public $bccList = [];

    protected $rules = [
        'subject' => 'required|string|max:255',
        'content' => 'required|string',
        'sender_email' => 'required|email',
        'recipients' => 'required|string',
    ];

    public function mount()
    {
        $this->sender_email = auth()->user()->email ?? '';
        $this->sender_name = auth()->user()->name ?? '';
    }

    public function render()
    {
        return view('livewire.admin.email-composer')->layout('layouts.admin');
    }

    public function preview()
    {
        $this->validate();
        
        // Process recipients
        $this->recipientList = array_filter(array_map('trim', explode(',', $this->recipients)));
        $this->bccList = $this->bcc_recipients ? array_filter(array_map('trim', explode(',', $this->bcc_recipients))) : [];
        
        $this->showPreview = true;
    }

    public function closePreview()
    {
        $this->showPreview = false;
    }

    public function sendEmail()
    {
        $this->validate();

        try {
            // Process recipients
            $this->recipientList = array_filter(array_map('trim', explode(',', $this->recipients)));
            $this->bccList = $this->bcc_recipients ? array_filter(array_map('trim', explode(',', $this->bcc_recipients))) : [];

            // Create campaign record for tracking
            $campaign = EmailCampaign::create([
                'name' => 'Quick Email - ' . date('Y-m-d H:i:s'),
                'subject' => $this->subject,
                'content' => $this->content,
                'template_id' => null,
                'recipients' => $this->recipientList,
                'bcc_recipients' => $this->bccList,
                'sender_email' => $this->sender_email,
                'sender_name' => $this->sender_name,
                'status' => 'sending',
                'total_recipients' => count($this->recipientList),
                'created_by' => auth()->id(),
            ]);

            // Send emails
            $sentCount = 0;
            $failedCount = 0;

            foreach ($this->recipientList as $recipient) {
                try {
                    Mail::send([], [], function ($message) use ($recipient, $campaign) {
                        $message->to($recipient)
                                ->subject($campaign->subject)
                                ->from($campaign->sender_email, $campaign->sender_name)
                                ->html($campaign->content);

                        // Add BCC if specified
                        if (!empty($this->bccList)) {
                            $message->bcc($this->bccList);
                        }
                    });
                    
                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    \Log::error('Email sending failed: ' . $e->getMessage());
                }
            }

            // Update campaign status
            $campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'sent_at' => now(),
            ]);

            session()->flash('success', "Email sent successfully! Sent: {$sentCount}, Failed: {$failedCount}");
            $this->resetForm();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    private function resetForm()
    {
        $this->subject = '';
        $this->content = '';
        $this->recipients = '';
        $this->bcc_recipients = '';
        $this->recipientList = [];
        $this->bccList = [];
        $this->showPreview = false;
    }
}
