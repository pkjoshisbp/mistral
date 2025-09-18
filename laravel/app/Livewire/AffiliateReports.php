<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\AffiliateCommission;
use App\Models\AffiliateVisit;
use App\Models\AffiliateLink;
use Carbon\Carbon;

class AffiliateReports extends Component
{
    public $dateRange = '30';
    public $selectedLink = 'all';

    public function updatedDateRange()
    {
        $this->dispatch('refreshCharts');
    }

    public function updatedSelectedLink()
    {
        $this->dispatch('refreshCharts');
    }

    public function render()
    {
        $affiliate = auth()->user()->affiliate;
        $startDate = Carbon::now()->subDays($this->dateRange);

        // Get base query
        $linksQuery = AffiliateLink::where('affiliate_id', $affiliate->id);
        $visitsQuery = AffiliateVisit::where('affiliate_id', $affiliate->id)
                                   ->where('visited_at', '>=', $startDate);
        $commissionsQuery = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                             ->where('created_at', '>=', $startDate);

        // Apply link filter if specified
        if ($this->selectedLink !== 'all') {
            $visitsQuery->where('link_id', $this->selectedLink);
            // Note: Commissions are not directly linked to specific links in our current schema
        }

        // Get statistics
        $totalVisits = $visitsQuery->count();
        $totalConversions = $visitsQuery->whereNotNull('converted_user_id')->count();
        $conversionRate = $totalVisits > 0 ? ($totalConversions / $totalVisits) * 100 : 0;
        $totalEarnings = $commissionsQuery->where('status', 'approved')->sum('commission_amount');

        // Get daily stats for charts
        $dailyStats = [];
        for ($i = $this->dateRange; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dayVisits = AffiliateVisit::where('affiliate_id', $affiliate->id)
                                    ->whereDate('visited_at', $date)
                                    ->when($this->selectedLink !== 'all', function($q) {
                                        $q->where('link_id', $this->selectedLink);
                                    })
                                    ->count();
            
            $dayConversions = AffiliateVisit::where('affiliate_id', $affiliate->id)
                                          ->whereDate('visited_at', $date)
                                          ->whereNotNull('converted_user_id')
                                          ->when($this->selectedLink !== 'all', function($q) {
                                              $q->where('link_id', $this->selectedLink);
                                          })
                                          ->count();
            
            $dayEarnings = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                            ->whereDate('created_at', $date)
                                            ->where('status', 'approved')
                                            ->sum('commission_amount');

            $dailyStats[] = [
                'date' => $date->format('M j'),
                'visits' => $dayVisits,
                'conversions' => $dayConversions,
                'earnings' => (float) $dayEarnings
            ];
        }

        // Get top performing links
        $topLinks = AffiliateLink::where('affiliate_id', $affiliate->id)
                                ->withCount(['visits' => function($query) use ($startDate) {
                                    $query->where('visited_at', '>=', $startDate);
                                }])
                                ->withCount(['visits as conversions_count' => function($query) use ($startDate) {
                                    $query->where('visited_at', '>=', $startDate)
                                          ->whereNotNull('converted_user_id');
                                }])
                                ->orderBy('visits_count', 'desc')
                                ->limit(5)
                                ->get();

        // Get all links for filter dropdown
        $allLinks = AffiliateLink::where('affiliate_id', $affiliate->id)->get();

        return view('livewire.affiliate-reports', compact(
            'totalVisits',
            'totalConversions', 
            'conversionRate',
            'totalEarnings',
            'dailyStats',
            'topLinks',
            'allLinks'
        ))->layout('layouts.affiliate');
    }
}