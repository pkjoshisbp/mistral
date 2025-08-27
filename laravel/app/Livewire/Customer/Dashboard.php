<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\DataSource;

class Dashboard extends Component
{
    public $organization;
    public $dataSources;
    public $stats = [];

    public function mount()
    {
        $this->organization = auth()->user()->organization;
        if ($this->organization) {
            $this->loadStats();
        }
    }

    private function loadStats()
    {
        $this->dataSources = DataSource::where('organization_id', $this->organization->id)->get();
        
        $this->stats = [
            'total_sources' => $this->dataSources->count(),
            'active_sources' => $this->dataSources->where('status', 'active')->count(),
            'syncing_sources' => $this->dataSources->where('status', 'syncing')->count(),
            'error_sources' => $this->dataSources->where('status', 'error')->count(),
            'last_sync' => $this->dataSources->whereNotNull('last_synced_at')->max('last_synced_at'),
        ];
    }

    public function render()
    {
        return view('livewire.customer.dashboard');
    }
}
