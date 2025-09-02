<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Models\OrganizationFaq;

class DataEntry extends Component
{
    public $dataType = 'service';
    public $showAddForm = false;
    public $editingId = null;
    public $showEditForm = false;
    public $selectedOrganization = null;

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
        'keywords' => 'nullable|string',
        'selectedOrganization' => 'required|exists:organizations,id'
    ];

    public function addEntry()
    {
        $this->validate();
        
        if (!$this->selectedOrganization) {
            session()->flash('error', 'Please select an organization.');
            return;
        }

        try {
            $org = Organization::find($this->selectedOrganization);
            $ai = new AiAgentService();
            $data = $this->prepareDataForType();
            $data['org_id'] = $org->id;
            $data['keywords'] = $this->keywords;
            $text = ($data['content'] ?? '') . ' ' . ($this->keywords ?? '');
            $vector = $ai->embed($text);
            $collection = 'org_' . $org->id . '_data';
            $ai->addToQdrant($collection, $vector, $data);
            
            // Also save to database
            OrganizationData::create([
                'organization_id' => $org->id,
                'type' => $this->dataType,
                'name' => $this->name,
                'description' => $this->description,
                'content' => $data['content'],
                'metadata' => $data
            ]);
            
            session()->flash('message', ucfirst($this->dataType) . ' added successfully for ' . $org->name . '!');
            $this->resetForm();
            $this->showAddForm = false;
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to add entry: ' . $e->getMessage());
        }
    }

    public function editEntry($id)
    {
        $entry = OrganizationData::find($id);

        if ($entry) {
            $this->editingId = $id;
            $this->selectedOrganization = $entry->organization_id;
            $this->name = $entry->name ?? '';
            $this->description = $entry->description ?? '';
            $this->category = $entry->metadata['category'] ?? '';
            $this->price = $entry->metadata['price'] ?? '';
            $this->requirements = $entry->metadata['requirements'] ?? '';
            $this->duration = $entry->metadata['duration'] ?? '';
            $this->availability = $entry->metadata['availability'] ?? '';
            $this->keywords = $entry->metadata['keywords'] ?? '';
            $this->dataType = $entry->type ?? 'service';
            $this->showEditForm = true;
            $this->showAddForm = false;
        }
    }

    public function updateEntry()
    {
        $this->validate();

        try {
            $entry = OrganizationData::find($this->editingId);

            if ($entry) {
                $org = Organization::find($this->selectedOrganization);
                $data = $this->prepareDataForType();
                $data['org_id'] = $org->id;
                $data['keywords'] = $this->keywords;

                // Update in database
                $entry->update([
                    'organization_id' => $this->selectedOrganization,
                    'name' => $this->name,
                    'description' => $this->description,
                    'type' => $this->dataType,
                    'content' => $data['content'],
                    'metadata' => $data
                ]);

                // Update in Qdrant
                $ai = new AiAgentService();
                $text = ($data['content'] ?? '') . ' ' . ($this->keywords ?? '');
                $vector = $ai->embed($text);
                $collection = 'org_' . $org->id . '_data';
                $ai->updateInQdrant($collection, $this->editingId, $vector, $data);

                session()->flash('message', ucfirst($this->dataType) . ' updated successfully!');
                $this->resetForm();
                $this->showEditForm = false;
                $this->editingId = null;
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update entry: ' . $e->getMessage());
        }
    }

    public function deleteEntry($id)
    {
        try {
            $entry = OrganizationData::find($id);

            if ($entry) {
                $orgId = $entry->organization_id;
                
                // Delete from database
                $entry->delete();

                // Delete from Qdrant
                $ai = new AiAgentService();
                $collection = 'org_' . $orgId . '_data';
                $ai->deleteFromQdrant($collection, $id);

                session()->flash('message', 'Entry deleted successfully!');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to delete entry: ' . $e->getMessage());
        }
    }

    public function cancelEdit()
    {
        $this->resetForm();
        $this->showEditForm = false;
        $this->editingId = null;
    }

    private function prepareDataForType()
    {
        $base = [
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'type' => 'manual_entry',
            'price' => $this->price,
            'requirements' => $this->requirements,
            'duration' => $this->duration,
            'availability' => $this->availability
        ];

        return match ($this->dataType) {
            'service' => array_merge($base, [
                'content' => "Service: {$this->name}\nDescription: {$this->description}\nPrice: ₹{$this->price}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}",
            ]),
            'product' => array_merge($base, [
                'content' => "Product: {$this->name}\nDescription: {$this->description}\nPrice: ₹{$this->price}\nCategory: {$this->category}",
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
        $this->selectedOrganization = null;
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

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getExistingEntriesProperty()
    {
        if ($this->dataType === 'faq') {
            // For FAQs, use the OrganizationFaq model
            $query = OrganizationFaq::query();
            
            if ($this->selectedOrganization) {
                $query->where('organization_id', $this->selectedOrganization);
            }
            
            return $query->with('organization')->orderBy('created_at', 'desc')->get();
        } else {
            // For other types, use OrganizationData model
            $query = OrganizationData::where('type', $this->dataType);
            
            if ($this->selectedOrganization) {
                $query->where('organization_id', $this->selectedOrganization);
            }
            
            return $query->with('organization')->orderBy('created_at', 'desc')->get();
        }
    }

    public function render()
    {
        return view('livewire.admin.data-entry')->layout('layouts.admin');
    }
}
