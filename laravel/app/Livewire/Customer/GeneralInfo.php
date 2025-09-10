<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationData;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class GeneralInfo extends Component
{
    public $showForm = false;
    public $editingId = null;
    public $title = '';
    public $information = '';
    public $category = '';
    public $keywords = '';

    protected $rules = [
        'title' => 'required|string|min:2',
        'information' => 'required|string|min:5',
        'category' => 'nullable|string',
        'keywords' => 'nullable|string'
    ];

    private function orgId()
    {
        $u = auth()->user();
        return $u->organization->id ?? $u->organizations->first()->id ?? null;
    }

    public function getInfosProperty()
    {
        return OrganizationData::where('type', 'info')
            ->where('organization_id', $this->orgId())
            ->orderByDesc('id')
            ->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->title = $this->information = $this->category = $this->keywords = '';
    }

    public function create()
    {
        $this->validate();
        $org = $this->orgId();
        
        try {
            $content = $this->compose();
            $rec = OrganizationData::create([
                'organization_id' => $org,
                'type' => 'info',
                'name' => $this->title,
                'description' => $this->information,
                'content' => $content,
                'metadata' => [
                    'category' => $this->category,
                    'keywords' => $this->keywords,
                    'type' => 'manual_entry'
                ]
            ]);

            // Sync to Qdrant using new unified system
            $this->syncInfoToQdrant($rec);

            session()->flash('message', 'Information added and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
            Log::error('Customer info create error', ['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $r = OrganizationData::where('organization_id', $this->orgId())->find($id);
        if (!$r) return;
        
        $this->editingId = $id;
        $this->title = $r->name;
        $this->information = $r->description;
        $m = $r->metadata ?? [];
        $this->category = $m['category'] ?? '';
        $this->keywords = $m['keywords'] ?? '';
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $r = OrganizationData::where('organization_id', $this->orgId())->find($this->editingId);
        if (!$r) return;
        
        try {
            $content = $this->compose();
            $metadata = [
                'category' => $this->category,
                'keywords' => $this->keywords,
                'type' => 'manual_entry'
            ];
            
            $r->update([
                'name' => $this->title,
                'description' => $this->information,
                'content' => $content,
                'metadata' => $metadata
            ]);

            // Sync to Qdrant using new unified system
            $this->syncInfoToQdrant($r);

            session()->flash('message', 'Information updated and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
            Log::error('Customer info update error', ['error' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        $r = OrganizationData::where('organization_id', $this->orgId())->find($id);
        if (!$r) return;
        
        try {
            $organization = Organization::find($r->organization_id);
            $ai = new AiAgentService();
            
            // Delete from Qdrant using new unified system
            if ($organization) {
                $result = $ai->deleteDataFromQdrant($organization->slug, 'info_' . $r->id);
                Log::info('Customer info deleted from Qdrant', [
                    'info_id' => $r->id,
                    'organization' => $organization->slug,
                    'result' => $result
                ]);
            }
            
            // Delete from database
            $r->delete();
            
            session()->flash('message', 'Information deleted and removed from AI system');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
            Log::error('Customer info delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync info to Qdrant using unified system
     */
    private function syncInfoToQdrant($info)
    {
        Log::info('>>> CUSTOMER syncInfoToQdrant called', ['info_id' => $info->id, 'title' => $info->name]);
        
        try {
            $organization = Organization::find($info->organization_id);
            if (!$organization) {
                Log::warning('Customer info sync failed - no organization', ['info_id' => $info->id]);
                return;
            }
            
            $aiService = new AiAgentService();
            
            $items = [
                [
                    'id' => "info_{$info->id}",
                    'title' => $info->name,
                    'content' => $info->content,
                    'category' => $info->metadata['category'] ?? 'general',
                    'metadata' => [
                        'table_id' => $info->id,
                        'updated_at' => $info->updated_at->toISOString(),
                        'keywords' => $info->metadata['keywords'] ?? '',
                    ]
                ]
            ];
            
            Log::info('>>> CUSTOMER About to call storeDataToQdrant for info', [
                'info_id' => $info->id,
                'organization_slug' => $organization->slug,
                'title' => $info->name,
                'items' => $items
            ]);
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'info', $items);
            
            Log::info('>>> CUSTOMER storeDataToQdrant returned for info', [
                'info_id' => $info->id,
                'result' => $result
            ]);

            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('Customer info auto-sync successful', [
                    'info_id' => $info->id,
                    'organization_slug' => $organization->slug
                ]);
            } else {
                Log::warning('Customer info auto-sync failed', [
                    'info_id' => $info->id,
                    'organization_slug' => $organization->slug,
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Customer info auto-sync exception', [
                'info_id' => $info->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function compose(): string
    {
        return "Information: {$this->title}\nDetails: {$this->information}\nCategory: {$this->category}";
    }

    public function render()
    {
        return view('livewire.customer.general-info')->layout('layouts.customer');
    }
}
