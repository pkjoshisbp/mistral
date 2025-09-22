<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\OrganizationFaq;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class Faqs extends Component
{
    use WithFileUploads;
    public $showForm = false;
    public $editingId = null;
    public $question = '';
    public $answer = '';
    public $category = '';
    public $keywords = '';
    public $sort_order = 0;
    public $is_active = true;
    public $showPreview = false;
    public $formSnapshot = [];
    public $uploadFile; // JSON upload
    public $importing = false;
    protected $listeners = ['customer-faqs-user-choice' => 'handleUnsavedChoice'];

    protected $rules = [
        'question' => 'required|string|min:3',
    'answer' => 'required|string|min:3',
        'category' => 'nullable|string',
        'keywords' => 'nullable|string',
        'sort_order' => 'nullable|integer',
        'is_active' => 'boolean'
    ];

    public function importJson()
    {
        $org = Organization::find($this->orgId());
        if (!$org) {
            session()->flash('error', 'Organization not found.');
            return;
        }
        if (!$this->uploadFile) {
            session()->flash('error', 'Please choose a JSON file to upload.');
            return;
        }

        $this->importing = true;
        try {
            $realPath = $this->uploadFile->getRealPath();
            $filename = $this->uploadFile->getClientOriginalName() ?: 'faqs.json';
            $url = url('/api/organizations/' . $org->slug . '/faqs/import');

            $response = \Http::timeout(120)
                ->withToken((string) ($org->api_token ?? ''))
                ->attach('upload', fopen($realPath, 'r'), $filename)
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['success'])) {
                    session()->flash('message', 'Import complete. Created: ' . ($data['created'] ?? 0) . ', Updated: ' . ($data['updated'] ?? 0) . ', Skipped: ' . ($data['skipped'] ?? 0) . '. Synced: ' . ($data['qdrant']['synced'] ?? 0));
                    $this->uploadFile = null;
                    return;
                }
            }

            session()->flash('error', 'Import failed: ' . $response->status() . ' ' . $response->body());
        } catch (\Throwable $e) {
            \Log::error('Customer FAQ import error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Import error: ' . $e->getMessage());
        } finally {
            $this->importing = false;
        }
    }

    private function orgId()
    {
        $u = auth()->user();
        return $u->organization->id ?? $u->organizations->first()->id ?? null;
    }

    public function getFaqsProperty()
    {
        return OrganizationFaq::where('organization_id', $this->orgId())
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();
    }

    public function resetForm()
    {
    $this->editingId = null;
    $this->question = $this->answer = $this->category = $this->keywords = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->showPreview = false;
    }

    public function togglePreview()
    {
        $this->showPreview = !$this->showPreview;
    }

    /**
     * Take a snapshot of current form values to detect unsaved changes
     */
    private function snapshotForm()
    {
        $this->formSnapshot = [
            'editingId' => $this->editingId,
            'question' => (string) $this->question,
            'answer' => (string) $this->answer,
            'category' => (string) $this->category,
            'keywords' => (string) $this->keywords,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];
    }

    /**
     * Check if current form differs from the snapshot
     */
    private function hasUnsavedChanges(): bool
    {
        if (empty($this->formSnapshot)) return false;
        $current = [
            'editingId' => $this->editingId,
            'question' => (string) $this->question,
            'answer' => (string) $this->answer,
            'category' => (string) $this->category,
            'keywords' => (string) $this->keywords,
            'sort_order' => (int) $this->sort_order,
            'is_active' => (bool) $this->is_active,
        ];
        return $current !== $this->formSnapshot;
    }

    public function toggleForm()
    {
        $this->showForm = !$this->showForm;
        
        // If form is now visible, dispatch event to activate toolbar
        if ($this->showForm) {
            $this->dispatch('activate-toolbar');
            // Starting fresh create if no editingId and fields empty
            $this->snapshotForm();
        }
    }

    /**
     * Safer handler for the header "Add FAQ" button.
     * If there are unsaved changes, ask user to save/discard/cancel.
     */
    public function handleAddClick()
    {
        if ($this->showForm && $this->hasUnsavedChanges()) {
            // Ask browser to confirm via JS
            $this->dispatch('confirm-unsaved-faq');
            return;
        }

        // No unsaved changes — start a new blank form
        $this->editingId = null;
        // Do not wipe on purpose if form is already open and blank; but ensure blank for new
        $this->question = $this->answer = $this->category = $this->keywords = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->showPreview = false;
        $this->showForm = true;
        $this->snapshotForm();
        $this->dispatch('activate-toolbar');
    }

    /**
     * Handle user choice from JS confirm dialog.
     * Payload: ['action' => 'save'|'discard'|'cancel']
     */
    public function handleUnsavedChoice($payload = [])
    {
        $action = $payload['action'] ?? 'cancel';
        if ($action === 'save') {
            // Save current as new (create) then prepare new blank form
            try {
                $this->create();
                // After create() the form is hidden and cleared; reopen new blank
                $this->editingId = null;
                $this->showForm = true;
                $this->question = $this->answer = $this->category = $this->keywords = '';
                $this->sort_order = 0;
                $this->is_active = true;
                $this->showPreview = false;
                $this->snapshotForm();
                $this->dispatch('activate-toolbar');
            } catch (\Throwable $e) {
                // Validation errors will surface via session; keep form as-is
            }
        } elseif ($action === 'discard') {
            // Discard and start new blank
            $this->editingId = null;
            $this->question = $this->answer = $this->category = $this->keywords = '';
            $this->sort_order = 0;
            $this->is_active = true;
            $this->showPreview = false;
            $this->showForm = true;
            $this->snapshotForm();
            $this->dispatch('activate-toolbar');
        } else {
            // cancel - do nothing, keep current form content intact
        }
    }

    public function getPreviewHtmlProperty()
    {
        if (empty($this->answer)) {
            return '<p class="text-muted">Enter your answer using simple HTML (e.g., <strong>bold</strong>, <em>italic</em>, <a href=\"#\">link</a>) to see the preview...</p>';
        }
        $faq = new OrganizationFaq();
        return $faq->sanitizeHtml($this->answer);
    }

    public function create()
    {
        $this->validate();
        $org = $this->orgId();
        
    // Sanitize provided HTML before saving
    $faqInstance = new OrganizationFaq();
    $htmlContent = $faqInstance->sanitizeHtml($this->answer);
        
        try {
            $faq = OrganizationFaq::create([
                'organization_id' => $org,
                'question' => $this->question,
                'answer' => $htmlContent,
                'answer_markdown' => null,
                'category' => $this->category,
                'keywords' => $this->keywords,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active
            ]);

            // Sync to Qdrant using new unified system
            $this->syncFaqToQdrant($faq);

            session()->flash('message', 'FAQ added and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
            Log::error('Customer FAQ create error', ['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $f = OrganizationFaq::where('organization_id', $this->orgId())->find($id);
        if (!$f) return;
        
        $this->editingId = $id;
        $this->question = $f->question;
    $this->answer = $f->answer;
        $this->category = $f->category;
        $this->keywords = $f->keywords;
        $this->sort_order = $f->sort_order ?? 0;
        $this->is_active = (bool)$f->is_active;
        $this->showForm = true;
        
        // Dispatch event to activate toolbar
        $this->dispatch('activate-toolbar');
        $this->snapshotForm();
    }

    public function update()
    {
        $this->validate();
        $f = OrganizationFaq::where('organization_id', $this->orgId())->find($this->editingId);
        if (!$f) return;
        
    // Sanitize provided HTML
    $htmlContent = $f->sanitizeHtml($this->answer);
        
        try {
            $f->update([
                'question' => $this->question,
                'answer' => $htmlContent,
                'answer_markdown' => null,
                'category' => $this->category,
                'keywords' => $this->keywords,
                'sort_order' => $this->sort_order,
                'is_active' => $this->is_active
            ]);

            // Sync updated FAQ to Qdrant using new unified system
            $this->syncFaqToQdrant($f);

            session()->flash('message', 'FAQ updated and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
            Log::error('Customer FAQ update error', ['error' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        $f = OrganizationFaq::where('organization_id', $this->orgId())->find($id);
        if (!$f) return;
        
        try {
            $organization = Organization::find($f->organization_id);
            $ai = new AiAgentService();
            
            // Delete from Qdrant using new unified system
            if ($organization) {
                $result = $ai->deleteDataFromQdrant($organization->slug, 'faq_' . $f->id);
                Log::info('Customer FAQ deleted from Qdrant', [
                    'faq_id' => $f->id,
                    'organization' => $organization->slug,
                    'result' => $result
                ]);
            }
            
            // Delete from database
            $f->delete();
            
            session()->flash('message', 'FAQ deleted and removed from AI system');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
            Log::error('Customer FAQ delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync FAQ to Qdrant using unified system
     */
    private function syncFaqToQdrant($faq)
    {
        Log::info('>>> CUSTOMER syncFaqToQdrant called', ['faq_id' => $faq->id, 'question' => $faq->question]);
        
        try {
            $organization = Organization::find($faq->organization_id);
            if (!$organization) {
                Log::warning('Customer FAQ sync failed - no organization', ['faq_id' => $faq->id]);
                return;
            }
            
            $aiService = new AiAgentService();
            
            // Compute content using model accessor (with fallbacks) and preserve URLs
            $content = trim((string) $faq->plain_text_with_links);
            if ($content === '') {
                Log::warning('Skipping Qdrant upsert for FAQ with empty content', [
                    'faq_id' => $faq->id,
                    'organization_slug' => $organization->slug,
                    'question' => $faq->question
                ]);
                return; // Do not overwrite existing good vectors with empty content
            }
            
            $items = [
                [
                    'id' => "faq_{$faq->id}",
                    'title' => $faq->question,
                    'content' => $content, // Use plain text for embeddings
                    'category' => $faq->category ?? 'general',
                    'metadata' => [
                        'table_id' => $faq->id,
                        'updated_at' => $faq->updated_at->toISOString(),
                        'keywords' => $faq->keywords,
                        'links' => method_exists($faq, 'getLinksAttribute') ? $faq->links : []
                    ]
                ]
            ];
            
            Log::info('>>> CUSTOMER About to call storeDataToQdrant', [
                'faq_id' => $faq->id,
                'organization_slug' => $organization->slug,
                'question' => $faq->question,
                'items' => $items
            ]);
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'faq', $items);
            
            Log::info('>>> CUSTOMER storeDataToQdrant returned', [
                'faq_id' => $faq->id,
                'result' => $result
            ]);

            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('Customer FAQ auto-sync successful', [
                    'faq_id' => $faq->id,
                    'organization_slug' => $organization->slug
                ]);
            } else {
                Log::warning('Customer FAQ auto-sync failed', [
                    'faq_id' => $faq->id,
                    'organization_slug' => $organization->slug,
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Customer FAQ auto-sync exception', [
                'faq_id' => $faq->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.customer.faqs')->layout('layouts.customer');
    }


    /**
     * Resync all FAQs of the current organization to AI (Qdrant)
     */
    public function resyncFaqsToAi()
    {
        try {
            $org = Organization::find($this->orgId());
            if (!$org) {
                session()->flash('error', 'Organization not found.');
                return;
            }
            // Call the Artisan command so we reuse the same safe logic and logging
            Artisan::call('faq:resync', [
                'organization' => $org->slug
            ]);
            $output = Artisan::output();
            Log::info('Customer-triggered FAQ resync completed', [
                'organization_slug' => $org->slug,
                'output' => $output
            ]);
            session()->flash('message', 'Resync completed. Output: ' . trim($output));
        } catch (\Throwable $e) {
            Log::error('Customer resync FAQs error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Resync failed: ' . $e->getMessage());
        }
    }

}
