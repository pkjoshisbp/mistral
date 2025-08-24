<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

class DataSync extends Component
{
    public $organizations;
    public $selectedOrgId;
    public $syncStatus = [];
    public $isLoading = false;

    public function mount()
    {
        $this->organizations = Organization::all();
    }

    public function syncOrganizationData($orgId)
    {
        $this->isLoading = true;
        $this->syncStatus[$orgId] = 'syncing';

        try {
            $organization = Organization::find($orgId);
            $data = OrganizationData::where('organization_id', $orgId)->get();

            $aiService = new AiAgentService();
            
            // Prepare data for syncing
            $syncData = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'description' => $item->description,
                    'content' => $item->content,
                    'type' => $item->type,
                    'metadata' => $item->metadata
                ];
            })->toArray();

            // Sync to Qdrant
            $results = $aiService->syncToQdrant($orgId, $syncData);

            // Update sync status
            OrganizationData::where('organization_id', $orgId)
                ->update([
                    'is_synced' => true,
                    'last_synced_at' => now()
                ]);

            $this->syncStatus[$orgId] = 'completed';
            session()->flash('message', 'Data synced successfully!');

        } catch (\Exception $e) {
            $this->syncStatus[$orgId] = 'failed';
            session()->flash('error', 'Sync failed: ' . $e->getMessage());
        }

        $this->isLoading = false;
    }

    public function render()
    {
        return view('livewire.data-sync');
    }
}
