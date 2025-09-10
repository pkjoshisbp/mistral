<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DataEntry extends Component
{
    public $dataType = 'service';
    public $showAddForm = false;
    public $editingId = null;
    public $showEditForm = false;

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
            $data = $this->prepareDataForType();
            $data['org_id'] = $org->id;
            $data['keywords'] = $this->keywords;
            
            // Sync to Qdrant using unified system
            $syncSuccess = $this->syncDataEntryToQdrant($org->slug, $data, $this->dataType);
            
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

    public function editEntry($id)
    {
        $org = Auth::user()->organization;
        if (!$org) {
            session()->flash('error', 'No organization linked to your account.');
            return;
        }

        $entry = \App\Models\OrganizationData::where('organization_id', $org->id)
            ->where('id', $id)
            ->first();

        if ($entry) {
            $this->editingId = $id;
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
        $org = Auth::user()->organization;
        if (!$org) {
            session()->flash('error', 'No organization linked to your account.');
            return;
        }

        try {
            $entry = \App\Models\OrganizationData::where('organization_id', $org->id)
                ->where('id', $this->editingId)
                ->first();

            if ($entry) {
                $data = $this->prepareDataForType();
                $data['org_id'] = $org->id;
                $data['keywords'] = $this->keywords;

                // Update in database
                $entry->update([
                    'name' => $this->name,
                    'description' => $this->description,
                    'type' => $this->dataType,
                    'content' => $data['content'],
                    'metadata' => $data
                ]);

                // Update in Qdrant using unified system
                $syncSuccess = $this->syncDataEntryToQdrant($org->slug, $data, $this->dataType);
                
                if ($syncSuccess) {
                    session()->flash('message', ucfirst($this->dataType) . ' updated and synced successfully!');
                } else {
                    session()->flash('message', ucfirst($this->dataType) . ' updated but sync failed. Please check logs.');
                }
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
        $org = Auth::user()->organization;
        if (!$org) {
            session()->flash('error', 'No organization linked to your account.');
            return;
        }

        try {
            $entry = \App\Models\OrganizationData::where('organization_id', $org->id)
                ->where('id', $id)
                ->first();

            if ($entry) {
                $entryName = $entry->name;
                $entryType = $entry->type ?? 'unknown';
                
                // Delete from database
                $entry->delete();

                // Delete from Qdrant using unified system
                $ai = new AiAgentService();
                $entryId = "{$entryType}_" . md5($entry->name . $entry->content . $entry->id);
                $ai->deleteDataFromQdrant($org->slug, $entryType, $entryId);
                
                Log::info(">>> Customer DataEntry deleted from Qdrant", [
                    'organization_slug' => $org->slug,
                    'data_type' => $entryType,
                    'name' => $entryName,
                    'deleted_id' => $entryId
                ]);

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
                Log::info('>>> Customer DataEntry sync successful', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'name' => $data['name'],
                    'result' => $result
                ]);
                return true;
            } else {
                Log::warning('>>> Customer DataEntry sync failed', [
                    'organization_slug' => $organizationSlug,
                    'data_type' => $dataType,
                    'name' => $data['name'],
                    'result' => $result
                ]);
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('>>> Customer DataEntry sync error', [
                'organization_slug' => $organizationSlug,
                'data_type' => $dataType,
                'name' => $data['name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
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

    public function getExistingEntriesProperty()
    {
        $org = Auth::user()->organization;
        if (!$org) {
            return collect();
        }

        return \App\Models\OrganizationData::where('organization_id', $org->id)
            ->where('type', $this->dataType)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function render()
    {
        return view('livewire.customer.data-entry')->layout('layouts.customer');
    }
}
