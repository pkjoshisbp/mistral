<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class DataEntryManager extends Component
{
    public $organizations;
    public $selectedOrgId;
    public $dataType = 'service';
    public $entries = [];
    public $showAddForm = false;

    // Form fields for different data types
    public $name = '';
    public $description = '';
    public $price = '';
    public $category = '';
    public $requirements = '';
    public $duration = '';
    public $availability = '';
    public $keywords = '';

    protected $rules = [
        'name' => 'required|min:2',
        'description' => 'required|min:10',
        'price' => 'nullable|numeric',
        'category' => 'nullable',
        'requirements' => 'nullable',
        'duration' => 'nullable',
        'availability' => 'nullable',
        'keywords' => 'nullable|string'
    ];

    public function mount()
    {
        $this->organizations = Organization::all();
    }

    public function updatedSelectedOrgId()
    {
        $this->loadEntries();
    }

    public function updatedDataType()
    {
        $this->resetForm();
        $this->loadEntries();
    }

    public function loadEntries()
    {
        // For now, we'll store in a simple format
        // Later this can be moved to a dedicated table
        $this->entries = collect();
    }

    /**
     * Sync data entry to Qdrant using unified system
     */
    private function syncDataEntryToQdrant($organizationSlug, $data, $dataType)
    {
        try {
            $aiService = new AiAgentService();
            
            $items = [
                [
                    'id' => "{$dataType}_" . md5($data['name'] . $data['content'] . time()),
                    'title' => $data['name'],
                    'content' => $data['content'],
                    'category' => $dataType,
                    'metadata' => array_merge($data, [
                        'data_type' => $dataType,
                        'created_at' => now()->toISOString(),
                        'keywords' => $data['keywords'] ?? '',
                    ])
                ]
            ];
            
            $result = $aiService->storeDataToQdrant($organizationSlug, $dataType, $items);
            
            if ($result && $result['success'] && $result['successful_stores'] > 0) {
                Log::info('>>> DataEntryManager sync successful', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'name' => $data['name'],
                    'result' => $result
                ]);
                return true;
            } else {
                Log::warning('>>> DataEntryManager sync failed', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'name' => $data['name'],
                    'result' => $result
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('>>> DataEntryManager sync error', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'name' => $data['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public function addEntry()
    {
        $this->validate();

        if (!$this->selectedOrgId) {
            session()->flash('error', 'Please select an organization first.');
            return;
        }

        try {
            $organization = Organization::find($this->selectedOrgId);
            $data = $this->prepareDataForType();
            $data['keywords'] = $this->keywords;
            
            // Use unified sync system instead of old method
            $syncSuccess = $this->syncDataEntryToQdrant($organization->slug, $data, $this->dataType);
            
            if ($syncSuccess) {
                session()->flash('message', ucfirst($this->dataType) . ' added and synced successfully!');
            } else {
                session()->flash('message', ucfirst($this->dataType) . ' added but sync failed. Please check logs.');
            }
            
            $this->resetForm();
            $this->showAddForm = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add entry: ' . $e->getMessage());
        }
    }

    private function prepareDataForType()
    {
        $baseData = [
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->dataType,
            'type' => 'manual_entry'
        ];

        switch ($this->dataType) {
            case 'service':
                return array_merge($baseData, [
                    'content' => "Service: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}",
                    'price' => $this->price,
                    'requirements' => $this->requirements,
                    'duration' => $this->duration,
                    'availability' => $this->availability
                ]);

            case 'product':
                return array_merge($baseData, [
                    'content' => "Product: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}",
                    'price' => $this->price
                ]);

            case 'faq':
                return array_merge($baseData, [
                    'content' => "FAQ\nQuestion: {$this->name}\nAnswer: {$this->description}\nCategory: {$this->category}",
                    'question' => $this->name,
                    'answer' => $this->description
                ]);

            case 'info':
                return array_merge($baseData, [
                    'content' => "Information: {$this->name}\nDetails: {$this->description}\nCategory: {$this->category}",
                ]);

            default:
                return $baseData;
        }
    }

    public function resetForm()
    {
    $this->name = '';
    $this->description = '';
    $this->price = '';
    $this->category = '';
    $this->requirements = '';
    $this->duration = '';
    $this->availability = '';
    $this->keywords = '';
    }

    public function getFormFieldsProperty()
    {
        $fields = [];
        switch ($this->dataType) {
            case 'service':
                $fields = [
                    'name' => 'Service Name',
                    'description' => 'Service Description',
                    'price' => 'Price (₹)',
                    'category' => 'Category',
                    'requirements' => 'Requirements/Preparation',
                    'duration' => 'Duration',
                    'availability' => 'Availability/Timing'
                ]; break;
            case 'product':
                $fields = [
                    'name' => 'Product Name',
                    'description' => 'Product Description',
                    'price' => 'Price (₹)',
                    'category' => 'Category'
                ]; break;
            case 'faq':
                $fields = [
                    'name' => 'Question',
                    'description' => 'Answer',
                    'category' => 'Category'
                ]; break;
            case 'info':
                $fields = [
                    'name' => 'Title',
                    'description' => 'Information',
                    'category' => 'Category'
                ]; break;
            default:
                $fields = [];
        }
        $fields['keywords'] = 'Keywords (comma separated)';
        return $fields;
    }

    public function render()
    {
        return view('livewire.data-entry-manager');
    }
}
