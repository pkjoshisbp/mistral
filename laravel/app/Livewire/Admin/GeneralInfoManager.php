<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class GeneralInfoManager extends Component
{
    public $selectedOrganization = '';
    public $showForm = false;
    public $editingId = null;

    public $title = '';
    public $information = '';
    public $category = '';
    public $keywords = '';

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'title' => 'required|string|min:2',
        'information' => 'required|string|min:5',
        'category' => 'nullable|string',
        'keywords' => 'nullable|string'
    ];

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getInfosProperty()
    {
        $q = OrganizationData::where('type', 'info')->with('organization')->orderByDesc('id');
        if ($this->selectedOrganization) $q->where('organization_id', $this->selectedOrganization);
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->title = $this->information = $this->category = $this->keywords = '';
    }

    private function composeContent()
    {
        return "Information: {$this->title}\nDetails: {$this->information}\nCategory: {$this->category}";
    }

    /**
     * Sync info to Qdrant using unified system
     */
    private function syncInfoToQdrant($organizationSlug, $infoItems)
    {
        try {
            $aiService = new AiAgentService();
            
            $items = [];
            foreach ($infoItems as $info) {
                $items[] = [
                    'id' => "info_{$info->id}",
                    'title' => $info->name,
                    'content' => $info->content,
                    'category' => $info->metadata['category'] ?? 'general',
                    'metadata' => [
                        'table_id' => $info->id,
                        'updated_at' => $info->updated_at->toISOString(),
                        'keywords' => $info->metadata['keywords'] ?? '',
                    ]
                ];
            }
            
            $result = $aiService->storeDataToQdrant($organizationSlug, 'general_info', $items);
            
            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('>>> Admin GeneralInfoManager sync successful', [
                    'organization_slug' => $organizationSlug,
                    'items_count' => count($items),
                    'result' => $result
                ]);
            } else {
                Log::warning('>>> Admin GeneralInfoManager sync failed', [
                    'organization_slug' => $organizationSlug,
                    'items_count' => count($items),
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('>>> Admin GeneralInfoManager sync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function create()
    {
        $this->validate();
        try {
            $content = $this->composeContent();
            $data = [
                'organization_id' => $this->selectedOrganization,
                'type' => 'info',
                'name' => $this->title,
                'description' => $this->information,
                'content' => $content,
                'metadata' => [
                    'category' => $this->category,
                    'keywords' => $this->keywords,
                    'type' => 'manual_entry'
                ]
            ];
            $record = OrganizationData::create($data);

            // Sync to Qdrant using unified system
            $organization = Organization::find($this->selectedOrganization);
            if ($organization) {
                $this->syncInfoToQdrant($organization->slug, [$record]);
            }

            session()->flash('message', 'Information added and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $r = OrganizationData::find($id);
        if (!$r) return;
        $this->editingId = $id;
        $this->selectedOrganization = $r->organization_id;
        $this->title = $r->name;
        $this->information = $r->description;
        $meta = $r->metadata ?? [];
        $this->category = $meta['category'] ?? '';
        $this->keywords = $meta['keywords'] ?? '';
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $r = OrganizationData::find($this->editingId);
        if (!$r) return;
        try {
            $content = $this->composeContent();
            $metadata = [
                'category' => $this->category,
                'keywords' => $this->keywords,
                'type' => 'manual_entry'
            ];
            $r->update([
                'organization_id' => $this->selectedOrganization,
                'name' => $this->title,
                'description' => $this->information,
                'content' => $content,
                'metadata' => $metadata
            ]);

            // Sync to Qdrant using unified system
            $organization = Organization::find($this->selectedOrganization);
            if ($organization) {
                $this->syncInfoToQdrant($organization->slug, [$r]);
            }
            
            session()->flash('message', 'Information updated');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $r = OrganizationData::find($id);
        if (!$r) return;
        $orgId = $r->organization_id;
        try {
            $r->delete();
            
            // Delete from Qdrant using unified system
            $organization = Organization::find($orgId);
            if ($organization) {
                $ai = new AiAgentService();
                $ai->deleteDataFromQdrant($organization->slug, 'general_info', "info_$id");
                Log::info(">>> Admin GeneralInfoManager deleted from Qdrant", [
                    'organization_slug' => $organization->slug,
                    'data_type' => 'general_info',
                    'deleted_id' => "info_$id"
                ]);
            }
            
            session()->flash('message', 'Deleted');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.general-info-manager')
            ->layout('layouts.admin');
    }
}
