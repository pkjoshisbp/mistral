<?php

namespace App\Livewire\Admin;

use App\Models\DemoOrganization;
use Livewire\Component;
use Livewire\WithPagination;

class DemoManager extends Component
{
    use WithPagination;

    public $showModal = false;
    public $editingDemo = null;
    public $industry = '';
    public $name = '';
    public $description = '';
    public $features = [];
    public $sample_questions = [];
    public $ai_responses = [];
    public $is_active = true;
    public $search = '';
    
    // Input fields for adding items
    public $featureInput = '';
    public $questionInput = '';
    public $responseQuestion = '';
    public $responseAnswer = '';

    protected $rules = [
        'industry' => 'required|string|max:255',
        'name' => 'required|string|max:255',
        'description' => 'required|string',
        'features' => 'required|array|min:1',
        'sample_questions' => 'required|array|min:1',
        'is_active' => 'boolean',
    ];

    protected $queryString = ['search'];

    public function render()
    {
        $demos = DemoOrganization::query()
            ->when($this->search, fn($query) => 
                $query->where('industry', 'like', '%' . $this->search . '%')
                      ->orWhere('name', 'like', '%' . $this->search . '%')
            )
            ->orderBy('industry')
            ->paginate(10);

        return view('livewire.admin.demo-manager', compact('demos'))
            ->layout('layouts.admin');
    }

    public function openModal($demoId = null)
    {
        $this->resetInputs();
        
        if ($demoId) {
            $this->editingDemo = DemoOrganization::find($demoId);
            $this->industry = $this->editingDemo->industry;
            $this->name = $this->editingDemo->name;
            $this->description = $this->editingDemo->description;
            $this->features = $this->editingDemo->features ?? [];
            $this->sample_questions = $this->editingDemo->sample_questions ?? [];
            $this->ai_responses = $this->editingDemo->ai_responses ?? [];
            $this->is_active = $this->editingDemo->is_active;
        }
        
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetInputs();
        $this->resetValidation();
    }

    public function addFeature()
    {
        if ($this->featureInput && !in_array($this->featureInput, $this->features)) {
            $this->features[] = $this->featureInput;
            $this->featureInput = '';
        }
    }

    public function removeFeature($index)
    {
        unset($this->features[$index]);
        $this->features = array_values($this->features);
    }

    public function addQuestion()
    {
        if ($this->questionInput && !in_array($this->questionInput, $this->sample_questions)) {
            $this->sample_questions[] = $this->questionInput;
            $this->questionInput = '';
        }
    }

    public function removeQuestion($index)
    {
        unset($this->sample_questions[$index]);
        $this->sample_questions = array_values($this->sample_questions);
    }

    public function addResponse()
    {
        if ($this->responseQuestion && $this->responseAnswer) {
            $this->ai_responses[$this->responseQuestion] = $this->responseAnswer;
            $this->responseQuestion = '';
            $this->responseAnswer = '';
        }
    }

    public function removeResponse($question)
    {
        unset($this->ai_responses[$question]);
    }

    public function save()
    {
        $this->validate();

        $data = [
            'industry' => $this->industry,
            'name' => $this->name,
            'description' => $this->description,
            'features' => $this->features,
            'sample_questions' => $this->sample_questions,
            'ai_responses' => $this->ai_responses,
            'is_active' => $this->is_active,
        ];

        if ($this->editingDemo) {
            $this->editingDemo->update($data);
            session()->flash('success', 'Demo organization updated successfully!');
        } else {
            DemoOrganization::create($data);
            session()->flash('success', 'Demo organization created successfully!');
        }

        $this->closeModal();
    }

    public function deleteDemo($id)
    {
        DemoOrganization::findOrFail($id)->delete();
        session()->flash('success', 'Demo deleted successfully!');
    }

    public function syncToQdrant()
    {
        try {
            $demoService = app(\App\Services\DemoQdrantService::class);
            $result = $demoService->syncAllDemoCollections();
            
            if ($result['success']) {
                session()->flash('success', $result['message'] . ' to Qdrant successfully!');
            } else {
                session()->flash('error', 'Failed to sync demo data to Qdrant.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error syncing to Qdrant: ' . $e->getMessage());
        }
    }

    public function toggleStatus($demoId)
    {
        $demo = DemoOrganization::find($demoId);
        if ($demo) {
            $demo->update(['is_active' => !$demo->is_active]);
            session()->flash('success', 'Demo status updated successfully!');
        }
    }

    private function resetInputs()
    {
        $this->editingDemo = null;
        $this->industry = '';
        $this->name = '';
        $this->description = '';
        $this->features = [];
        $this->sample_questions = [];
        $this->ai_responses = [];
        $this->is_active = true;
        $this->featureInput = '';
        $this->questionInput = '';
        $this->responseQuestion = '';
        $this->responseAnswer = '';
    }
}
