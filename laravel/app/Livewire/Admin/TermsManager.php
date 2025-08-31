<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\TermsAndConditions;
use Illuminate\Support\Facades\Auth;

class TermsManager extends Component
{
    public $terms = [];
    public $selectedType = 'terms';
    public $title = '';
    public $content = '';
    public $isEditing = false;
    public $editingId = null;

    public $types = [
        'terms' => 'Terms and Conditions',
        'privacy' => 'Privacy Policy',
        'refund' => 'Refund Policy',
        'support' => 'Support Policy'
    ];

    public function mount()
    {
        $this->loadTerms();
    }

    public function loadTerms()
    {
        $this->terms = TermsAndConditions::orderBy('type')->get();
    }

    public function selectType($type)
    {
        $this->selectedType = $type;
        $this->resetForm();
        
        $existing = TermsAndConditions::where('type', $type)->first();
        if ($existing) {
            $this->title = $existing->title;
            $this->content = $existing->content;
            $this->isEditing = true;
            $this->editingId = $existing->id;
        }
    }

    public function resetForm()
    {
        $this->title = '';
        $this->content = '';
        $this->isEditing = false;
        $this->editingId = null;
    }

    public function save()
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|min:10',
        ]);

        $data = [
            'type' => $this->selectedType,
            'title' => $this->title,
            'content' => $this->content,
            'updated_by' => Auth::id(),
            'is_active' => true
        ];

        if ($this->isEditing && $this->editingId) {
            TermsAndConditions::where('id', $this->editingId)->update($data);
            session()->flash('success', 'Terms updated successfully!');
        } else {
            TermsAndConditions::updateOrCreate(
                ['type' => $this->selectedType],
                $data
            );
            session()->flash('success', 'Terms created successfully!');
        }

        $this->loadTerms();
        $this->resetForm();
    }

    public function delete($id)
    {
        TermsAndConditions::findOrFail($id)->delete();
        session()->flash('success', 'Terms deleted successfully!');
        $this->loadTerms();
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.admin.terms-manager');
    }
}
