<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FaqsManager extends Component
{
    use WithFileUploads;
    public $selectedOrganization = '';
    public $search = '';
    public $showForm = false;
    public $editingId = null;

    public $question = '';
    public $answer = '';
    public $follow_up = '';
    public $category = '';
    public $is_active = true;
    public $sort_order = 0;
    public $keywords = '';
    public $uploadFile; // JSON upload
    public $importing = false;

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'question' => 'required|string|min:3',
        'answer' => 'required|string|min:3',
        'follow_up' => 'nullable|string',
        'category' => 'nullable|string',
        'is_active' => 'boolean',
        'sort_order' => 'nullable|integer',
        'keywords' => 'nullable|string'
    ];

    public function importJson()
    {
        if (!$this->selectedOrganization) {
            session()->flash('error', 'Please select an organization first.');
            return;
        }
        if (!$this->uploadFile) {
            session()->flash('error', 'Please choose a JSON file to upload.');
            return;
        }

        $org = Organization::find($this->selectedOrganization);
        if (!$org) {
            session()->flash('error', 'Selected organization not found.');
            return;
        }

        $this->importing = true;
        try {
            $realPath = $this->uploadFile->getRealPath();
            $filename = $this->uploadFile->getClientOriginalName() ?: 'faqs.json';
            $url = url('/api/organizations/' . $org->slug . '/faqs/import');

            $response = Http::timeout(120)
                ->withToken((string) ($org->api_token ?? ''))
                ->attach('upload', fopen($realPath, 'r'), $filename)
                ->post($url);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['success'])) {
                    session()->flash('message', 'Import complete. Created: ' . ($data['created'] ?? 0) . ', Updated: ' . ($data['updated'] ?? 0) . ', Skipped: ' . ($data['skipped'] ?? 0) . '. Synced: ' . ($data['qdrant']['synced'] ?? 0));
                    // Clear file and refresh list
                    $this->uploadFile = null;
                    return;
                }
            }

            session()->flash('error', 'Import failed: ' . $response->status() . ' ' . $response->body());
        } catch (\Throwable $e) {
            Log::error('Admin FAQ import error', ['error' => $e->getMessage()]);
            session()->flash('error', 'Import error: ' . $e->getMessage());
        } finally {
            $this->importing = false;
        }
    }

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getFaqsProperty()
    {
        $q = OrganizationFaq::query()->with('organization')->orderBy('sort_order')->orderByDesc('id');
        if ($this->selectedOrganization) $q->where('organization_id', $this->selectedOrganization);
        
        if ($this->search) {
            $q->where(function($query) {
                $search = '%' . $this->search . '%';
                $query->where('question', 'like', $search)
                      ->orWhere('answer', 'like', $search)
                      ->orWhere('category', 'like', $search)
                      ->orWhere('keywords', 'like', $search);
            });
        }
        
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->question = $this->answer = $this->follow_up = $this->category = $this->keywords = '';
        $this->is_active = true;
        $this->sort_order = 0;
    }

    public function create()
    {
        $this->validate();
        try {
            $faq = OrganizationFaq::create([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'follow_up' => $this->follow_up,
                'category' => $this->category,
                'keywords' => $this->keywords,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            
            // Auto-sync to Qdrant using new unified system
            $this->syncFaqToQdrant($faq);
            
            session()->flash('message', 'FAQ added and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $this->editingId = $id;
        $this->selectedOrganization = $f->organization_id;
        $this->question = $f->question;
        $this->answer = $f->answer;
        $this->follow_up = $f->follow_up;
        $this->category = $f->category;
        $this->keywords = $f->keywords;
        $this->sort_order = $f->sort_order ?? 0;
        $this->is_active = (bool)$f->is_active;
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $f = OrganizationFaq::find($this->editingId);
        if (!$f) return;
        try {
            $f->update([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'follow_up' => $this->follow_up,
                'category' => $this->category,
                'keywords' => $this->keywords,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            
            // Auto-sync updated FAQ to Qdrant
            $this->syncFaqToQdrant($f);
            
            session()->flash('message', 'FAQ updated and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $organization = $f->organization;
        try {
            $f->delete();
            
            // Remove from Qdrant using unified system  
            $this->deleteFaqFromQdrant($organization->slug, "faq_{$id}");
            
            session()->flash('message', 'FAQ deleted from database and AI system');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync single FAQ to Qdrant using unified system
     */
    private function syncFaqToQdrant($faq)
    {
        Log::info('>>> syncFaqToQdrant method called', ['faq_id' => $faq->id, 'question' => $faq->question]);
        
        try {
            $organization = $faq->organization;
            if (!$organization) {
                Log::warning('FAQ sync failed - no organization', ['faq_id' => $faq->id]);
                return;
            }
            
            $aiService = new AiAgentService();
            
            $items = [
                [
                    'id' => "faq_{$faq->id}",
                    'title' => $faq->question,
                    'content' => $faq->answer,
                    'category' => $faq->category ?? 'general',
                    'metadata' => [
                        'table_id' => $faq->id,
                        'follow_up' => $faq->follow_up ?? '',
                        'updated_at' => $faq->updated_at->toISOString()
                    ]
                ]
            ];
            
            Log::info('>>> About to call storeDataToQdrant', [
                'faq_id' => $faq->id,
                'organization_slug' => $organization->slug,
                'question' => $faq->question,
                'items' => $items
            ]);
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'faq', $items);
            
            Log::info('>>> storeDataToQdrant returned', [
                'faq_id' => $faq->id,
                'result' => $result
            ]);
            
            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('FAQ auto-sync successful', [
                    'faq_id' => $faq->id,
                    'organization_slug' => $organization->slug
                ]);
            } else {
                Log::warning('FAQ auto-sync failed', [
                    'faq_id' => $faq->id,
                    'organization_slug' => $organization->slug,
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('FAQ auto-sync exception', [
                'faq_id' => $faq->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Delete FAQ from Qdrant 
     */
    private function deleteFaqFromQdrant($organizationSlug, $faqId)
    {
        try {
            $aiService = new AiAgentService();
            
            Log::info('Deleting FAQ from Qdrant', [
                'organization_slug' => $organizationSlug,
                'faq_id' => $faqId
            ]);
            
            $result = $aiService->deleteDataFromQdrant($organizationSlug, [$faqId]);
            
            if ($result && $result['success'] && $result['deleted_count'] > 0) {
                Log::info('FAQ deletion from Qdrant successful', [
                    'organization_slug' => $organizationSlug,
                    'faq_id' => $faqId
                ]);
            } else {
                Log::warning('FAQ deletion from Qdrant failed', [
                    'organization_slug' => $organizationSlug,
                    'faq_id' => $faqId,
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('FAQ delete from Qdrant failed', [
                'organization_slug' => $organizationSlug,
                'faq_id' => $faqId,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function render()
    {
        return view('livewire.admin.faqs-manager')
            ->layout('layouts.admin');
    }
}
