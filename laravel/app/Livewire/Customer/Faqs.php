<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationFaq;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class Faqs extends Component
{
    public $showForm = false;
    public $editingId = null;
    public $question = '';
    public $answer = '';
    public $category = '';
    public $keywords = '';
    public $sort_order = 0;
    public $is_active = true;

    protected $rules = [
        'question' => 'required|string|min:3',
        'answer' => 'required|string|min:3',
        'category' => 'nullable|string',
        'keywords' => 'nullable|string',
        'sort_order' => 'nullable|integer',
        'is_active' => 'boolean'
    ];

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
    }

    public function create()
    {
        $this->validate();
        $org = $this->orgId();
        
        try {
            $faq = OrganizationFaq::create([
                'organization_id' => $org,
                'question' => $this->question,
                'answer' => $this->answer,
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
    }

    public function update()
    {
        $this->validate();
        $f = OrganizationFaq::where('organization_id', $this->orgId())->find($this->editingId);
        if (!$f) return;
        
        try {
            $f->update([
                'question' => $this->question,
                'answer' => $this->answer,
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
            
            $items = [
                [
                    'id' => "faq_{$faq->id}",
                    'title' => $faq->question,
                    'content' => $faq->answer,
                    'category' => $faq->category ?? 'general',
                    'metadata' => [
                        'table_id' => $faq->id,
                        'updated_at' => $faq->updated_at->toISOString(),
                        'keywords' => $faq->keywords
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
}
