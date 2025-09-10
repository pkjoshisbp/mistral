<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

class ServicesManager extends Component
{
    public $selectedOrganization = '';
    public $showForm = false;
    public $editingId = null;

        // Fields
    public $name = '';
    public $description = '';
    public $price = '';
    public $currency = 'INR';
    public $category = '';
    public $requirements = '';
    public $duration = '';
    public $availability = '';
    public $keywords = '';

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'name' => 'required|string|min:2',
        'description' => 'required|string|min:5',
        'price' => 'nullable|numeric',
        'currency' => 'required|in:INR,USD',
        'category' => 'nullable|string',
        'requirements' => 'nullable|string',
        'duration' => 'nullable|string',
        'availability' => 'nullable|string',
        'keywords' => 'nullable|string'
    ];

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getServicesProperty()
    {
        $q = OrganizationData::where('type', 'service')->with('organization')->orderByDesc('id');
        if ($this->selectedOrganization) {
            $q->where('organization_id', $this->selectedOrganization);
        }
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = $this->description = $this->price = $this->category = $this->requirements = $this->duration = $this->availability = $this->keywords = '';
        $this->currency = 'INR';
    }

    public function create()
    {
        $this->validate();
        try {
            $data = [
                'organization_id' => $this->selectedOrganization,
                'type' => 'service',
                'name' => $this->name,
                'description' => $this->description,
                'content' => $this->composeContent(),
                'metadata' => [
                    'category' => $this->category,
                    'price' => $this->price,
                    'currency' => $this->currency,
                    'requirements' => $this->requirements,
                    'duration' => $this->duration,
                    'availability' => $this->availability,
                    'keywords' => $this->keywords,
                    'type' => 'manual_entry'
                ]
            ];
            $record = OrganizationData::create($data);

            // Sync to Qdrant using new unified system
            $this->syncServiceToQdrant($record);

            session()->flash('message', 'Service added and synced to AI system');
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
        $this->name = $r->name;
        $this->description = $r->description;
        $meta = $r->metadata ?? [];
        $this->price = $meta['price'] ?? '';
        $this->currency = $meta['currency'] ?? 'INR';
        $this->category = $meta['category'] ?? '';
        $this->requirements = $meta['requirements'] ?? '';
        $this->duration = $meta['duration'] ?? '';
        $this->availability = $meta['availability'] ?? '';
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
                'price' => $this->price,
                'currency' => $this->currency,
                'requirements' => $this->requirements,
                'duration' => $this->duration,
                'availability' => $this->availability,
                'keywords' => $this->keywords,
                'type' => 'manual_entry'
            ];
            $r->update([
                'organization_id' => $this->selectedOrganization,
                'name' => $this->name,
                'description' => $this->description,
                'content' => $content,
                'metadata' => $metadata
            ]);
            
            // Sync updated service to Qdrant using new unified system
            $this->syncServiceToQdrant($r);
            
            session()->flash('message', 'Service updated and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $service = OrganizationData::find($id);
        if (!$service) return;
        
        try {
            $organization = Organization::find($service->organization_id);
            $ai = new AiAgentService();
            
            // Delete from Qdrant first using new unified system
            if ($organization) {
                $result = $ai->deleteDataFromQdrant($organization->slug, 'service_' . $service->id);
                \Log::info('Service deleted from Qdrant', [
                    'service_id' => $service->id,
                    'organization' => $organization->slug,
                    'result' => $result
                ]);
            }
            
            // Delete from database
            $service->delete();
            
            session()->flash('message', 'Service deleted and removed from AI system');
        } catch (\Exception $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
            \Log::error('Error deleting service', [
                'service_id' => $id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * Sync service to Qdrant using unified system
     */
    private function syncServiceToQdrant(OrganizationData $service)
    {
        try {
            $ai = new AiAgentService();
            $organization = Organization::find($service->organization_id);
            
            if (!$organization) {
                \Log::warning('Organization not found for service sync', ['service_id' => $service->id]);
                return;
            }
            
            // Prepare service data for Qdrant
            $serviceData = [
                'data_type' => 'service',
                'item_id' => 'service_' . $service->id,
                'title' => $service->name,
                'content' => $service->content,
                'category' => $service->metadata['category'] ?? '',
                'organization_slug' => $organization->slug,
                'table_id' => $service->id,
                'updated_at' => $service->updated_at->toISOString(),
                'price' => $service->metadata['price'] ?? '',
                'requirements' => $service->metadata['requirements'] ?? '',
                'duration' => $service->metadata['duration'] ?? '',
                'availability' => $service->metadata['availability'] ?? '',
                'keywords' => $service->metadata['keywords'] ?? '',
            ];
            
            // Use unified store method
            $result = $ai->storeDataToQdrant($organization->slug, 'service', [$serviceData]);
            
            if ($result) {
                \Log::info('Service synced to Qdrant', [
                    'service_id' => $service->id,
                    'organization' => $organization->slug,
                    'item_id' => 'service_' . $service->id
                ]);
            } else {
                \Log::error('Failed to sync service to Qdrant', [
                    'service_id' => $service->id,
                    'organization' => $organization->slug
                ]);
            }
        } catch (\Exception $e) {
            \Log::error('Error syncing service to Qdrant', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function composeContent(): string
    {
        $priceText = '';
        if ($this->price) {
            $symbol = $this->currency === 'USD' ? '$' : '₹';
            $priceText = "Price: {$symbol}{$this->price} {$this->currency}";
        }
        
        return "Service: {$this->name}\nDescription: {$this->description}\n{$priceText}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}";
    }

    public function render()
    {
        return view('livewire.admin.services-manager')->layout('layouts.admin');
    }
}
