<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\AffiliateVisit;
use App\Models\AffiliateCommission;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class AffiliateDashboard extends Component
{
    use WithPagination;

    public $affiliate;
    public $stats;
    public $selectedPeriod = '30'; // Default to last 30 days
    
    // Link generation properties
    public $newLinkName = '';
    public $newLinkUrl = '/';
    public $showCreateLink = false;

    protected $rules = [
        'newLinkName' => 'required|string|max:255',
        'newLinkUrl' => 'required|url',
    ];

    public function mount()
    {
        $this->affiliate = Auth::user()->affiliate;
        if (!$this->affiliate) {
            abort(403, 'Access denied. No affiliate profile found.');
        }
        $this->loadStats();
    }

    public function updatedSelectedPeriod()
    {
        $this->loadStats();
    }

    public function loadStats()
    {
        $days = $this->selectedPeriod;
        $startDate = now()->subDays($days);

        $this->stats = [
            'total_links' => $this->affiliate->links()->count(),
            'total_visits' => $this->affiliate->visits()->where('visited_at', '>=', $startDate)->count(),
            'total_conversions' => $this->affiliate->visits()->where('converted_at', '>=', $startDate)->count(),
            'total_commissions' => $this->affiliate->commissions()
                ->where('commission_start_date', '>=', $startDate)
                ->where('status', 'approved')
                ->sum('commission_amount'),
            'pending_commissions' => $this->affiliate->commissions()
                ->where('commission_start_date', '>=', $startDate)
                ->where('status', 'pending')
                ->sum('commission_amount'),
            'conversion_rate' => 0,
        ];

        // Calculate conversion rate
        if ($this->stats['total_visits'] > 0) {
            $this->stats['conversion_rate'] = round(($this->stats['total_conversions'] / $this->stats['total_visits']) * 100, 2);
        }
    }

    public function createLink()
    {
        $this->validate();

        $link = $this->affiliate->links()->create([
            'name' => $this->newLinkName,
            'original_url' => $this->newLinkUrl,
            'tracking_code' => \Str::random(12),
        ]);

        $this->newLinkName = '';
        $this->newLinkUrl = '/';
        $this->showCreateLink = false;
        
        $this->dispatch('link-created', ['message' => 'Affiliate link created successfully!']);
        $this->loadStats(); // Refresh stats
    }

    public function toggleLinkStatus($linkId)
    {
        $link = $this->affiliate->links()->findOrFail($linkId);
        $link->update(['is_active' => !$link->is_active]);
        
        $status = $link->is_active ? 'activated' : 'deactivated';
        $this->dispatch('link-updated', ['message' => "Link {$status} successfully!"]);
    }

    public function deleteLink($linkId)
    {
        $link = $this->affiliate->links()->findOrFail($linkId);
        $link->delete();
        
        $this->dispatch('link-deleted', ['message' => 'Link deleted successfully!']);
        $this->loadStats(); // Refresh stats
    }

    public function render()
    {
        $links = $this->affiliate->links()
            ->with(['visits' => function ($query) {
                $query->where('visited_at', '>=', now()->subDays($this->selectedPeriod));
            }])
            ->withCount(['visits', 'conversions'])
            ->paginate(10);

        $recentVisits = $this->affiliate->visits()
            ->with('link')
            ->where('visited_at', '>=', now()->subDays(7))
            ->orderBy('visited_at', 'desc')
            ->take(10)
            ->get();

        $commissions = $this->affiliate->commissions()
            ->where('commission_start_date', '>=', now()->subDays($this->selectedPeriod))
            ->orderBy('commission_start_date', 'desc')
            ->paginate(5);

        return view('livewire.affiliate-dashboard', [
            'links' => $links,
            'recentVisits' => $recentVisits,
            'commissions' => $commissions,
        ])->layout('layouts.affiliate');
    }
}
