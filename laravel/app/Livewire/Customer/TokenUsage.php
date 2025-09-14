<?php

namespace App\Livewire\Customer;

use App\Models\TokenUsageLog;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\DB;

class TokenUsage extends Component
{
    use WithPagination;

    public $dateFrom = '';
    public $dateTo = '';
    public $selectedEndpointType = '';
    
    protected $queryString = ['dateFrom', 'dateTo', 'selectedEndpointType'];

    public function mount()
    {
        // Default to current month
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedDateFrom()
    {
        $this->resetPage();
    }

    public function updatedDateTo()
    {
        $this->resetPage();
    }

    public function updatedSelectedEndpointType()
    {
        $this->resetPage();
    }

    public function getOrganizationIdProperty()
    {
        return auth()->user()->organizations->first()->id ?? null;
    }

    public function getStatsProperty()
    {
        if (!$this->organizationId) return [
            'total_tokens' => 0,
            'total_requests' => 0,
            'avg_tokens_per_request' => 0,
        ];

        $query = $this->getBaseQuery();
        
        $totalTokens = $query->sum('tokens_used');
        $totalRequests = $query->count();
        
        return [
            'total_tokens' => $totalTokens,
            'total_requests' => $totalRequests,
            'avg_tokens_per_request' => $totalRequests > 0 ? round($totalTokens / $totalRequests, 1) : 0,
        ];
    }

    public function getDailyStatsProperty()
    {
        if (!$this->organizationId) return collect();

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

    public function getEndpointStatsProperty()
    {
        if (!$this->organizationId) return collect();

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
        if (!$this->organizationId) {
            return TokenUsageLog::whereRaw('1 = 0'); // Return empty query
        }

        $query = TokenUsageLog::where('organization_id', $this->organizationId);

        if ($this->dateFrom) {
            $query->where('used_at', '>=', $this->dateFrom . ' 00:00:00');
        }

        if ($this->dateTo) {
            $query->where('used_at', '<=', $this->dateTo . ' 23:59:59');
        }

        if ($this->selectedEndpointType) {
            $query->where('endpoint_type', $this->selectedEndpointType);
        }

        return $query;
    }

    public function render()
    {
        if (!$this->organizationId) {
            return view('livewire.customer.token-usage', [
                'logs' => collect(),
                'endpointTypes' => collect(),
                'stats' => $this->stats,
                'dailyStats' => $this->dailyStats,
                'endpointStats' => $this->endpointStats,
                'hasOrganization' => false
            ])->layout('layouts.customer');
        }

        $logs = $this->getBaseQuery()
            ->with(['user'])
            ->orderBy('used_at', 'desc')
            ->paginate(25);

        $endpointTypes = TokenUsageLog::where('organization_id', $this->organizationId)
            ->distinct()
            ->pluck('endpoint_type')
            ->filter();

        return view('livewire.customer.token-usage', [
            'logs' => $logs,
            'endpointTypes' => $endpointTypes,
            'stats' => $this->stats,
            'dailyStats' => $this->dailyStats,
            'endpointStats' => $this->endpointStats,
            'hasOrganization' => true
        ])->layout('layouts.customer');
    }
}