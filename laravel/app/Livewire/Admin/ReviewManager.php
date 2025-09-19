<?php

namespace App\Livewire\Admin;

use App\Models\CustomerReview;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

class ReviewManager extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = 'all';
    public $organizationFilter = 'all';
    public $ratingFilter = 'all';
    public $selectedReviews = [];
    public $bulkAction = '';
    public $showModal = false;
    public $editingReview = null;
    public $adminNotes = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'organizationFilter' => ['except' => 'all'],
        'ratingFilter' => ['except' => 'all']
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingOrganizationFilter()
    {
        $this->resetPage();
    }

    public function updatingRatingFilter()
    {
        $this->resetPage();
    }

    public function approveReview($reviewId)
    {
        $review = CustomerReview::findOrFail($reviewId);
        $review->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id()
        ]);

        $this->dispatch('reviewUpdated');
        session()->flash('message', 'Review approved successfully.');
    }

    public function rejectReview($reviewId)
    {
        $review = CustomerReview::findOrFail($reviewId);
        $review->update([
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by' => Auth::id()
        ]);

        $this->dispatch('reviewUpdated');
        session()->flash('message', 'Review rejected successfully.');
    }

    public function toggleFeatured($reviewId)
    {
        $review = CustomerReview::findOrFail($reviewId);
        $review->update(['is_featured' => !$review->is_featured]);

        $this->dispatch('reviewUpdated');
        session()->flash('message', 'Review featured status updated.');
    }

    public function editReview($reviewId)
    {
        $this->editingReview = CustomerReview::findOrFail($reviewId);
        $this->adminNotes = $this->editingReview->admin_notes ?? '';
        $this->showModal = true;
    }

    public function saveAdminNotes()
    {
        if ($this->editingReview) {
            $this->editingReview->update(['admin_notes' => $this->adminNotes]);
            $this->closeModal();
            session()->flash('message', 'Admin notes saved successfully.');
        }
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->editingReview = null;
        $this->adminNotes = '';
    }

    public function deleteReview($reviewId)
    {
        CustomerReview::findOrFail($reviewId)->delete();
        session()->flash('message', 'Review deleted successfully.');
    }

    public function bulkApprove()
    {
        if (empty($this->selectedReviews)) {
            session()->flash('error', 'Please select reviews to approve.');
            return;
        }

        CustomerReview::whereIn('id', $this->selectedReviews)->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => Auth::id()
        ]);

        $this->selectedReviews = [];
        session()->flash('message', 'Selected reviews approved successfully.');
    }

    public function bulkReject()
    {
        if (empty($this->selectedReviews)) {
            session()->flash('error', 'Please select reviews to reject.');
            return;
        }

        CustomerReview::whereIn('id', $this->selectedReviews)->update([
            'status' => 'rejected',
            'approved_at' => null,
            'approved_by' => Auth::id()
        ]);

        $this->selectedReviews = [];
        session()->flash('message', 'Selected reviews rejected successfully.');
    }

    public function bulkDelete()
    {
        if (empty($this->selectedReviews)) {
            session()->flash('error', 'Please select reviews to delete.');
            return;
        }

        CustomerReview::whereIn('id', $this->selectedReviews)->delete();
        $this->selectedReviews = [];
        session()->flash('message', 'Selected reviews deleted successfully.');
    }

    public function render()
    {
        $query = CustomerReview::with(['user', 'organization', 'approvedBy'])
            ->when($this->search, fn($q) => 
                $q->where('title', 'like', '%' . $this->search . '%')
                  ->orWhere('review', 'like', '%' . $this->search . '%')
                  ->orWhereHas('user', fn($q2) => $q2->where('name', 'like', '%' . $this->search . '%'))
            )
            ->when($this->statusFilter !== 'all', fn($q) => $q->where('status', $this->statusFilter))
            ->when($this->organizationFilter !== 'all', fn($q) => $q->where('organization_id', $this->organizationFilter))
            ->when($this->ratingFilter !== 'all', fn($q) => $q->where('rating', $this->ratingFilter))
            ->orderBy('created_at', 'desc');

        $reviews = $query->paginate(20);
        $organizations = Organization::orderBy('name')->get();

        $stats = [
            'total' => CustomerReview::count(),
            'pending' => CustomerReview::where('status', 'pending')->count(),
            'approved' => CustomerReview::where('status', 'approved')->count(),
            'rejected' => CustomerReview::where('status', 'rejected')->count(),
            'featured' => CustomerReview::where('is_featured', true)->count(),
        ];

        return view('livewire.admin.review-manager', compact('reviews', 'organizations', 'stats'));
    }
}
