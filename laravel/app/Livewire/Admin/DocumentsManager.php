<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationDocument;

class DocumentsManager extends Component
{
    public $selectedOrganization = '';
    public $showForm = false;
    public $editingId = null;

    public $title = '';
    public $file = '';
    public $category = '';
    public $description = '';
    public $keywords = '';

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'title' => 'required|string|min:2',
        'category' => 'nullable|string',
        'description' => 'nullable|string',
        'keywords' => 'nullable|string',
        // 'file' => 'required|file', // For file upload, handled separately
    ];

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getDocumentsProperty()
    {
        $q = OrganizationDocument::query()->with('organization')->orderByDesc('id');
        if ($this->selectedOrganization) $q->where('organization_id', $this->selectedOrganization);
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->title = $this->file = $this->category = $this->description = $this->keywords = '';
    }

    public function create()
    {
        $this->validate();
        // File upload logic would go here
        OrganizationDocument::create([
            'organization_id' => $this->selectedOrganization,
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'keywords' => $this->keywords,
            // 'file_path' => $uploadedFilePath
        ]);
        session()->flash('message', 'Document added');
        $this->resetForm();
        $this->showForm = false;
    }

    public function edit($id)
    {
        $doc = OrganizationDocument::find($id);
        if (!$doc) return;
        $this->editingId = $id;
        $this->selectedOrganization = $doc->organization_id;
        $this->title = $doc->title;
        $this->category = $doc->category;
        $this->description = $doc->description;
        $this->keywords = $doc->keywords;
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $doc = OrganizationDocument::find($this->editingId);
        if (!$doc) return;
        $doc->update([
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'keywords' => $this->keywords,
        ]);
        session()->flash('message', 'Document updated');
        $this->resetForm();
        $this->showForm = false;
    }

    public function delete($id)
    {
        OrganizationDocument::destroy($id);
        session()->flash('message', 'Document deleted');
    }

    public function render()
    {
        return view('livewire.admin.documents-manager');
    }
}
