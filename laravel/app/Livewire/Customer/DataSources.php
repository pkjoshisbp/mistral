<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\DataSource;
use App\Services\AiAgentService;

class DataSources extends Component
{
    public $organization;
    public $dataSources;
    public $showCreateForm = false;
    public $editingId = null;
    
    // Form fields
    public $type = '';
    public $name = '';
    public $description = '';
    public $config = [];

    protected $rules = [
        'type' => 'required|in:crawler,file_upload,google_sheets,database,api_push',
        'name' => 'required|string|max:255',
        'description' => 'nullable|string',
        'config' => 'required|array'
    ];

    public function mount()
    {
    $this->organization = auth()->user()->primaryOrganization();
        $this->loadDataSources();
    }

    public function loadDataSources()
    {
        if ($this->organization) {
            $this->dataSources = DataSource::where('organization_id', $this->organization->id)
                ->orderBy('created_at', 'desc')
                ->get();
        }
    }

    public function createDataSource()
    {
        $this->validate();

        if (!$this->organization) {
            session()->flash('error', 'No organization assigned to your account.');
            return;
        }

        DataSource::create([
            'organization_id' => $this->organization->id,
            'type' => $this->type,
            'name' => $this->name,
            'description' => $this->description,
            'config' => $this->config,
            'status' => 'inactive'
        ]);

        $this->resetForm();
        $this->loadDataSources();
        session()->flash('message', 'Data source created successfully!');
    }

    public function editDataSource($id)
    {
        $dataSource = DataSource::find($id);
        if ($dataSource && $dataSource->organization_id === $this->organization->id) {
            $this->editingId = $id;
            $this->type = $dataSource->type;
            $this->name = $dataSource->name;
            $this->description = $dataSource->description;
            $this->config = $dataSource->config;
            $this->showCreateForm = true;
        }
    }

    public function updateDataSource()
    {
        $this->validate();

        $dataSource = DataSource::find($this->editingId);
        if ($dataSource && $dataSource->organization_id === $this->organization->id) {
            $dataSource->update([
                'type' => $this->type,
                'name' => $this->name,
                'description' => $this->description,
                'config' => $this->config
            ]);

            $this->resetForm();
            $this->loadDataSources();
            session()->flash('message', 'Data source updated successfully!');
        }
    }

    public function deleteDataSource($id)
    {
        $dataSource = DataSource::find($id);
        if ($dataSource && $dataSource->organization_id === $this->organization->id) {
            $dataSource->delete();
            $this->loadDataSources();
            session()->flash('message', 'Data source deleted successfully!');
        }
    }

    public function syncDataSource($id)
    {
        $dataSource = DataSource::find($id);
        if ($dataSource && $dataSource->organization_id === $this->organization->id) {
            // Update status to syncing
            $dataSource->update(['status' => 'syncing']);
            
            // Here you would trigger the actual sync process
            // For now, we'll simulate it
            $this->dispatchSync($dataSource);
            
            $this->loadDataSources();
            session()->flash('message', 'Sync started for ' . $dataSource->name);
        }
    }

    private function dispatchSync($dataSource)
    {
        // This would normally dispatch a job to sync the data
        // For demonstration, we'll just update the status
        // In production, this would call the appropriate sync service
    }

    public function toggleCreateForm()
    {
        $this->showCreateForm = !$this->showCreateForm;
        if (!$this->showCreateForm) {
            $this->resetForm();
        }
    }

    public function resetForm()
    {
        $this->editingId = null;
        $this->type = '';
        $this->name = '';
        $this->description = '';
        $this->config = [];
        $this->showCreateForm = false;
    }

    public function getTypeBadgeColor($type)
    {
        $colors = [
            'crawler' => 'info',
            'file_upload' => 'success',
            'google_sheets' => 'warning',
            'database' => 'primary',
            'api_push' => 'secondary'
        ];
        return $colors[$type] ?? 'secondary';
    }

    public function getStatusBadgeColor($status)
    {
        $colors = [
            'active' => 'success',
            'inactive' => 'secondary',
            'syncing' => 'warning',
            'error' => 'danger'
        ];
        return $colors[$status] ?? 'secondary';
    }

    public function render()
    {
        return view('livewire.customer.data-sources')
            ->layout('layouts.customer');
    }
}
