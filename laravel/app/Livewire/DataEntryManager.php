<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;

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

    protected $rules = [
        'name' => 'required|min:2',
        'description' => 'required|min:10',
        'price' => 'nullable|numeric',
        'category' => 'nullable',
        'requirements' => 'nullable',
        'duration' => 'nullable',
        'availability' => 'nullable'
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

    public function addEntry()
    {
        $this->validate();

        if (!$this->selectedOrgId) {
            session()->flash('error', 'Please select an organization first.');
            return;
        }

        try {
            $organization = Organization::find($this->selectedOrgId);
            
            // Prepare data based on type
            $data = $this->prepareDataForType();
            
            // Add to vector database
            $aiService = new AiAgentService();
            // Select collection based on dataType
            $collection = "org_{$organization->id}";
            if ($this->dataType === 'info') {
                $collection .= '_document';
            } elseif ($this->dataType === 'service') {
                $collection .= '_service';
            } elseif ($this->dataType === 'product') {
                $collection .= '_product';
            } elseif ($this->dataType === 'faq') {
                $collection .= '_faq';
            } else {
                $collection .= '_document'; // fallback
            }

            // Generate embedding vector using embed() method
            $vector = $aiService->embed($data['content'] ?? '');
            $payload = $data;
            $result = $aiService->addToQdrant($collection, $vector, $payload);
            \Log::debug('Qdrant addToQdrant response', ['result' => $result, 'collection' => $collection, 'payload' => $payload]);
            
            session()->flash('message', ucfirst($this->dataType) . ' added successfully!');
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
    }

    public function getFormFieldsProperty()
    {
        switch ($this->dataType) {
            case 'service':
                return [
                    'name' => 'Service Name',
                    'description' => 'Service Description',
                    'price' => 'Price (₹)',
                    'category' => 'Category',
                    'requirements' => 'Requirements/Preparation',
                    'duration' => 'Duration',
                    'availability' => 'Availability/Timing'
                ];

            case 'product':
                return [
                    'name' => 'Product Name',
                    'description' => 'Product Description',
                    'price' => 'Price (₹)',
                    'category' => 'Category'
                ];

            case 'faq':
                return [
                    'name' => 'Question',
                    'description' => 'Answer',
                    'category' => 'Category'
                ];

            case 'info':
                return [
                    'name' => 'Title',
                    'description' => 'Information',
                    'category' => 'Category'
                ];

            default:
                return [];
        }
    }

    public function render()
    {
        return view('livewire.data-entry-manager');
    }
}
