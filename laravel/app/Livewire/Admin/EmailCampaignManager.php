<?php

namespace App\Livewire\Admin;

use App\Models\EmailTemplate;
use App\Models\EmailCampaign;
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
    // Removed organization-based advanced selection; using direct recipient rows now

    public function sendCampaign()
    {
        try {
            // Validate recipients exist on step 2 send attempt
            if (count(array_filter($this->recipientRows, fn($r)=>!empty($r['include']) && !empty($r['email']))) === 0) {
                $this->addError('recipientRows', 'Please add at least one recipient email.');
                return;
            }

            // Enforce mandatory BCC before sending
            if (!in_array('pkjoshi.sbp@gmail.com', $this->bccList)) {
                $this->bccList[] = 'pkjoshi.sbp@gmail.com';
            }

            // Create campaign record
            $campaign = EmailCampaign::create([
                'name' => $this->name,
                'subject' => $this->subject,
                'content' => $this->previewContent, // representative or simple mode content
                'template_id' => $this->template_id ?: null,
                'recipients' => $this->recipientList,
                'bcc_recipients' => $this->bccList,
                'sender_email' => $this->sender_email,
                'sender_name' => $this->sender_name,
                'status' => 'sending',
                'total_recipients' => count($this->recipientList),
                'created_by' => auth()->id(),
            ]);

            $sentCount = 0; $failedCount = 0;
            foreach ($this->recipientRows as $row) {
                if (empty($row['include']) || empty($row['email'])) continue;
                $personalContent = $this->buildPersonalizedContent($row['variables']);
                try {
                    $recModel = $campaign->recipients()->create([
                        'organization_id' => null,
                        'recipient_email' => $row['email'],
                        'variables' => $row['variables'],
                        'status' => 'pending'
                    ]);
                    $personalSubject = $this->buildPersonalizedSubject($row['variables']);
                    Mail::send([], [], function ($message) use ($row, $campaign, $personalContent, $personalSubject) {
                        $message->to($row['email'])
                                ->subject($personalSubject)
                                ->from($campaign->sender_email, $campaign->sender_name)
                                ->html($personalContent);
                        if (!empty($this->bccList)) { $message->bcc($this->bccList); }
                    });
                    $recModel->update(['status'=>'sent','sent_at'=>now()]);
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
