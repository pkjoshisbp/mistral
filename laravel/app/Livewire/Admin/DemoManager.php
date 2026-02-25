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
    public $questionAnswerInput = '';
    public $questionKeywordsInput = '';
    public $responseQuestion = '';
    public $responseAnswer = '';
    public $editingQuestionIndex = null;
    public $editingQuestionValue = '';
    public $editingQuestionAnswer = '';
    public $editingQuestionKeywords = '';
    public $showFaqForm = false;

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
            $this->sample_questions = $this->normalizeSampleQuestions(
                $this->editingDemo->sample_questions ?? [],
                $this->editingDemo->ai_responses ?? []
            );
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
        $question = trim((string) $this->questionInput);
        $answer = trim((string) $this->questionAnswerInput);
        $keywords = trim((string) $this->questionKeywordsInput);

        if ($question === '' || $answer === '') {
            return;
        }

        foreach ($this->sample_questions as $entry) {
            $existingQuestion = trim((string) ($entry['question'] ?? ''));
            if ($existingQuestion !== '' && strcasecmp($existingQuestion, $question) === 0) {
                return;
            }
        }

        $this->sample_questions[] = [
            'question' => $question,
            'answer' => $answer,
            'keywords' => $keywords,
        ];

        $this->questionInput = '';
        $this->questionAnswerInput = '';
        $this->questionKeywordsInput = '';
        $this->showFaqForm = false;

        $this->persistFaqChanges();
    }

    public function removeQuestion($index)
    {
        unset($this->sample_questions[$index]);
        $this->sample_questions = array_values($this->sample_questions);

        if ($this->editingQuestionIndex !== null && $this->editingQuestionIndex >= count($this->sample_questions)) {
            $this->cancelQuestionEdit();
        }

        $this->persistFaqChanges();
    }

    public function startQuestionEdit($index)
    {
        if (!isset($this->sample_questions[$index]) || !is_array($this->sample_questions[$index])) {
            return;
        }

        $this->editingQuestionIndex = $index;
        $this->editingQuestionValue = (string) ($this->sample_questions[$index]['question'] ?? '');
        $this->editingQuestionAnswer = (string) ($this->sample_questions[$index]['answer'] ?? '');
        $this->editingQuestionKeywords = (string) ($this->sample_questions[$index]['keywords'] ?? '');
        $this->showFaqForm = true;
    }

    public function saveQuestionEdit()
    {
        if ($this->editingQuestionIndex === null) {
            return;
        }

        $newQuestion = trim((string) $this->editingQuestionValue);
        $newAnswer = trim((string) $this->editingQuestionAnswer);
        $newKeywords = trim((string) $this->editingQuestionKeywords);

        if ($newQuestion === '' || $newAnswer === '') {
            return;
        }

        foreach ($this->sample_questions as $index => $entry) {
            $existingQuestion = trim((string) ($entry['question'] ?? ''));
            if ($index !== $this->editingQuestionIndex && $existingQuestion !== '' && strcasecmp($existingQuestion, $newQuestion) === 0) {
                return;
            }
        }

        $this->sample_questions[$this->editingQuestionIndex] = [
            'question' => $newQuestion,
            'answer' => $newAnswer,
            'keywords' => $newKeywords,
        ];
        $this->sample_questions = array_values($this->sample_questions);
        $this->cancelQuestionEdit();

        $this->persistFaqChanges();
    }

    public function cancelQuestionEdit()
    {
        $this->editingQuestionIndex = null;
        $this->editingQuestionValue = '';
        $this->editingQuestionAnswer = '';
        $this->editingQuestionKeywords = '';
        $this->showFaqForm = false;
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
        $this->sample_questions = $this->normalizeSampleQuestions($this->sample_questions, $this->ai_responses);
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
            $this->syncDemoToQdrant($this->editingDemo);
            session()->flash('success', 'Demo organization updated successfully!');
        } else {
            $created = DemoOrganization::create($data);
            $this->syncDemoToQdrant($created);
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

    private function persistFaqChanges(): void
    {
        if (!$this->editingDemo) {
            return;
        }

        $this->editingDemo->update([
            'sample_questions' => $this->sample_questions,
        ]);

        $this->syncDemoToQdrant($this->editingDemo);
    }

    private function syncDemoToQdrant(DemoOrganization $demo): void
    {
        try {
            app(\App\Services\DemoQdrantService::class)->syncDemoCollection($demo->fresh());
        } catch (\Exception $e) {
            session()->flash('error', 'FAQ saved but Qdrant sync failed: ' . $e->getMessage());
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
        $this->questionAnswerInput = '';
        $this->questionKeywordsInput = '';
        $this->responseQuestion = '';
        $this->responseAnswer = '';
        $this->editingQuestionIndex = null;
        $this->editingQuestionValue = '';
        $this->editingQuestionAnswer = '';
        $this->editingQuestionKeywords = '';
        $this->showFaqForm = false;
    }

    private function normalizeSampleQuestions(array $questions, array $aiResponses = []): array
    {
        $normalized = [];

        foreach ($questions as $entry) {
            if (is_array($entry)) {
                $question = trim((string) ($entry['question'] ?? ''));
                $answer = trim((string) ($entry['answer'] ?? ''));
                $keywords = trim((string) ($entry['keywords'] ?? ''));
            } else {
                $question = trim((string) $entry);
                $answer = trim((string) ($aiResponses[$question] ?? ''));
                $keywords = '';
            }

            if ($question === '') {
                continue;
            }

            $exists = false;
            foreach ($normalized as $item) {
                if (strcasecmp($item['question'], $question) === 0) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                continue;
            }

            $normalized[] = [
                'question' => $question,
                'answer' => $answer,
                'keywords' => $keywords,
            ];
        }

        return array_values($normalized);
    }
}
