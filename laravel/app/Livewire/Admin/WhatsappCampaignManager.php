<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\WhatsappService;

class WhatsappCampaignManager extends Component
{
    use WithFileUploads;

    public $numbers = '';
    public $message = '';
    public $image_url = '';
    public $image_file; // Livewire temporary upload
    public $footer_text = '';
    public $results = [];
    public $selectedTemplate = '';
    public $templates = [];
    public $templateParam1 = '';
    public $buttonUrlVar1 = '';
    public $cta_text = '';
    public $cta_url = '';

    public function mount()
    {
        $this->results = [];
        $this->templates = [
            [
                'key' => 'welcome_ai_chat',
                'label' => 'Welcome – AI Chat Support (Utility)',
                'category' => 'UTILITY',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/1181350/pexels-photo-1181350.jpeg',
                'body' => "Hello {{1}}, welcome to AI Chat Support!\nWe're here to help you 24/7 with smart chat & automation.\nVisit us: https://ai-chat.support",
                'button_text' => 'Visit Website',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'key' => 'intro_business_automation',
                'label' => 'Intro – Business Automation (Marketing)',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3182831/pexels-photo-3182831.jpeg',
                'body' => "Hi {{1}},\nImagine automating your customer support & lead generation with AI.\nAI Chat Support helps you do just that — saving time & money.\nExplore here: https://ai-chat.support",
                'button_text' => 'Get Demo',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'key' => 'vertical_ecommerce_offer',
                'label' => 'Vertical – E-commerce Offer (Marketing)',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3747460/pexels-photo-3747460.jpeg',
                'body' => "Hello {{1}},\nBoost your e-commerce sales with 24/7 AI chat support for your store.\nSee use cases: https://ai-chat.support",
                'button_text' => 'See Use Cases',
                'button_url' => 'https://ai-chat.support/blog',
            ],
            [
                'key' => 'event_lead_capture',
                'label' => 'Event – Lead Capture (Marketing)',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg',
                'body' => "Hi {{1}},\nInterested in our upcoming AI automation webinar?\nJoin here: https://ai-chat.support",
                'button_text' => 'Reserve Spot',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'key' => 'followup_after_interest',
                'label' => 'Follow-up After Interest (Marketing)',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/574071/pexels-photo-574071.jpeg',
                'body' => "Hi {{1}},\nJust checking in — did you have a chance to explore AI Chat Support?\nVisit us: https://ai-chat.support",
                'button_text' => 'Case Studies',
                'button_url' => 'https://ai-chat.support/blog',
            ],
        ];
    }

    public function render()
    {
        return view('livewire.admin.whatsapp-campaign-manager')->layout('layouts.admin');
    }

