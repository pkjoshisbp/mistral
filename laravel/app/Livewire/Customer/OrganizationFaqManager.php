<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use App\Models\OrganizationFaq;
use App\Models\Organization;
use App\Http\Controllers\Api\FaqSyncController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class OrganizationFaqManager extends Component
{
    use WithFileUploads, WithPagination;

    public $organization;
    public $showAddForm = false;
    public $showImportForm = false;
    public $editingFaq = null;
    public $csvFile;
    public $replaceExisting = false;

    // Form fields
    public $question = '';
    public $answer = '';
    public $category = '';

    // Filters
    public $search = '';
    public $categoryFilter = '';

    protected $rules = [
        'question' => 'required|string|max:1000',
        'answer' => 'required|string|max:5000',
        'category' => 'nullable|string|max:255',
    ];

    public function mount()
    {
        $user = Auth::user();
        if ($user && $user->role === 'customer' && $user->organization) {
            $this->organization = $user->organization;
        } else {
            abort(403, 'Access denied');
        }
    }

    public function render()
    {
        $query = OrganizationFaq::where('organization_id', $this->organization->id)
            ->where('is_active', true);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('question', 'like', '%' . $this->search . '%')
                  ->orWhere('answer', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }

        $faqs = $query->orderBy('sort_order')->orderBy('created_at', 'desc')->paginate(10);

        $categories = OrganizationFaq::where('organization_id', $this->organization->id)
            ->where('is_active', true)
            ->distinct()
            ->pluck('category')
            ->filter();

        $stats = [
            'total' => OrganizationFaq::where('organization_id', $this->organization->id)->where('is_active', true)->count(),
            'categories' => $categories->count()
        ];

        return view('livewire.customer.organization-faq-manager', compact('faqs', 'categories', 'stats'));
    }

    public function showAddForm()
    {
        $this->resetForm();
        $this->showAddForm = true;
    }

    public function hideAddForm()
    {
        $this->showAddForm = false;
        $this->resetForm();
    }

    public function showImportForm()
    {
        $this->showImportForm = true;
    }

    public function hideImportForm()
    {
        $this->showImportForm = false;
        $this->csvFile = null;
        $this->replaceExisting = false;
    }

    public function editFaq($faqId)
    {
        $faq = OrganizationFaq::where('organization_id', $this->organization->id)->findOrFail($faqId);
        $this->editingFaq = $faq;
        $this->question = $faq->question;
        $this->answer = $faq->answer;
        $this->category = $faq->category;
        $this->showAddForm = true;
    }

    public function saveFaq()
    {
        $this->validate();

        try {
            if ($this->editingFaq) {
                // Update existing FAQ
                $this->editingFaq->update([
                    'question' => $this->question,
                    'answer' => $this->answer,
                    'category' => $this->category ?: 'General'
                ]);
                $message = 'FAQ updated successfully';
                $this->editingFaq = null;
            } else {
                // Create new FAQ
                OrganizationFaq::create([
                    'organization_id' => $this->organization->id,
                    'question' => $this->question,
                    'answer' => $this->answer,
                    'category' => $this->category ?: 'General',
                    'is_active' => true,
                    'sort_order' => 999
                ]);
                $message = 'FAQ created successfully';
            }

            // Sync to Qdrant
            $this->syncToQdrant();

            session()->flash('message', $message);
            $this->hideAddForm();
            $this->resetPage();

        } catch (\Exception $e) {
            Log::error('FAQ save error: ' . $e->getMessage());
            session()->flash('error', 'Failed to save FAQ: ' . $e->getMessage());
        }
    }

    public function deleteFaq($faqId)
    {
        try {
            $faq = OrganizationFaq::where('organization_id', $this->organization->id)->findOrFail($faqId);
            $faq->update(['is_active' => false]);

            // Sync to Qdrant
            $this->syncToQdrant();

            session()->flash('message', 'FAQ deleted successfully');
        } catch (\Exception $e) {
            Log::error('FAQ delete error: ' . $e->getMessage());
            session()->flash('error', 'Failed to delete FAQ');
        }
    }

    public function importCsv()
    {
        $this->validate([
            'csvFile' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        try {
            // Create a temporary request to use the existing API controller
            $request = new Request();
            $request->files->set('csv_file', $this->csvFile->path());
            $request->merge([
                'organization_id' => $this->organization->id,
                'replace_existing' => $this->replaceExisting
            ]);

            $controller = new FaqSyncController();
            $response = $controller->importFromCsv($request);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getContent(), true);
                session()->flash('message', $data['message'] . " (Synced: {$data['synced_count']}, Updated: {$data['updated_count']})");
                $this->hideImportForm();
                $this->resetPage();
            } else {
                $error = json_decode($response->getContent(), true);
                session()->flash('error', $error['message'] ?? 'Failed to import CSV');
            }

        } catch (\Exception $e) {
            Log::error('CSV import error: ' . $e->getMessage());
            session()->flash('error', 'Failed to import CSV: ' . $e->getMessage());
        }
    }

    public function syncAllFaqs()
    {
        try {
            $this->syncToQdrant();
            session()->flash('message', 'FAQs synced to search database successfully');
        } catch (\Exception $e) {
            Log::error('FAQ sync error: ' . $e->getMessage());
            session()->flash('error', 'Failed to sync FAQs: ' . $e->getMessage());
        }
    }

    private function syncToQdrant()
    {
        $controller = new FaqSyncController();
        $controller->syncToQdrant($this->organization);
    }

    private function resetForm()
    {
        $this->question = '';
        $this->answer = '';
        $this->category = '';
        $this->editingFaq = null;
        $this->resetValidation();
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }
}