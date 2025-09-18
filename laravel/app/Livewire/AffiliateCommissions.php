<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\AffiliateCommission;

class AffiliateCommissions extends Component
{
    use WithPagination;

    public $statusFilter = 'all';
    public $typeFilter = 'all';

    public function updatedStatusFilter()
    {
        $this->resetPage();
    }

    public function updatedTypeFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $affiliate = auth()->user()->affiliate;
        
        $query = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                  ->with(['user', 'plan']);

        // Apply filters
        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->typeFilter !== 'all') {
            $query->where('commission_type', $this->typeFilter);
        }

        $commissions = $query->orderBy('created_at', 'desc')->paginate(10);

        // Calculate totals
        $totalEarnings = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                         ->where('status', 'approved')
                                         ->sum('commission_amount');

        $pendingEarnings = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                            ->where('status', 'pending')
                                            ->sum('commission_amount');

        $thisMonthEarnings = AffiliateCommission::where('affiliate_id', $affiliate->id)
                                              ->where('status', 'approved')
                                              ->whereMonth('created_at', now()->month)
                                              ->whereYear('created_at', now()->year)
                                              ->sum('commission_amount');

        return view('livewire.affiliate-commissions', compact(
            'commissions', 
            'totalEarnings', 
            'pendingEarnings', 
            'thisMonthEarnings'
        ))->layout('layouts.affiliate');
    }
}