<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

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
            $ai = new AiAgentService();
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = $data['metadata'];
                $payload['content'] = $content;
                $payload['org_id'] = $this->selectedOrganization;
                $payload['id'] = $record->id;
                $ai->addToQdrant($collection, $vector, $payload, $record->id);
            }
            session()->flash('message', 'Information added');
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
            $ai = new AiAgentService();
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = $metadata;
                $payload['content'] = $content;
                $payload['org_id'] = $this->selectedOrganization;
                $payload['id'] = $r->id;
                $ai->addToQdrant($collection, $vector, $payload, $r->id);
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
            $ai = new AiAgentService();
            $collection = 'org_' . $orgId . '_data';
            $ai->deleteFromQdrant($collection, $id);
            session()->flash('message', 'Deleted');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    private function composeContent(): string
    {
        return "Information: {$this->title}\nDetails: {$this->information}\nCategory: {$this->category}";
    }

    public function render()
    {
        return view('livewire.admin.general-info-manager')->layout('layouts.admin');
    }
}
