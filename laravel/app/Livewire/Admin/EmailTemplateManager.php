<?php

namespace App\Livewire\Admin;

use App\Models\EmailTemplate;
use Livewire\Component;
use Livewire\WithPagination;

class EmailTemplateManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $showPreviewModal = false;
    public $previewContent = '';
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
    public $commonPlaceholders = [];

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
        $this->commonPlaceholders = [
            'owner_name' => '{owner_name}',
            'recipient_name' => '{recipient_name}',
            'company_name' => '{company_name}',
            'store_name' => '{store_name}',
            'website_url' => '{website_url}',
            'contact_email' => '{contact_email}',
            'phone_number' => '{phone_number}',
            'support_phone' => '{support_phone}',
        ];
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

    public function updatedIndustryFilter()
    {
        $this->resetPage();
    }

    public function updatedSearch()
    {
        $this->resetPage();
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

    public function insertPlaceholder(string $placeholder)
    {
        $this->content = rtrim((string)$this->content) . ' ' . $placeholder;
    }

    public function insertSubjectPlaceholder(string $placeholder)
    {
        $this->subject = rtrim((string)$this->subject) . ' ' . $placeholder;
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

    public function duplicateTemplate($templateId)
    {
        $template = EmailTemplate::find($templateId);
        if (!$template) return;

        $newTemplate = $template->replicate(['created_at', 'updated_at']);
        $newTemplate->name = $template->name . ' (Copy)';
        $newTemplate->created_by = auth()->id();
        $newTemplate->save();

        session()->flash('success', 'Email template duplicated successfully!');
    }

    public function toggleStatus($templateId)
    {
        $template = EmailTemplate::find($templateId);
        if ($template) {
            $template->update(['is_active' => !$template->is_active]);
            session()->flash('success', 'Template status updated successfully!');
        }
    }

    public function previewTemplate($templateId = null)
    {
        $template = $templateId ? EmailTemplate::find($templateId) : null;
        
        if ($template) {
            // Use existing template content
            $content = $template->content;
        } else {
            // Use current form content for preview during editing
            $content = $this->content;
        }

        // Replace variables with sample data for preview
        $sampleData = [
            'contact_name' => 'John Smith',
            'company_name' => 'Sample Company Inc.',
            'hospital_name' => 'City General Hospital',
            'institution_name' => 'Metro University',
            'organization_name' => 'Community Support NGO',
            'dealership_name' => 'Premier Auto Sales',
            'store_name' => 'Online Store Pro',
            'firm_name' => 'Legal Associates LLC',
            'sender_name' => 'Sarah Johnson',
            'contact_phone' => '+1 (555) 123-4567',
        ];

        $this->previewContent = $content;
        foreach ($sampleData as $variable => $value) {
            $this->previewContent = str_replace('{' . $variable . '}', $value, $this->previewContent);
        }

        $this->showPreviewModal = true;
    }

    public function closePreviewModal()
    {
        $this->showPreviewModal = false;
        $this->previewContent = '';
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
