<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\OrganizationData;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class Services extends Component
{
    public $showForm = false;
    public $editingId = null;
    public $name='';
    public $description='';
    public $price='';
    public $currency='INR'; // Default to INR
    public $category='';
    public $requirements='';
    public $duration='';
    public $availability='';
    public $keywords='';

    protected $rules = [
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

    private function orgId()
    {
        $user = auth()->user();
        return $user->organization->id ?? $user->organizations->first()->id ?? null;
    }

    public function getServicesProperty()
    {
        return OrganizationData::where('type','service')->where('organization_id',$this->orgId())->orderByDesc('id')->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->name = $this->description = $this->price = $this->category = $this->requirements = $this->duration = $this->availability = $this->keywords = '';
        $this->currency = 'INR'; // Reset to default currency
    }

    public function create()
    {
        $this->validate();
        $orgId = $this->orgId();
        
        try {
            $content = $this->composeContent();
            $record = OrganizationData::create([
                'organization_id' => $orgId,
                'type' => 'service',
                'name' => $this->name,
                'description' => $this->description,
                'content' => $content,
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
            ]);
            
            // Sync to Qdrant using new unified system
            $this->syncServiceToQdrant($record);
            
            session()->flash('message','Service added and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) { 
            session()->flash('error','Add failed: '.$e->getMessage()); 
            Log::error('Customer service create error', ['error' => $e->getMessage()]);
        }
    }

    public function edit($id)
    {
        $r = OrganizationData::where('organization_id',$this->orgId())->find($id);
        if(!$r) return;
        
        $this->editingId = $id;
        $this->name = $r->name;
        $this->description = $r->description;
        $m = $r->metadata ?? [];
        $this->price = $m['price'] ?? '';
        $this->currency = $m['currency'] ?? 'INR'; // Default to INR if not set
        $this->category = $m['category'] ?? '';
        $this->requirements = $m['requirements'] ?? '';
        $this->duration = $m['duration'] ?? '';
        $this->availability = $m['availability'] ?? '';
        $this->keywords = $m['keywords'] ?? '';
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $r = OrganizationData::where('organization_id',$this->orgId())->find($this->editingId);
        if(!$r) return;
        
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
                'name' => $this->name,
                'description' => $this->description,
                'content' => $content,
                'metadata' => $metadata
            ]);
            
            // Sync to Qdrant using new unified system
            $this->syncServiceToQdrant($r);
            
            session()->flash('message','Service updated and synced to AI system');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) { 
            session()->flash('error','Update failed: '.$e->getMessage());
            Log::error('Customer service update error', ['error' => $e->getMessage()]);
        }
    }

    public function delete($id)
    {
        $r = OrganizationData::where('organization_id',$this->orgId())->find($id);
        if(!$r) return;
        
        try {
            $organization = Organization::find($r->organization_id);
            $ai = new AiAgentService();
            
            // Delete from Qdrant using new unified system
            if ($organization) {
                $result = $ai->deleteDataFromQdrant($organization->slug, 'service_' . $r->id);
                Log::info('Customer service deleted from Qdrant', [
                    'service_id' => $r->id,
                    'organization' => $organization->slug,
                    'result' => $result
                ]);
            }
            
            // Delete from database
            $r->delete();
            
            session()->flash('message','Service deleted and removed from AI system');
        } catch (\Throwable $e) { 
            session()->flash('error','Delete failed: '.$e->getMessage());
            Log::error('Customer service delete error', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Sync service to Qdrant using unified system
     */
    private function syncServiceToQdrant($service)
    {
        Log::info('>>> CUSTOMER syncServiceToQdrant called', ['service_id' => $service->id, 'name' => $service->name]);
        
        try {
            $organization = Organization::find($service->organization_id);
            if (!$organization) {
                Log::warning('Customer service sync failed - no organization', ['service_id' => $service->id]);
                return;
            }
            
            $aiService = new AiAgentService();
            
            $items = [
                [
                    'id' => "service_{$service->id}",
                    'title' => $service->name,
                    'content' => $service->content,
                    'category' => $service->metadata['category'] ?? 'integration',
                    'metadata' => [
                        'table_id' => $service->id,
                        'updated_at' => $service->updated_at->toISOString(),
                        'price' => $service->metadata['price'] ?? '',
                        'currency' => $service->metadata['currency'] ?? 'INR',
                        'requirements' => $service->metadata['requirements'] ?? '',
                        'duration' => $service->metadata['duration'] ?? '',
                        'availability' => $service->metadata['availability'] ?? '',
                        'keywords' => $service->metadata['keywords'] ?? '',
                    ]
                ]
            ];
            
            Log::info('>>> CUSTOMER About to call storeDataToQdrant for service', [
                'service_id' => $service->id,
                'organization_slug' => $organization->slug,
                'name' => $service->name,
                'items' => $items
            ]);
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'service', $items);
            
            Log::info('>>> CUSTOMER storeDataToQdrant returned for service', [
                'service_id' => $service->id,
                'result' => $result
            ]);

            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('Customer service auto-sync successful', [
                    'service_id' => $service->id,
                    'organization_slug' => $organization->slug
                ]);
            } else {
                Log::warning('Customer service auto-sync failed', [
                    'service_id' => $service->id,
                    'organization_slug' => $organization->slug,
                    'result' => $result
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('Customer service auto-sync exception', [
                'service_id' => $service->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function composeContent()
    {
        // Format price with proper currency symbol and context
        $priceText = $this->price;
        if (is_numeric($this->price) && $this->price > 0) {
            $currencySymbol = $this->currency === 'USD' ? '$' : '₹';
            $priceText = "{$currencySymbol}{$this->price} {$this->currency}";
        }
        
        return "Service: {$this->name}\nDescription: {$this->description}\nPrice: {$priceText}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}";
    }

    public function render()
    {
        return view('livewire.customer.services')->layout('layouts.customer');
    }
}
