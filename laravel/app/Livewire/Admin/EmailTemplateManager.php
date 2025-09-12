<?php

namespace App\Livewire\Admin;

use App\Models\EmailTemplate;
use Livewire\Component;
use Livewire\WithPagination;

class EmailTemplateManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingTemplate = null;
    public $name = '';
    public $subject = '';
    public $content = '';
    public $industry_type = 'general';
    public $variables = [];
    public $description = '';
    public $is_active = true;
    public $variableInput = '';
    public $search = '';
    public $industryFilter = '';

    protected $rules = [
        'name' => 'required|string|max:255',
        'subject' => 'required|string|max:255',
        'content' => 'required|string',
        'industry_type' => 'required|string',
        'description' => 'nullable|string',
        'is_active' => 'boolean',
    ];

    protected $queryString = ['search', 'industryFilter'];

    public function mount()
    {
        $this->resetInputs();
    }

    public function render()
    {
        $templates = EmailTemplate::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('subject', 'like', '%' . $this->search . '%')
                      ->orWhere('description', 'like', '%' . $this->search . '%');
            })
            ->when($this->industryFilter, function ($query) {
                $query->where('industry_type', $this->industryFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        $industries = EmailTemplate::distinct()->pluck('industry_type');

        return view('livewire.admin.email-template-manager', [
            'templates' => $templates,
            'industries' => $industries
        ])->layout('layouts.admin');
    }

    public function openModal($templateId = null)
    {
        $this->resetInputs();
        
        if ($templateId) {
            $this->editingTemplate = EmailTemplate::find($templateId);
            $this->name = $this->editingTemplate->name;
            $this->subject = $this->editingTemplate->subject;
            $this->content = $this->editingTemplate->content;
            $this->industry_type = $this->editingTemplate->industry_type;
            $this->variables = $this->editingTemplate->variables ?? [];
            $this->description = $this->editingTemplate->description ?? '';
            $this->is_active = $this->editingTemplate->is_active;
        }
        
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetInputs();
        $this->resetValidation();
    }

    public function addVariable()
    {
        if ($this->variableInput && !in_array($this->variableInput, $this->variables)) {
            $this->variables[] = $this->variableInput;
            $this->variableInput = '';
        }
    }

    public function removeVariable($index)
    {
        unset($this->variables[$index]);
        $this->variables = array_values($this->variables);
    }

    public function save()
    {
        $this->validate();

        $data = [
            'name' => $this->name,
            'subject' => $this->subject,
            'content' => $this->content,
            'industry_type' => $this->industry_type,
            'variables' => $this->variables,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_by' => auth()->id(),
        ];

        if ($this->editingTemplate) {
            $this->editingTemplate->update($data);
            session()->flash('success', 'Email template updated successfully!');
        } else {
            EmailTemplate::create($data);
            session()->flash('success', 'Email template created successfully!');
        }

        $this->closeModal();
    }

    public function delete($templateId)
    {
        $template = EmailTemplate::find($templateId);
        if ($template) {
            $template->delete();
            session()->flash('success', 'Email template deleted successfully!');
        }
    }

    public function toggleStatus($templateId)
    {
        $template = EmailTemplate::find($templateId);
        if ($template) {
            $template->update(['is_active' => !$template->is_active]);
            session()->flash('success', 'Template status updated successfully!');
        }
    }

    private function resetInputs()
    {
        $this->editingTemplate = null;
        $this->name = '';
        $this->subject = '';
        $this->content = '';
        $this->industry_type = 'general';
        $this->variables = [];
        $this->description = '';
        $this->is_active = true;
        $this->variableInput = '';
    }
}
