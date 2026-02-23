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
    public $selectedCampaignId = null;

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
        $recentCampaigns = EmailCampaign::query()
            ->withCount([
                'recipients as opened_count' => fn($q) => $q->whereNotNull('opened_at'),
                'recipients as delivered_count' => fn($q) => $q->whereNotNull('delivered_at'),
                'recipients as clicked_count' => fn($q) => $q->whereNotNull('clicked_at'),
            ])
            ->where('name', 'like', 'Quick Email - %')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $selectedCampaignRecipients = collect();
        if ($this->selectedCampaignId) {
            $selectedCampaign = EmailCampaign::query()->find($this->selectedCampaignId);

            $selectedCampaignRecipients = $selectedCampaign
                ? $selectedCampaign->recipients()->orderByDesc('sent_at')->get()
                : collect();
        }

        return view('livewire.admin.email-composer', [
            'recentCampaigns' => $recentCampaigns,
            'selectedCampaignRecipients' => $selectedCampaignRecipients,
        ])->layout('layouts.admin');
    }

    public function viewCampaignReport(int $campaignId): void
    {
        $this->selectedCampaignId = $campaignId;
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
                        $message->getSymfonyMessage()
                                ->getHeaders()
                                ->addTextHeader('X-Mailgun-Variables', json_encode([
                                    'tracking_token' => $trackingToken,
                                    'campaign_id' => $campaign->id,
                                ]));
                    });

                    $campaign->recipients()->create([
                        'organization_id' => null,
                        'recipient_email' => $recipient,
                        'variables' => [],
                        'status' => 'sent',
                        'sent_at' => now(),
                        'delivery_status' => 'sent',
                        'tracking_token' => $trackingToken,
                        'resend_count' => 0,
                        'last_sent_at' => now(),
                        'next_resend_at' => now()->addDays(7),
                        'last_event' => 'sent',
                        'last_event_at' => now(),
                    ]);
                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    \Log::error('Email sending failed: ' . $e->getMessage());
                }
            }

            if (!empty($this->bccList) && $sentCount > 0) {
                $untrackedContent = $this->applyEmailTypography($campaign->content);
                try {
                    Mail::send([], [], function ($message) use ($campaign, $untrackedContent) {
                        $message->to($this->bccList)
                            ->subject('[BCC Copy] ' . $campaign->subject)
                            ->from($campaign->sender_email, $campaign->sender_name)
                            ->html($untrackedContent);
                        $message->getSymfonyMessage()
                            ->getHeaders()
                            ->addTextHeader('X-AICS-BCC-COPY', '1');
                    });
                } catch (\Exception $bccException) {
                    \Log::warning('BCC copy failed: ' . $bccException->getMessage());
                }
            }

            // Update campaign status
            $campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'sent_at' => now(),
            ]);

            $this->selectedCampaignId = $campaign->id;

            session()->flash('success', "Email sent successfully! Sent: {$sentCount}, Failed: {$failedCount}");
            $this->resetForm();
            $this->dispatch('email-composer-clear-editor');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send email: ' . $e->getMessage());
        }
    }

    protected function buildTrackedContent(string $content, string $token): string
    {
        $content = $this->applyEmailTypography($content);
        
        // Wrap all links with tracking URLs
        $content = $this->wrapLinksWithTracking($content, $token);
        
        $baseUrl = rtrim(config('app.url'), '/');
        
        // Multiple tracking pixels for better reliability
        $pixel = '<img src="' . $baseUrl . '/email/open/' . $token . '.png" width="1" height="1" style="display:block;width:1px;height:1px;" alt="" />';
        $hiddenPixel = '<div style="display:none;"><img src="' . $baseUrl . '/email/open/' . $token . '.png" /></div>';
        
        // Insert tracking at multiple positions
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', $pixel . $hiddenPixel . '</body>', $content, 1);
        } else {
            $content .= $pixel . $hiddenPixel;
        }
        
        // Also add at the beginning if there's a body tag
        if (stripos($content, '<body') !== false) {
            $content = preg_replace('/(<body[^>]*>)/i', '$1' . $pixel, $content, 1);
        }

        return $content;
    }
    
    protected function wrapLinksWithTracking(string $content, string $token): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        
        // Replace all <a href="..."> tags with tracking URLs
        $content = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function($matches) use ($baseUrl, $token) {
                $beforeHref = $matches[1];
                $url = $matches[2];
                $afterHref = $matches[3];
                
                // Skip if it's already a tracking URL or an anchor link
                if (strpos($url, '/email/click/') !== false || strpos($url, '#') === 0 || strpos($url, 'mailto:') === 0) {
                    return $matches[0];
                }
                
                // Create tracking URL
                $trackingUrl = $baseUrl . '/email/click/' . $token . '?url=' . urlencode($url);
                
                return '<a ' . $beforeHref . 'href="' . $trackingUrl . '"' . $afterHref . '>';
            },
            $content
        );
        
        return $content;
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