    public function send()
    {
        // If a template is selected, automatically use the template send flow
        if (!empty($this->selectedTemplate)) {
            // Validate only the numbers here; template flow has its own logic
            $this->validate([
                'numbers' => 'required|string',
            ]);
            return $this->sendUsingTemplate();
        }

        $this->validate([
            'numbers' => 'required|string',
            'message' => 'required_without_all:image_url,image_file|string|nullable',
            'image_url' => 'nullable|url',
            'image_file' => 'nullable|image|max:5120', // 5MB
            'footer_text' => 'nullable|string|max:500',
            'cta_text' => 'nullable|string|max:120',
            'cta_url' => 'nullable|url|max:500',
        ]);

        $svc = app(WhatsappService::class);
        $sent = 0; $failed = 0; $errors = [];
        $this->results = [];
        $uploadedImageUrl = null;
        $uploadedImageMediaId = null;
        $localStoredPath = null;
        if ($this->image_file) {
            try {
                $path = $this->image_file->store('whatsapp', 'public');
                $localStoredPath = storage_path('app/public/' . ltrim($path, '/'));
                // Convert to absolute URL for WhatsApp API (fallback)
                $uploadedImageUrl = URL::to(Storage::url($path));
                // Try uploading to WhatsApp to get media_id to avoid external link fetch
                try {
                    $mime = $this->image_file->getMimeType();
                    $mediaResp = $svc->uploadMedia($localStoredPath, $mime);
                    $uploadedImageMediaId = $mediaResp['id'] ?? null;
                } catch (\Throwable $upEx) {
                    \Log::warning('WhatsApp media upload failed, falling back to link', ['error' => $upEx->getMessage()]);
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to store uploaded image for WhatsApp send', ['error' => $e->getMessage()]);
                session()->flash('error', 'Image upload failed: ' . $e->getMessage());
                return;
            }
        }

        foreach (array_filter(array_map('trim', explode(',', $this->numbers))) as $num) {
            try {
                $finalFooter = trim((string)$this->footer_text);
                $finalCaption = $this->message ?: null;
                if ($finalFooter) {
                    if ($finalCaption) {
                        $finalCaption .= "\n\n" . $finalFooter;
                    } else {
                        // If only footer provided, use footer as message/caption
                        $finalCaption = $finalFooter;
                    }
                }

                $imageToUse = $uploadedImageUrl ?: $this->image_url;
                if ($imageToUse || $uploadedImageMediaId) {
                    $resp = $svc->sendImage($num, $imageToUse, $finalCaption, null, null, $uploadedImageMediaId);
                } else {
                    // For text sends, append footer if any
                    $textToSend = $this->message ?: '';
                    // Append CTA if provided
                    if ($this->cta_url) {
                        $ctaLine = $this->cta_text ? ($this->cta_text . ': ' . $this->cta_url) : $this->cta_url;
                        $textToSend = rtrim($textToSend);
                        $textToSend .= ($textToSend ? "\n\n" : '') . $ctaLine;
                    }
                    if ($finalFooter) {
                        $textToSend = rtrim($textToSend) . ($textToSend ? "\n\n" : '') . $finalFooter;
                    }
                    $resp = $svc->sendText($num, $textToSend);
                }
                $sent++;
                $messageId = is_array($resp) ? ($resp['messages'][0]['id'] ?? null) : null;
                \Log::info('WhatsApp campaign per-recipient success', ['to' => $num, 'message_id' => $messageId]);
                $this->results[] = ['to' => $num, 'status' => 'sent', 'message_id' => $messageId];
            } catch (\Throwable $e) {
                $failed++; $errors[] = $e->getMessage();
                \Log::error('WhatsApp campaign per-recipient failure', ['to' => $num, 'error' => $e->getMessage()]);
                $this->results[] = ['to' => $num, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }
        if ($failed === 0) {
            session()->flash('success', "WhatsApp messages sent: {$sent}");
        } else {
            session()->flash('error', "Some messages failed. Sent: {$sent}, Failed: {$failed}. Error: " . ($errors[0] ?? ''));
        }
    }

    public function sendUsingTemplate()
    {
        if (!$this->selectedTemplate) {
            session()->flash('error', 'Please select a template first.');
            return;
        }
        $tpl = collect($this->templates)->firstWhere('key', $this->selectedTemplate);
        if (!$tpl) {
            session()->flash('error', 'Template details not found.');
            return;
        }

        // Numbers and optional media validation
        $this->validate([
            'numbers' => 'required|string',
            'image_file' => 'nullable|image|max:5120',
            'image_url' => 'nullable|url',
        ]);

    $svc = app(WhatsappService::class);
        $sent = 0; $failed = 0; $errors = [];
        $this->results = [];
    $components = [];
        // Determine template language
        $lang = $tpl['language'] ?? 'en';
        try {
            $status = app(WhatsappService::class)->getTemplateStatus($tpl['key']);
            if (!empty($status['data'][0]['language'])) {
                $lang = $status['data'][0]['language'];
            }
        } catch (\Throwable $e) {
            // ignore, use default
        }
        // Support overriding header image with uploaded file
        // Determine header image link preference: uploaded file > image_url field > template default
        $headerImageLink = null;
        $headerImageMediaId = null;
        if ($this->image_file) {
            try {
                $path = $this->image_file->store('whatsapp', 'public');
                $abs = URL::to(Storage::url($path));
                $headerImageLink = $abs;
                // Upload to WhatsApp to get media_id for header
                try {
                    $mime = $this->image_file->getMimeType();
                    $local = storage_path('app/public/' . ltrim($path, '/'));
                    $mediaResp = $svc->uploadMedia($local, $mime);
                    $headerImageMediaId = $mediaResp['id'] ?? null;
                } catch (\Throwable $upEx) {
                    \Log::warning('WhatsApp header media upload failed, using link', ['error' => $upEx->getMessage()]);
                }
            } catch (\Throwable $e) {
                \Log::error('Failed to store uploaded header image for template send', ['error' => $e->getMessage()]);
            }
        } elseif (!empty($this->image_url)) {
            $headerImageLink = $this->image_url;
        }

        if ($headerImageMediaId || $headerImageLink || !empty($tpl['header_image'])) {
            $imageParam = [];
            if ($headerImageMediaId) {
                $imageParam['id'] = $headerImageMediaId;
            } else {
                $imageParam['link'] = $headerImageLink ?: $tpl['header_image'];
            }
            $components[] = [
                'type' => 'header',
                'parameters' => [[
                    'type' => 'image',
                    'image' => $imageParam,
                ]]
            ];
        }
        $components[] = [
            'type' => 'body',
            'parameters' => [[
                'type' => 'text',
                'text' => $this->templateParam1 ?: 'there'
            ]]
        ];

        // Optional dynamic button URL variable (for templates with URL {{1}})
        if (!empty($this->buttonUrlVar1)) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0', // first button
                'parameters' => [[
                    'type' => 'text',
                    'text' => $this->buttonUrlVar1
                ]]
            ];
        }

        foreach (array_filter(array_map('trim', explode(',', $this->numbers))) as $num) {
            try {
                $resp = $svc->sendTemplate($num, $tpl['key'], $lang, $components);
                $sent++;
                $messageId = is_array($resp) ? ($resp['messages'][0]['id'] ?? null) : null;
                \Log::info('WhatsApp template send success', ['to' => $num, 'template' => $tpl['key'], 'message_id' => $messageId]);
                $this->results[] = ['to' => $num, 'status' => 'sent', 'message_id' => $messageId];
            } catch (\Throwable $e) {
                $failed++; $errors[] = $e->getMessage();
                \Log::error('WhatsApp template send failure', ['to' => $num, 'template' => $tpl['key'], 'error' => $e->getMessage()]);
                $this->results[] = ['to' => $num, 'status' => 'failed', 'error' => $e->getMessage()];
            }
        }
        if ($failed === 0) {
            session()->flash('success', "Template messages sent: {$sent}");
        } else {
            session()->flash('error', "Some template messages failed. Sent: {$sent}, Failed: {$failed}. Error: " . ($errors[0] ?? ''));
        }
    }

    public function insertTemplate()
    {
        if (!$this->selectedTemplate) return;
        $tpl = collect($this->templates)->firstWhere('key', $this->selectedTemplate);
        if ($tpl) {
            $this->message = $tpl['body'];
            $this->image_url = $tpl['header_image'] ?? '';
        }
    }

    public function createWabaTemplate()
    {
        if (!$this->selectedTemplate) return;
        $tpl = collect($this->templates)->firstWhere('key', $this->selectedTemplate);
        if (!$tpl) return;
        try {
            $svc = app(WhatsappService::class);
            $resp = $svc->createTemplate([
                'name' => $tpl['key'],
                'category' => $tpl['category'],
                'language' => $tpl['language'] ?? 'en',
                'body_text' => $tpl['body'],
                'header_type' => 'IMAGE',
                'button_text' => $tpl['button_text'] ?? null,
                'button_url' => $tpl['button_url'] ?? null,
            ]);
            session()->flash('success', 'Template submitted to WhatsApp for approval.');
            \Log::info('WABA template create response', $resp);
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to create template: ' . $e->getMessage());
        }
    }

    public function checkTemplateStatus()
    {
        if (!$this->selectedTemplate) return;
        try {
            $svc = app(WhatsappService::class);
            $resp = $svc->getTemplateStatus($this->selectedTemplate);
            session()->flash('success', 'Template status fetched. See logs for details.');
            \Log::info('WABA template status', ['template' => $this->selectedTemplate, 'response' => $resp]);
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to fetch template status: ' . $e->getMessage());
        }
    }
}
