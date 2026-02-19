<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Services\WhatsappService;
use App\Services\WhatsappTemplateSyncService;
use App\Models\WhatsappTemplate;

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
    public $draftTemplates = [];
    public $approvedTemplates = [];
    public $templateParam1 = '';
    public $headerParam1 = '';
    public $buttonUrlVar1 = '';
    public $cta_text = '';
    public $cta_url = '';
    public $templateName = '';

    public function mount()
    {
        $this->results = [];
        $this->draftTemplates = [
            [
                'value' => 'draft:welcome_ai_chat',
                'source' => 'draft',
                'key' => 'welcome_ai_chat',
                'label' => 'Welcome - AI Chat Support (Utility) [Draft]',
                'category' => 'UTILITY',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/1181350/pexels-photo-1181350.jpeg',
                'body' => "Hello {{1}}, welcome to AI Chat Support!\nWe're here to help you 24/7 with smart chat & automation.\nVisit us: https://ai-chat.support",
                'button_text' => 'Visit Website',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'value' => 'draft:intro_business_automation',
                'source' => 'draft',
                'key' => 'intro_business_automation',
                'label' => 'Intro - Business Automation (Marketing) [Draft]',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3182831/pexels-photo-3182831.jpeg',
                'body' => "Hi {{1}},\nImagine automating your customer support & lead generation with AI.\nAI Chat Support helps you do just that — saving time & money.\nExplore here: https://ai-chat.support",
                'button_text' => 'Get Demo',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'value' => 'draft:vertical_ecommerce_offer',
                'source' => 'draft',
                'key' => 'vertical_ecommerce_offer',
                'label' => 'Vertical - E-commerce Offer (Marketing) [Draft]',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3747460/pexels-photo-3747460.jpeg',
                'body' => "Hello {{1}},\nBoost your e-commerce sales with 24/7 AI chat support for your store.\nSee use cases: https://ai-chat.support",
                'button_text' => 'See Use Cases',
                'button_url' => 'https://ai-chat.support/blog',
            ],
            [
                'value' => 'draft:event_lead_capture',
                'source' => 'draft',
                'key' => 'event_lead_capture',
                'label' => 'Event - Lead Capture (Marketing) [Draft]',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/3184291/pexels-photo-3184291.jpeg',
                'body' => "Hi {{1}},\nInterested in our upcoming AI automation webinar?\nJoin here: https://ai-chat.support",
                'button_text' => 'Reserve Spot',
                'button_url' => 'https://ai-chat.support',
            ],
            [
                'value' => 'draft:followup_after_interest',
                'source' => 'draft',
                'key' => 'followup_after_interest',
                'label' => 'Follow-up After Interest (Marketing) [Draft]',
                'category' => 'MARKETING',
                'language' => 'en',
                'header_type' => 'IMAGE',
                'header_image' => 'https://images.pexels.com/photos/574071/pexels-photo-574071.jpeg',
                'body' => "Hi {{1}},\nJust checking in — did you have a chance to explore AI Chat Support?\nVisit us: https://ai-chat.support",
                'button_text' => 'Case Studies',
                'button_url' => 'https://ai-chat.support/blog',
            ],
        ];
        $this->loadApprovedTemplates();
        $this->templates = array_merge($this->approvedTemplates, $this->draftTemplates);
    }

    private function loadApprovedTemplates(): void
    {
        $rows = WhatsappTemplate::approved()
            ->orderBy('name')
            ->get();

        $this->approvedTemplates = $rows->map(function (WhatsappTemplate $tpl) {
            $buttons = $tpl->buttons ?? [];
            $primaryButton = null;
            foreach ($buttons as $button) {
                if (($button['type'] ?? '') === 'URL') {
                    $primaryButton = $button;
                    break;
                }
            }

            $label = $tpl->name;
            if ($tpl->language) {
                $label .= ' (' . $tpl->language . ')';
            }
            if ($tpl->category) {
                $label .= ' - ' . $tpl->category;
            }

            return [
                'value' => 'approved:' . $tpl->id,
                'source' => 'approved',
                'id' => $tpl->id,
                'key' => $tpl->name,
                'label' => $label,
                'category' => $tpl->category,
                'language' => $tpl->language,
                'header_type' => $tpl->header_type,
                'header_text' => $tpl->header_text,
                'header_image' => $tpl->header_media_url,
                'body' => $tpl->body_text,
                'footer' => $tpl->footer_text,
                'buttons' => $buttons,
                'button_text' => $primaryButton['text'] ?? null,
                'button_url' => $primaryButton['url'] ?? null,
                'body_variable_count' => (int) $tpl->body_variable_count,
                'status' => $tpl->status,
            ];
        })->toArray();
    }

    private function getSelectedTemplate(): ?array
    {
        if (!$this->selectedTemplate) {
            return null;
        }

        return collect($this->templates)->firstWhere('value', $this->selectedTemplate);
    }

    private function syncApprovedTemplatesSilently(): void
    {
        try {
            app(WhatsappTemplateSyncService::class)->syncTemplates('APPROVED');
            $this->loadApprovedTemplates();
            $this->templates = array_merge($this->approvedTemplates, $this->draftTemplates);
        } catch (\Throwable $e) {
            \Log::warning('WhatsApp template auto-sync failed', ['error' => $e->getMessage()]);
        }
    }
    public function syncApprovedTemplates()
    {
        try {
            $count = app(WhatsappTemplateSyncService::class)->syncTemplates('APPROVED');

            $this->loadApprovedTemplates();
            $this->templates = array_merge($this->approvedTemplates, $this->draftTemplates);
            session()->flash('success', "Synced {$count} approved templates from WhatsApp.");
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to sync templates: ' . $e->getMessage());
        }
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
        $this->syncApprovedTemplatesSilently();
        $tpl = $this->getSelectedTemplate();
        if (!$tpl) {
            session()->flash('error', 'Template details not found.');
            return;
        }

        $headerType = strtoupper((string) ($tpl['header_type'] ?? ''));
        $headerText = (string) ($tpl['header_text'] ?? '');
        $headerVariableCount = 0;
        if ($headerType === 'TEXT') {
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $headerText, $matches);
            $headerVariableCount = count(array_unique($matches[1] ?? []));
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
        $lang = $tpl['language'] ?? 'en_US';
        if ($lang === 'en') {
            $lang = 'en_US';
        }
        // Determine template language
        try {
            $status = $svc->getTemplateStatus($tpl['key']);
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

        if ($headerType === 'IMAGE' && !$headerImageMediaId && !$headerImageLink && empty($tpl['header_image'])) {
            session()->flash('error', 'This template requires a header image. Please upload one or provide an image URL.');
            return;
        }

        if ($headerType === 'TEXT' && $headerVariableCount > 0) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $this->headerParam1))));
            $headerParams = [];
            for ($i = 0; $i < $headerVariableCount; $i++) {
                $headerParams[] = [
                    'type' => 'text',
                    'text' => $values[$i] ?? ('Header ' . ($i + 1)),
                ];
            }
            $components[] = [
                'type' => 'header',
                'parameters' => $headerParams,
            ];
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
        $bodyVariableCount = (int) ($tpl['body_variable_count'] ?? 1);
        if ($bodyVariableCount > 0) {
            $values = array_values(array_filter(array_map('trim', explode(',', (string) $this->templateParam1))));
            $bodyParams = [];
            for ($i = 0; $i < $bodyVariableCount; $i++) {
                $bodyParams[] = [
                    'type' => 'text',
                    'text' => $values[$i] ?? ('Value ' . ($i + 1)),
                ];
            }
            $components[] = [
                'type' => 'body',
                'parameters' => $bodyParams,
            ];
        }

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
        $tpl = $this->getSelectedTemplate();
        if ($tpl) {
            $this->message = $tpl['body'];
            $this->image_url = $tpl['header_image'] ?? '';
            $this->templateName = $tpl['key'] ?? '';
        }
    }

    public function createWabaTemplate()
    {
        $this->resetErrorBag();
        $this->validate([
            'selectedTemplate' => 'required|string',
            'image_file' => 'nullable|image|max:5120',
            'image_url' => 'nullable|url',
        ]);

        $tpl = $this->getSelectedTemplate();
        if (!$tpl) {
            $this->addError('selectedTemplate', 'Template details not found.');
            return;
        }
        if (($tpl['source'] ?? '') !== 'draft') {
            $this->addError('selectedTemplate', 'Template creation is only available for draft templates.');
            return;
        }

        $errors = [];
        $key = trim((string) ($this->templateName !== '' ? $this->templateName : ($tpl['key'] ?? '')));
        $category = strtoupper((string)($tpl['category'] ?? ''));
        $language = $tpl['language'] ?? 'en';
        $bodyText = $tpl['body'] ?? '';
        $headerType = strtoupper((string)($tpl['header_type'] ?? ''));
        $headerText = $tpl['header_text'] ?? '';
        $headerImage = $tpl['header_image'] ?? '';
        $buttonText = $tpl['button_text'] ?? '';
        $buttonUrl = $tpl['button_url'] ?? '';

        $svc = app(WhatsappService::class);
        $headerImageExample = null;
        $headerImageMime = null;
        if ($this->image_file) {
            try {
                $path = $this->image_file->store('whatsapp', 'public');
                $url = URL::to(Storage::url($path));
                $url = preg_replace('/^http:\/\//i', 'https://', $url);
                $headerImageMime = $this->image_file->getMimeType();
                $mediaResp = $svc->uploadTemplateMediaFromUrl($url, $headerImageMime);
                $headerImageExample = $mediaResp['id'] ?? null;
                if (!$headerImageExample) {
                    $this->addError('template', 'Failed to get media ID for the uploaded header image.');
                    return;
                }
            } catch (\Throwable $e) {
                $this->addError('template', 'Failed to upload header image: ' . $e->getMessage());
                return;
            }
        } else {
            $sourceUrl = $this->image_url ?: $headerImage;
            if (!empty($sourceUrl)) {
                try {
                    $sourceUrl = preg_replace('/^http:\/\//i', 'https://', $sourceUrl);
                    $mediaResp = $svc->uploadTemplateMediaFromUrl($sourceUrl, null);
                    $headerImageExample = $mediaResp['id'] ?? null;
                    if (!$headerImageExample) {
                        $this->addError('template', 'Failed to get media ID for the header image URL.');
                        return;
                    }
                } catch (\Throwable $e) {
                    $this->addError('template', 'Failed to prepare header image: ' . $e->getMessage());
                    return;
                }
            }
        }

        if ($key === '') {
            $errors[] = 'Template name is required.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $key)) {
            $errors[] = 'Template name must use lowercase letters, numbers, and underscores only.';
        }
        if ($category === '') {
            $errors[] = 'Template category is missing.';
        } elseif (!in_array($category, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true)) {
            $errors[] = 'Template category must be MARKETING, UTILITY, or AUTHENTICATION.';
        }
        if (trim($bodyText) === '') {
            $errors[] = 'Template body text is required.';
        }
        if ($headerType && !in_array($headerType, ['IMAGE', 'TEXT'], true)) {
            $errors[] = 'Header type must be IMAGE or TEXT when provided.';
        }
        if ($headerType === 'TEXT' && trim($headerText) === '') {
            $errors[] = 'Header text is required when header type is TEXT.';
        }
        if ($headerType === 'IMAGE' && trim((string) $headerImageExample) === '') {
            $errors[] = 'Header image URL or upload is required when header type is IMAGE.';
        }
        if (($buttonText && !$buttonUrl) || (!$buttonText && $buttonUrl)) {
            $errors[] = 'Both button text and button URL must be provided together.';
        }

        if (!empty($errors)) {
            foreach ($errors as $err) {
                $this->addError('template', $err);
            }
            return;
        }

        try {
            // Check if template already exists for this language to avoid API error
            try {
                $status = $svc->getTemplateStatus($key);
                $existing = collect($status['data'] ?? [])->first(function ($item) use ($key, $language) {
                    return ($item['name'] ?? null) === $key && ($item['language'] ?? null) === $language;
                });
                if ($existing) {
                    $this->addError('template', 'This template already has English content in WhatsApp. Use a new template name to create another version.');
                    return;
                }
            } catch (\Throwable $e) {
                // If status check fails, continue with create attempt
            }
            $resp = $svc->createTemplate([
                'name' => $key,
                'category' => $category,
                'language' => $language,
                'body_text' => $bodyText,
                'header_type' => $headerType ?: null,
                'header_text' => $headerText ?: null,
                'header_example' => $headerType === 'IMAGE' ? ($headerImageExample ?: null) : null,
                'button_text' => $buttonText ?: null,
                'button_url' => $buttonUrl ?: null,
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
            $tpl = $this->getSelectedTemplate();
            if (!$tpl) {
                session()->flash('error', 'Template details not found.');
                return;
            }
            $resp = $svc->getTemplateStatus($tpl['key']);
            session()->flash('success', 'Template status fetched. See logs for details.');
            \Log::info('WABA template status', ['template' => $tpl['key'], 'response' => $resp]);
        } catch (\Throwable $e) {
            session()->flash('error', 'Failed to fetch template status: ' . $e->getMessage());
        }
    }
}
