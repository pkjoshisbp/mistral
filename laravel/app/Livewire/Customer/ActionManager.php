<?php

namespace App\Livewire\Customer;

use App\Models\OrganizationAction;
use App\Models\Organization;
use App\Services\ActionService;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Log;

class ActionManager extends Component
{
    use WithPagination;

    public $search = '';
    public $showModal = false;
    public $editingAction = null;
    public $organizationId;
    
    // Form fields (simplified for customers)
    public $name = '';
    public $action_type = '';
    public $description = '';
    public $source_type = '';
    public $source_config = [];
    public $keywords = [];
    public $is_active = true;
    
    // Helper fields
    public $keywordInput = '';
    
    // Source config fields
    public $api_url = '';
    public $csv_file_path = '';
    public $excel_file_path = '';
    public $db_table = '';
    public $sheets_spreadsheet_id = '';
    public $sheets_range = 'A:Z';

    protected $rules = [
        'name' => 'required|string|max:255',
        'action_type' => 'required|string|max:255',
        'description' => 'required|string',
        'source_type' => 'required|in:api,csv,excel,google_sheets,database',
    ];

    public function mount()
    {
        $user = auth()->user();
        $organization = $user->organizations->first();
        
        if (!$organization) {
            return redirect()->route('customer.setup-organization');
        }
        
        $this->organizationId = $organization->id;
        $this->resetForm();
    }

