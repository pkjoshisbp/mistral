<?php

namespace App\Livewire\Admin;

use App\Models\TokenUsageLog;
use App\Models\User;
use App\Models\Organization;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class TokenUsageAnalytics extends Component
{
    use WithPagination;

    public $search = '';
    public $dateFrom = '';
    public $dateTo = '';
    public $selectedOrganization = '';
    public $selectedEndpointType = '';
    
    protected $queryString = ['search', 'dateFrom', 'dateTo', 'selectedOrganization', 'selectedEndpointType'];

    public function mount()
    {
        // Default to current month
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function updatedSelectedOrganization()
    {
        $this->resetPage();
    }

    public function updatedSelectedEndpointType()
    {
        $this->resetPage();
    }

    public function getStatsProperty()
    {
        $query = $this->getBaseQuery();
        
        return [
            'total_tokens' => $query->sum('tokens_used'),
            'total_requests' => $query->count(),
            'unique_users' => $query->distinct('user_id')->count(),
            'unique_organizations' => $query->distinct('organization_id')->count(),
        ];
    }

    public function getDailyStatsProperty()
    {
        return $this->getBaseQuery()
            ->select(
                DB::raw('DATE(used_at) as date'),
                DB::raw('SUM(tokens_used) as total_tokens'),
                DB::raw('COUNT(*) as total_requests')
            )
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->limit(14)
            ->get();
    }

    public function getTopOrganizationsProperty()
    {
        return $this->getBaseQuery()
            ->select(
                'organization_id',
                DB::raw('SUM(tokens_used) as total_tokens'),
                DB::raw('COUNT(*) as total_requests')
            )
            ->with('organization')
            ->groupBy('organization_id')
            ->orderBy('total_tokens', 'desc')
            ->limit(10)
            ->get();
    }

    public function getEndpointStatsProperty()
    {
        return $this->getBaseQuery()
            ->select(
                'endpoint_type',
                DB::raw('SUM(tokens_used) as total_tokens'),
                DB::raw('COUNT(*) as total_requests'),
                DB::raw('AVG(tokens_used) as avg_tokens')
            )
            ->groupBy('endpoint_type')
            ->orderBy('total_tokens', 'desc')
            ->get();
    }

    private function getBaseQuery()
    {
        $query = TokenUsageLog::query();

        if ($this->dateFrom) {
            $query->where('used_at', '>=', $this->dateFrom . ' 00:00:00');
        }

        if ($this->dateTo) {
            $query->where('used_at', '<=', $this->dateTo . ' 23:59:59');
        }

        if ($this->selectedOrganization) {
            $query->where('organization_id', $this->selectedOrganization);
        }

        if ($this->selectedEndpointType) {
            $query->where('endpoint_type', $this->selectedEndpointType);
        }

        if ($this->search) {
            $query->whereHas('user', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('email', 'like', '%' . $this->search . '%');
            })->orWhereHas('organization', function($q) {
                $q->where('name', 'like', '%' . $this->search . '%');
            });
        }

        return $query;
    }

    public function render()
    {
        $logs = $this->getBaseQuery()
            ->with(['user', 'organization', 'subscription'])
            ->orderBy('used_at', 'desc')
            ->paginate(25);

        $organizations = Organization::orderBy('name')->get();
        $endpointTypes = TokenUsageLog::distinct()->pluck('endpoint_type')->filter();

        return view('livewire.admin.token-usage-analytics', [
            'logs' => $logs,
            'organizations' => $organizations,
            'endpointTypes' => $endpointTypes,
            'stats' => $this->stats,
            'dailyStats' => $this->dailyStats,
            'topOrganizations' => $this->topOrganizations,
            'endpointStats' => $this->endpointStats,
        ])->layout('layouts.admin');
    }
}