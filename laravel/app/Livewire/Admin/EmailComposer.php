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
                    $trackingToken = bin2hex(random_bytes(16));
                    $trackedContent = $this->buildTrackedContent($campaign->content, $trackingToken);
                    Mail::send([], [], function ($message) use ($recipient, $campaign, $trackedContent, $trackingToken) {
                        $message->to($recipient)
                                ->subject($campaign->subject)
                                ->from($campaign->sender_email, $campaign->sender_name)
                                ->html($trackedContent);
                        $message->getSymfonyMessage()
                                ->getHeaders()
                                ->addTextHeader('X-AICS-Tracking-Token', $trackingToken);

                        // Add BCC if specified
                        if (!empty($this->bccList)) {
                            $message->bcc($this->bccList);
                        }
                    });
                    
                    $campaign->recipients()->create([
                        'organization_id' => null,
                        'recipient_email' => $recipient,
                        'variables' => [],
                        'status' => 'sent',
                        'sent_at' => now(),
                        'tracking_token' => $trackingToken,
                        'resend_count' => 0,
                        'last_sent_at' => now(),
                        'next_resend_at' => now()->addDays(7),
                    ]);
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

    protected function buildTrackedContent(string $content, string $token): string
    {
        $content = $this->applyEmailTypography($content);
        $baseUrl = rtrim(config('app.url'), '/');
        $pixel = '<img src="' . $baseUrl . '/email/open/' . $token . '.png" width="1" height="1" style="display:none" alt="" />';

        if (stripos($content, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel . '</body>', $content, 1);
        }

        return $content . $pixel;
    }

    protected function applyEmailTypography(string $content): string
    {
        $fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        $baseStyle = 'font-family: ' . $fontFamily . '; font-size: 16px; line-height: 1.6;';

        if (preg_match('/font-family:/i', $content)) {
            $content = preg_replace(
                '/font-family:\s*[^;"\']*arial[^;"\']*;?/i',
                'font-family: ' . $fontFamily . '; font-size: 16px; line-height: 1.6;',
                $content
            );
        } else {
            $content = '<div style="' . $baseStyle . '">' . $content . '</div>';
        }

        $content = preg_replace_callback('/<body([^>]*)>/i', function ($m) use ($baseStyle) {
            $attrs = $m[1] ?? '';
            if (stripos($attrs, 'style=') !== false) {
                return preg_replace('/style=("|\')(.*?)\1/i', 'style="$2 ' . $baseStyle . '"', $m[0], 1);
            }
            return '<body' . $attrs . ' style="' . $baseStyle . '">';
        }, $content);

        return $content;
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
