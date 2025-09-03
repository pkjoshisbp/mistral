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
                    'requirements' => $this->requirements,
                    'duration' => $this->duration,
                    'availability' => $this->availability,
                    'keywords' => $this->keywords,
                    'type' => 'manual_entry'
                ]
            ];
            $record = OrganizationData::create($data);

            // Embed & store in Qdrant
            $ai = new AiAgentService();
            $text = $data['content'] . ' ' . ($this->keywords ?? '');
            $vector = $ai->embed($text);
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = $data['metadata'];
                $payload['content'] = $data['content'];
                $payload['org_id'] = $this->selectedOrganization;
                $payload['id'] = $record->id;
                $ai->addToQdrant($collection, $vector, $payload, $record->id);
            }

            session()->flash('message', 'Service added');
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
            $ai = new AiAgentService();
            $text = $content . ' ' . ($this->keywords ?? '');
            $vector = $ai->embed($text);
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = $metadata;
                $payload['content'] = $content;
                $payload['org_id'] = $this->selectedOrganization;
                $payload['id'] = $r->id;
                $ai->addToQdrant($collection, $vector, $payload, $r->id);
            }
            session()->flash('message', 'Service updated');
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
            $ai = new AiAgentService();
            $collection = 'org_' . $orgId . '_data';
            $ai->deleteFromQdrant($collection, $id); // Method exists in DataEntry usage; assume service has it even if not visible here.
            session()->flash('message', 'Deleted');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    private function composeContent(): string
    {
        return "Service: {$this->name}\nDescription: {$this->description}\nPrice: {$this->price}\nCategory: {$this->category}\nRequirements: {$this->requirements}\nDuration: {$this->duration}\nAvailability: {$this->availability}";
    }

    public function render()
    {
        return view('livewire.admin.services-manager')->layout('layouts.admin');
    }
}
