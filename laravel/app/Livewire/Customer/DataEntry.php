<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;

class DataEntry extends Component
{
    public $dataType = 'service';
    public $showAddForm = false;

    // Form fields
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
        'category' => 'nullable|string',
        'requirements' => 'nullable|string',
        'duration' => 'nullable|string',
        'availability' => 'nullable|string',
        'keywords' => 'nullable|string'
    ];

    public function addEntry()
    {
        $this->validate();
        $org = Auth::user()->organization;
        if (!$org) {
            session()->flash('error', 'No organization linked to your account.');
            return;
        }

        try {
            $ai = new AiAgentService();
            $data = $this->prepareDataForType();
            $data['org_id'] = $org->id;
            $data['keywords'] = $this->keywords;
            $text = ($data['content'] ?? '') . ' ' . ($this->keywords ?? '');
            $vector = $ai->embed($text);
            $collection = 'org_' . $org->id . '_data';
            $ai->addToQdrant($collection, $vector, $data);
            session()->flash('message', ucfirst($this->dataType) . ' added successfully!');
            $this->resetForm();
            $this->showAddForm = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add entry: ' . $e->getMessage());
        }
    }

    private function prepareDataForType()
    {
        $base = [
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->dataType,
            'type' => 'manual_entry'
        ];
        return match ($this->dataType) {
            'service' => array_merge($base, [
                'content' => "Service: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}",
                'price' => $this->price,
                'requirements' => $this->requirements,
                'duration' => $this->duration,
                'availability' => $this->availability
            ]),
            'product' => array_merge($base, [
                'content' => "Product: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}",
                'price' => $this->price
            ]),
            'faq' => array_merge($base, [
                'content' => "FAQ Question: {$this->name}\nAnswer: {$this->description}\nCategory: {$this->category}",
                'question' => $this->name,
                'answer' => $this->description
            ]),
            'info' => array_merge($base, [
                'content' => "Information: {$this->name}\nDetails: {$this->description}\nCategory: {$this->category}" ,
            ]),
            default => $base,
        };
    }

    public function resetForm()
    {
        $this->name = $this->description = $this->price = $this->category = $this->requirements = $this->duration = $this->availability = $this->keywords = '';
    }

    public function getFormFieldsProperty()
    {
        $fields = match ($this->dataType) {
            'service' => [
                'name' => 'Service Name',
                'description' => 'Service Description',
                'price' => 'Price (₹)',
                'category' => 'Category',
                'requirements' => 'Requirements/Preparation',
                'duration' => 'Duration',
                'availability' => 'Availability/Timing'
            ],
            'product' => [
                'name' => 'Product Name',
                'description' => 'Product Description',
                'price' => 'Price (₹)',
                'category' => 'Category'
            ],
            'faq' => [
                'name' => 'Question',
                'description' => 'Answer',
                'category' => 'Category'
            ],
            'info' => [
                'name' => 'Title',
                'description' => 'Information',
                'category' => 'Category'
            ],
            default => []
        };
        $fields['keywords'] = 'Keywords (comma separated)';
        return $fields;
    }

    public function render()
    {
        return view('livewire.customer.data-entry')->layout('layouts.customer');
    }
}
