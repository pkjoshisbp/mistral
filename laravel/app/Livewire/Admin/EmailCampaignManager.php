<?php

namespace App\Livewire\Admin;

use App\Models\EmailTemplate;
use App\Models\EmailCampaign;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;
use Livewire\WithPagination;

class EmailCampaignManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $showPreview = false;
    public $step = 1; // 1: Setup, 2: Recipients & Preview
    
    // Campaign fields
    public $name = '';
    public $subject = '';
    public $content = '';
    public $template_id = '';
    public $sender_email = '';
    public $sender_name = '';
    public $sender_phone = '';
    public $recipients = '';
    public $bcc_recipients = '';
    public $scheduleEnabled = false;
    public $scheduled_at = null;
    
    // Template variables
    public $templateVariables = [];
    public $variableValues = [];
    protected function hiddenVariables(): array { return ['sender_name','contact_phone','sender_phone']; }
    
    // Preview & recipients
    public $previewContent = '';
    public $previewSubject = '';
    public $recipientList = [];
    public $bccList = [];
    public $recipientRows = []; // array: [email, include, variables=>[]]
    public $availableVariables = [];
    public $reuseCampaignId = null;
    
    public $search = '';
    public $statusFilter = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'content' => 'required|string',
        'sender_email' => 'required|email',
        'sender_name' => 'nullable|string|max:255',
    ];

    public function mount()
    {
    $this->sender_email = auth()->user()->email ?? 'support@ai-chat.support';
    $this->sender_name = 'AI Chat Support';
    $this->sender_phone = '9937253528';
    }
    
    /* ---------------- Recipient Row Helpers ---------------- */
    public function addRecipientRow()
    {
        $this->recipientRows[] = [
            'email' => '',
            'include' => true,
            'variables' => array_merge(
                ['sender_name'=>'AI Chat Support','contact_phone'=>'+91 9937253528','sender_phone'=>'+91 9937253528'],
                collect($this->availableVariables)->mapWithKeys(fn($v)=>[$v=>$this->variableValues[$v] ?? ''])->toArray()
            ),
        ];
        $this->updatePreview();
    }

    public function duplicateRecipientRow($index)
    {
        if (!isset($this->recipientRows[$index])) return;
        $row = $this->recipientRows[$index];
        $row['email'] = '';
        $this->recipientRows[] = $row;
        $this->updatePreview();
    }

    public function removeRecipientRow($index)
    {
        unset($this->recipientRows[$index]);
        $this->recipientRows = array_values($this->recipientRows);
        $this->updatePreview();
    }

    public function updatedRecipientRows()
    {
        $this->updatePreview();
    }
    public function updatedVariableValues()
    {
        // If user changes default variable values, update empty variable cells in each recipient
        foreach ($this->recipientRows as &$row) {
            foreach ($this->availableVariables as $var) {
                if ($row['variables'][$var] === '') {
                    $row['variables'][$var] = $this->variableValues[$var] ?? '';
                }
            }
        }
        $this->updatePreview();
    }

    public function render()
    {
        $campaigns = EmailCampaign::query()
            ->with(['template', 'creator'])
            ->withCount([
                'recipients as opened_count' => fn($q) => $q->whereNotNull('opened_at'),
                'recipients as delivered_count' => fn($q) => $q->whereNotNull('delivered_at'),
                'recipients as clicked_count' => fn($q) => $q->whereNotNull('clicked_at'),
            ])
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('subject', 'like', '%' . $this->search . '%');
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $templates = EmailTemplate::active()->get();

        return view('livewire.admin.email-campaign-manager', [
            'campaigns' => $campaigns,
            'templates' => $templates
        ])->layout('layouts.admin');
    }

    public function openModal()
    {
        $this->resetInputs();
        $this->step = 1;
        $this->showModal = true;
    }

    public function reuseCampaign($campaignId)
    {
        $campaign = EmailCampaign::find($campaignId);
        if (!$campaign) return;

        $this->resetInputs();
        $this->reuseCampaignId = $campaign->id;
        $this->name = $campaign->name . ' (Resend)';
        $this->subject = $campaign->subject;
        $this->content = $campaign->content;
        $this->template_id = $campaign->template_id;
        $this->sender_email = $campaign->sender_email;
        $this->sender_name = $campaign->sender_name;
        $this->bcc_recipients = is_array($campaign->bcc_recipients) ? implode(', ', $campaign->bcc_recipients) : '';
        $this->scheduleEnabled = false;
        $this->scheduled_at = null;

        if ($this->template_id) {
            $this->selectTemplate();
        }

        $recipientRows = $campaign->recipients()->get();
        $this->recipientRows = $recipientRows->map(function ($rec) {
            return [
                'email' => $rec->recipient_email,
                'include' => true,
                'variables' => array_merge(
                    ['sender_name'=>'AI Chat Support','contact_phone'=>'+91 9937253528','sender_phone'=>'+91 9937253528'],
                    $rec->variables ?? []
                ),
            ];
        })->toArray();

        $this->step = 2;
        $this->updatePreview();
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->showPreview = false;
        $this->resetInputs();
        $this->resetValidation();
    }

    public function selectTemplate()
    {
        if ($this->template_id) {
            $template = EmailTemplate::find($this->template_id);
            if ($template) {
                $this->subject = $template->subject;
                $this->content = $template->content;
                $this->templateVariables = $template->variables ?? [];
                // Filter out hidden variables from editable list
                $this->availableVariables = array_values(array_filter($this->templateVariables, fn($v)=>!in_array($v, $this->hiddenVariables())));
                
                // Initialize variable values
                foreach ($this->templateVariables as $variable) {
                    if (!isset($this->variableValues[$variable])) {
                        $this->variableValues[$variable] = '';
                    }
                }
                // Force defaults for hidden variables
                $this->variableValues['sender_name'] = 'AI Chat Support';
                $this->variableValues['contact_phone'] = '+91 9937253528';
                $this->variableValues['sender_phone'] = '+91 9937253528';
            }
        }
    }

    public function nextStep()
    {
        if ($this->step === 1) {
            $this->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
                'sender_email' => 'required|email',
            ]);
            $this->step = 2;
            $this->updatePreview();
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function updatePreview()
    {
        $included = array_values(array_filter($this->recipientRows, fn($r)=>!empty($r['include'])));
        $this->recipientList = array_map(fn($r)=>$r['email'], $included);
        $firstVars = $included[0]['variables'] ?? [];
        $this->previewContent = $this->buildPersonalizedContent($firstVars);
        $this->previewSubject = $this->buildPersonalizedSubject($firstVars);
    $this->bccList = $this->bcc_recipients ? array_filter(array_map('trim', explode(',', $this->bcc_recipients))) : [];
    if (!in_array('pkjoshi.sbp@gmail.com', $this->bccList)) { $this->bccList[] = 'pkjoshi.sbp@gmail.com'; }
    }

    protected function buildPersonalizedContent(array $variables): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function($m) use ($variables) {
            $key = $m[1];
            return $variables[$key] ?? $this->variableValues[$key] ?? $m[0];
        }, $this->content);
    }
    protected function buildPersonalizedSubject(array $variables): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function($m) use ($variables) {
            $key = $m[1];
            return $variables[$key] ?? $this->variableValues[$key] ?? $m[0];
        }, $this->subject);
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
    // Removed organization-based advanced selection; using direct recipient rows now

    public function sendCampaign()
    {
        try {
            // Validate recipients exist on step 2 send attempt
            if (count(array_filter($this->recipientRows, fn($r)=>!empty($r['include']) && !empty($r['email']))) === 0) {
                $this->addError('recipientRows', 'Please add at least one recipient email.');
                return;
            }

            if ($this->scheduleEnabled) {
                if (!$this->scheduled_at) {
                    $this->addError('scheduled_at', 'Please select a scheduled date/time.');
                    return;
                }

                $scheduledAt = Carbon::parse($this->scheduled_at, config('app.timezone'));
                if ($scheduledAt->lessThanOrEqualTo(now())) {
                    $this->addError('scheduled_at', 'Scheduled time must be in the future.');
                    return;
                }
            }

            // Enforce mandatory BCC before sending
            if (!in_array('pkjoshi.sbp@gmail.com', $this->bccList)) {
                $this->bccList[] = 'pkjoshi.sbp@gmail.com';
            }

            // Create campaign record
            $campaign = EmailCampaign::create([
                'name' => $this->name,
                'subject' => $this->subject,
                'content' => $this->content,
                'template_id' => $this->template_id ?: null,
                'recipients' => $this->recipientList,
                'bcc_recipients' => $this->bccList,
                'sender_email' => $this->sender_email,
                'sender_name' => $this->sender_name,
                'scheduled_at' => $this->scheduleEnabled ? Carbon::parse($this->scheduled_at, config('app.timezone')) : null,
                'status' => $this->scheduleEnabled ? 'scheduled' : 'sending',
                'total_recipients' => count($this->recipientList),
                'created_by' => auth()->id(),
            ]);

            if ($this->scheduleEnabled) {
                foreach ($this->recipientRows as $row) {
                    if (empty($row['include']) || empty($row['email'])) continue;
                    $campaign->recipients()->create([
                        'organization_id' => null,
                        'recipient_email' => $row['email'],
                        'variables' => $row['variables'],
                        'status' => 'pending',
                        'tracking_token' => bin2hex(random_bytes(16)),
                        'resend_count' => 0,
                    ]);
                }

                session()->flash('success', 'Campaign scheduled successfully.');
                $this->closeModal();
                return;
            }

            $sentCount = 0; $failedCount = 0;
            foreach ($this->recipientRows as $row) {
                if (empty($row['include']) || empty($row['email'])) continue;
                $personalContent = $this->buildPersonalizedContent($row['variables']);
                try {
                    $recModel = $campaign->recipients()->create([
                        'organization_id' => null,
                        'recipient_email' => $row['email'],
                        'variables' => $row['variables'],
                        'status' => 'pending',
                        'tracking_token' => bin2hex(random_bytes(16)),
                        'resend_count' => 0,
                        'last_sent_at' => now(),
                        'next_resend_at' => now()->addDays(7),
                    ]);
                    $personalSubject = $this->buildPersonalizedSubject($row['variables']);
                        $trackedContent = $this->buildTrackedContent($personalContent, $recModel->tracking_token);
                        Mail::send([], [], function ($message) use ($row, $campaign, $trackedContent, $personalSubject, $recModel) {
                        $message->to($row['email'])
                            ->subject($personalSubject)
                            ->from($campaign->sender_email, $campaign->sender_name)
                            ->html($trackedContent);
                        $message->getSymfonyMessage()
                            ->getHeaders()
                            ->addTextHeader('X-AICS-Tracking-Token', $recModel->tracking_token);
                        $message->getSymfonyMessage()
                            ->getHeaders()
                            ->addTextHeader('X-Mailgun-Variables', json_encode([
                                'tracking_token' => $recModel->tracking_token,
                                'campaign_id' => $campaign->id,
                            ]));
                    });

                    if (!empty($this->bccList)) {
                        $untrackedContent = $this->applyEmailTypography($personalContent);
                        try {
                            Mail::send([], [], function ($message) use ($campaign, $row, $personalSubject, $untrackedContent) {
                                $message->to($this->bccList)
                                    ->subject('[BCC Copy] ' . $personalSubject)
                                    ->from($campaign->sender_email, $campaign->sender_name)
                                    ->html($untrackedContent);
                                $message->getSymfonyMessage()
                                    ->getHeaders()
                                    ->addTextHeader('X-AICS-BCC-COPY', '1');
                                $message->getSymfonyMessage()
                                    ->getHeaders()
                                    ->addTextHeader('X-AICS-Original-Recipient', (string) ($row['email'] ?? ''));
                            });
                        } catch (\Exception $bccException) {
                            \Log::warning('Campaign BCC copy failed: ' . $bccException->getMessage());
                        }
                    }

                    $recModel->update([
                        'status'=>'sent',
                        'sent_at'=>now(),
                        'delivery_status'=>'sent',
                        'last_event' => 'sent',
                        'last_event_at' => now(),
                    ]);
                    $sentCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    \Log::error('Email sending failed: ' . $e->getMessage());
                    if (isset($recModel)) {
                        $recModel->update(['status'=>'failed','error_message'=>$e->getMessage()]);
                    }
                }
            }

            // Update campaign status
            $campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'sent_at' => now(),
                'total_recipients' => $sentCount + $failedCount,
            ]);

            session()->flash('success', "Campaign sent successfully! Sent: {$sentCount}, Failed: {$failedCount}");
            $this->closeModal();

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to send campaign: ' . $e->getMessage());
        }
    }

    public function deleteCampaign($campaignId)
    {
        $campaign = EmailCampaign::find($campaignId);
        if ($campaign) {
            $campaign->delete();
            session()->flash('success', 'Campaign deleted successfully!');
        }
    }

    private function resetInputs()
    {
        $this->name = '';
        $this->subject = '';
        $this->content = '';
        $this->template_id = '';
        $this->recipients = '';
        $this->bcc_recipients = '';
        $this->scheduleEnabled = false;
        $this->scheduled_at = null;
        $this->templateVariables = [];
        $this->variableValues = [
            'sender_name' => 'AI Chat Support',
            'contact_phone' => '+91 9937253528',
            'sender_phone' => '+91 9937253528',
        ];
        $this->previewContent = '';
        $this->previewSubject = '';
        $this->recipientList = [];
        $this->bccList = [];
        $this->step = 1;
        $this->recipientRows = [];
        $this->sender_email = auth()->user()->email ?? 'support@ai-chat.support';
        $this->sender_name = 'AI Chat Support';
        $this->sender_phone = '9937253528';
    }
}
