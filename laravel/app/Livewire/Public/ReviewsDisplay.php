<?php

namespace App\Livewire\Public;

use App\Models\CustomerReview;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;

class ReviewsDisplay extends Component
{
    use WithPagination;

    public $organizationId = null;
    public $organization = null;
    public $organizationName = null;
    public $ratingFilter = 'all';
    public $sortBy = 'latest';
    public $showFeaturedOnly = false;

    protected $queryString = [
        'ratingFilter' => ['except' => 'all'],
        'sortBy' => ['except' => 'latest'],
        'showFeaturedOnly' => ['except' => false]
    ];

    public function mount($organizationId = null)
    {
        if ($organizationId) {
            $this->organizationId = $organizationId;
            $this->organization = Organization::findOrFail($organizationId);
            $this->organizationName = $this->normalizeOrganizationName(data_get($this->organization, 'name'));
        }
    }

    public function updatingRatingFilter()
    {
        $this->resetPage();
    }

    public function updatingSortBy()
    {
        $this->resetPage();
    }

    public function updatingShowFeaturedOnly()
    {
        $this->resetPage();
    }

    public function getReviewsProperty()
    {
        $query = CustomerReview::with(['user', 'organization'])
            ->approved()
            ->when($this->organizationId, fn($q) => $q->where('organization_id', $this->organizationId))
            ->when($this->ratingFilter !== 'all', fn($q) => $q->where('rating', $this->ratingFilter))
            ->when($this->showFeaturedOnly, fn($q) => $q->where('is_featured', true));

        // Apply sorting
        switch ($this->sortBy) {
            case 'rating_high':
                $query->orderBy('rating', 'desc')->orderBy('created_at', 'desc');
                break;
            case 'rating_low':
                $query->orderBy('rating', 'asc')->orderBy('created_at', 'desc');
                break;
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            default: // 'latest'
                $query->orderBy('is_featured', 'desc')->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate(12);
    }

    public function getStatsProperty()
    {
        $baseQuery = CustomerReview::approved();
        if ($this->organizationId) {
            $baseQuery->where('organization_id', $this->organizationId);
        }

        $totalReviews = $baseQuery->count();
        $averageRating = $totalReviews > 0 ? round($baseQuery->avg('rating'), 1) : 0;
        
        $ratingBreakdown = [];
        for ($i = 5; $i >= 1; $i--) {
            $count = (clone $baseQuery)->where('rating', $i)->count();
            $percentage = $totalReviews > 0 ? round(($count / $totalReviews) * 100) : 0;
            $ratingBreakdown[$i] = [
                'count' => $count,
                'percentage' => $percentage
            ];
        }

        return [
            'total' => $totalReviews,
            'average' => $averageRating,
            'breakdown' => $ratingBreakdown
        ];
    }

    public function render()
    {
        $reviews = $this->reviews;
        $stats = $this->stats;
        $organizationName = $this->organizationName ?? $this->normalizeOrganizationName(data_get($this->organization, 'name'));
        
        return view('livewire.public.reviews-display', compact('reviews', 'stats', 'organizationName'));
    }

    private function normalizeOrganizationName($name): ?string
    {
        if (is_array($name)) {
            $name = implode(', ', array_filter(array_map('trim', $name)));
        }

        if (is_object($name)) {
            $name = method_exists($name, '__toString') ? (string) $name : null;
        }

        $name = is_string($name) ? trim($name) : null;

        return $name !== '' ? $name : null;
    }
}
