<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;

class FaqsManager extends Component
{
    public $selectedOrganization = '';
    public $showForm = false;
    public $editingId = null;

    public $question = '';
    public $answer = '';
    public $category = '';
    public $is_active = true;
    public $sort_order = 0;
    public $keywords = '';

    protected $rules = [
        'selectedOrganization' => 'required|exists:organizations,id',
        'question' => 'required|string|min:3',
        'answer' => 'required|string|min:3',
        'category' => 'nullable|string',
        'is_active' => 'boolean',
        'sort_order' => 'nullable|integer',
        'keywords' => 'nullable|string'
    ];

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getFaqsProperty()
    {
        $q = OrganizationFaq::query()->with('organization')->orderBy('sort_order')->orderByDesc('id');
        if ($this->selectedOrganization) $q->where('organization_id', $this->selectedOrganization);
        return $q->get();
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->question = $this->answer = $this->category = $this->keywords = '';
        $this->is_active = true;
        $this->sort_order = 0;
    }

    public function create()
    {
        $this->validate();
        try {
            $faq = OrganizationFaq::create([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'category' => $this->category,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            // Embed into Qdrant
            $ai = new AiAgentService();
            $content = "FAQ Question: {$this->question}\nAnswer: {$this->answer}\nCategory: {$this->category}";
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = [
                    'content' => $content,
                    'org_id' => $this->selectedOrganization,
                    'type' => 'faq',
                    'keywords' => $this->keywords,
                    'id' => $faq->id
                ];
                $ai->addToQdrant($collection, $vector, $payload, $faq->id);
            }
            session()->flash('message', 'FAQ added');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Add failed: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $this->editingId = $id;
        $this->selectedOrganization = $f->organization_id;
        $this->question = $f->question;
        $this->answer = $f->answer;
        $this->category = $f->category;
        $this->sort_order = $f->sort_order ?? 0;
        $this->is_active = (bool)$f->is_active;
        $this->showForm = true;
    }

    public function update()
    {
        $this->validate();
        $f = OrganizationFaq::find($this->editingId);
        if (!$f) return;
        try {
            $f->update([
                'organization_id' => $this->selectedOrganization,
                'question' => $this->question,
                'answer' => $this->answer,
                'category' => $this->category,
                'sort_order' => $this->sort_order ?? 0,
                'is_active' => $this->is_active
            ]);
            $ai = new AiAgentService();
            $content = "FAQ Question: {$this->question}\nAnswer: {$this->answer}\nCategory: {$this->category}";
            $vector = $ai->embed($content . ' ' . ($this->keywords ?? ''));
            if ($vector) {
                $collection = 'org_' . $this->selectedOrganization . '_data';
                $payload = [
                    'content' => $content,
                    'org_id' => $this->selectedOrganization,
                    'type' => 'faq',
                    'keywords' => $this->keywords,
                    'id' => $f->id
                ];
                $ai->addToQdrant($collection, $vector, $payload, $f->id);
            }
            session()->flash('message', 'FAQ updated');
            $this->resetForm();
            $this->showForm = false;
        } catch (\Throwable $e) {
            session()->flash('error', 'Update failed: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $f = OrganizationFaq::find($id);
        if (!$f) return;
        $orgId = $f->organization_id;
        try {
            $f->delete();
            $ai = new AiAgentService();
            $collection = 'org_' . $orgId . '_data';
            $ai->deleteFromQdrant($collection, $id);
            session()->flash('message', 'Deleted');
        } catch (\Throwable $e) {
            session()->flash('error', 'Delete failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.admin.faqs-manager')->layout('layouts.admin');
    }
}