    public function render()
    {
        $query = OrganizationAction::where('organization_id', $this->organizationId)
            ->when($this->search, fn($q) => 
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('action_type', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
            )
            ->orderBy('name');

        $actions = $query->paginate(10);

        return view('livewire.customer.action-manager', compact('actions'))
            ->layout('layouts.customer');
    }

    public function openModal($actionId = null)
    {
        $this->resetForm();
        
        if ($actionId) {
            $this->editingAction = OrganizationAction::where('organization_id', $this->organizationId)->find($actionId);
            
            if (!$this->editingAction) {
                session()->flash('error', 'Action not found!');
                return;
            }
            
            $this->name = $this->editingAction->name;
            $this->action_type = $this->editingAction->action_type;
            $this->description = $this->editingAction->description;
            $this->keywords = $this->editingAction->keywords ?? [];
            $this->source_type = $this->editingAction->source_type;
            $this->source_config = $this->editingAction->source_config ?? [];
            $this->is_active = $this->editingAction->is_active;
            
            $this->loadSourceConfigFields();
        }
        
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
        $this->resetValidation();
    }

    public function save()
    {
        $this->validate();
        
        // Build source config based on type
        $this->buildSourceConfig();
        
        $data = [
            'organization_id' => $this->organizationId,
            'name' => $this->name,
            'action_type' => $this->action_type,
            'description' => $this->description,
            'keywords' => array_filter($this->keywords),
            'source_type' => $this->source_type,
            'source_config' => $this->source_config,
            'is_active' => $this->is_active,
            // Set reasonable defaults for customer actions
            'aliases' => [],
            'params_template' => [],
            'required_params' => [],
            'optional_params' => [],
            'min_score_threshold' => 0.75,
            'cache_ttl' => 300,
            'roles_allowed' => [],
            'response_template' => '',
            'output_format' => 'text',
        ];

        if ($this->editingAction) {
            $this->editingAction->update($data);
            session()->flash('success', 'Action updated successfully!');
        } else {
            $action = OrganizationAction::create($data);
            session()->flash('success', 'Action created successfully!');
            
            // Sync to vector database
            try {
                $actionService = app(ActionService::class);
                $actionService->syncActionToVectorDB($action);
            } catch (\Exception $e) {
                Log::warning('Failed to sync action to vector DB', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->closeModal();
    }

    public function delete($actionId)
    {
        $action = OrganizationAction::where('organization_id', $this->organizationId)->find($actionId);
        
        if ($action) {
            // Remove from vector database first
            try {
                $actionService = app(ActionService::class);
                $actionService->removeActionFromVectorDB($action);
            } catch (\Exception $e) {
                Log::warning('Failed to remove action from vector DB', [
                    'action_id' => $action->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $action->delete();
            session()->flash('success', 'Action deleted successfully!');
        }
    }

    public function toggleStatus($actionId)
    {
        $action = OrganizationAction::where('organization_id', $this->organizationId)->find($actionId);
        if ($action) {
            $action->update(['is_active' => !$action->is_active]);
            session()->flash('success', 'Action status updated!');
        }
    }

    public function testAction($actionId)
    {
        $action = OrganizationAction::where('organization_id', $this->organizationId)->find($actionId);
        
        if (!$action) {
            session()->flash('error', 'Action not found!');
            return;
        }

        try {
            $actionService = app(ActionService::class);
            $testQuery = "Test query for " . $action->name;
            
            $result = $actionService->processQuery($testQuery, $action->organization_id);
            
            if ($result['type'] === 'action_executed' && $result['result']['success']) {
                session()->flash('success', 'Action test successful! Retrieved ' . 
                    (is_array($result['result']['data']) ? count($result['result']['data']) : 1) . ' records.');
            } else {
                session()->flash('error', 'Action test failed: ' . ($result['result']['error'] ?? 'Unknown error'));
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Action test failed: ' . $e->getMessage());
        }
    }

    // Helper methods for form management
    public function addKeyword()
    {
        if ($this->keywordInput && !in_array($this->keywordInput, $this->keywords)) {
            $this->keywords[] = $this->keywordInput;
            $this->keywordInput = '';
        }
    }

    public function removeKeyword($index)
    {
        unset($this->keywords[$index]);
        $this->keywords = array_values($this->keywords);
    }

    private function resetForm()
    {
        $this->editingAction = null;
        $this->name = '';
        $this->action_type = '';
        $this->description = '';
        $this->keywords = [];
        $this->source_type = '';
        $this->source_config = [];
        $this->is_active = true;
        
        $this->keywordInput = '';
        
        $this->resetSourceConfigFields();
    }

    private function resetSourceConfigFields()
    {
        $this->api_url = '';
        $this->csv_file_path = '';
        $this->excel_file_path = '';
        $this->db_table = '';
        $this->sheets_spreadsheet_id = '';
        $this->sheets_range = 'A:Z';
    }

    private function loadSourceConfigFields()
    {
        $config = $this->source_config;
        
        switch ($this->source_type) {
            case 'api':
                $this->api_url = $config['url'] ?? '';
                break;
                
            case 'csv':
                $this->csv_file_path = $config['file_path'] ?? '';
                break;
                
            case 'excel':
                $this->excel_file_path = $config['file_path'] ?? '';
                break;
                
            case 'database':
                $this->db_table = $config['table'] ?? '';
                break;
                
            case 'google_sheets':
                $this->sheets_spreadsheet_id = $config['spreadsheet_id'] ?? '';
                $this->sheets_range = $config['range'] ?? 'A:Z';
                break;
        }
    }

    private function buildSourceConfig()
    {
        switch ($this->source_type) {
            case 'api':
                $this->source_config = [
                    'method' => 'GET',
                    'url' => $this->api_url,
                    'headers' => [],
                    'timeout' => 30
                ];
                break;
                
            case 'csv':
                $this->source_config = [
                    'file_path' => $this->csv_file_path,
                    'delimiter' => ',',
                    'has_header' => true
                ];
                break;
                
            case 'excel':
                $this->source_config = [
                    'file_path' => $this->excel_file_path,
                    'sheet_name' => '',
                    'has_header' => true
                ];
                break;
                
            case 'database':
                $this->source_config = [
                    'table' => $this->db_table,
                    'columns' => ['*'],
                    'connection' => 'mysql',
                    'limit' => 100
                ];
                break;
                
            case 'google_sheets':
                $this->source_config = [
                    'spreadsheet_id' => $this->sheets_spreadsheet_id,
                    'range' => $this->sheets_range,
                    'has_header' => true
                ];
                break;
        }
    }
}