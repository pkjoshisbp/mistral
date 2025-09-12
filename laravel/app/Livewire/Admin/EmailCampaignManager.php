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
    public $step = 1; // 1: Setup, 2: Recipients, 3: Preview
    
    // Campaign fields
    public $name = '';
    public $subject = '';
    public $content = '';
    public $template_id = '';
    public $sender_email = '';
    public $sender_name = '';
    public $recipients = '';
    public $bcc_recipients = '';
    
    // Template variables
    public $templateVariables = [];
    public $variableValues = [];
    
    // Preview
    public $previewContent = '';
    public $recipientList = [];
    public $bccList = [];
    
    public $search = '';
    public $statusFilter = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'content' => 'required|string',
        'sender_email' => 'required|email',
        'sender_name' => 'nullable|string|max:255',
        'recipients' => 'required|string',
    ];

    public function mount()
    {
        $this->sender_email = auth()->user()->email ?? '';
        $this->sender_name = auth()->user()->name ?? '';
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
                
                // Initialize variable values
                foreach ($this->templateVariables as $variable) {
                    if (!isset($this->variableValues[$variable])) {
                        $this->variableValues[$variable] = '';
                    }
                }
            }
        }
    }

    public function nextStep()
    {
        if ($this->step == 1) {
            $this->validate([
                'name' => 'required|string|max:255',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
                'sender_email' => 'required|email',
            ]);
            $this->step = 2;
        } elseif ($this->step == 2) {
            $this->validate([
                'recipients' => 'required|string',
            ]);
            $this->preparePreview();
            $this->step = 3;
        }
    }

    public function previousStep()
    {
        if ($this->step > 1) {
            $this->step--;
        }
    }

    public function preparePreview()
    {
        // Process recipients
        $this->recipientList = array_filter(array_map('trim', explode(',', $this->recipients)));
        $this->bccList = $this->bcc_recipients ? array_filter(array_map('trim', explode(',', $this->bcc_recipients))) : [];
        
        // Replace variables in content
        $this->previewContent = $this->content;
        foreach ($this->variableValues as $variable => $value) {
            $this->previewContent = str_replace('{' . $variable . '}', $value, $this->previewContent);
        }
    }

    public function sendCampaign()
    {
        try {
            // Create campaign record
            $campaign = EmailCampaign::create([
                'name' => $this->name,
                'subject' => $this->subject,
                'content' => $this->previewContent,
                'template_id' => $this->template_id ?: null,
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
        $this->variableValues = [];
        $this->previewContent = '';
        $this->recipientList = [];
        $this->bccList = [];
        $this->step = 1;
    }
}
