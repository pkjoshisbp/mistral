<?php

namespace App\Livewire\Admin;

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
    public $selectedOrganization = '';
    public $showModal = false;
    public $editingAction = null;
    
    // Form fields
    public $organization_id = '';
    public $name = '';
    public $action_type = '';
    public $description = '';
    public $aliases = [];
    public $keywords = [];
    public $source_type = '';
    public $source_config = [];
    public $params_template = [];
    public $required_params = [];
    public $optional_params = [];
    public $min_score_threshold = 0.75;
    public $cache_ttl = 300;
    public $is_active = true;
    public $roles_allowed = [];
    public $response_template = '';
    public $output_format = 'text';
    
    // Helper fields
    public $aliasInput = '';
    public $keywordInput = '';
    public $requiredParamInput = '';
    public $optionalParamInput = '';
    
    // Source config fields
    public $api_method = 'GET';
    public $api_url = '';
    public $api_headers = [];
    public $api_timeout = 30;
    public $csv_file_path = '';
    public $csv_delimiter = ',';
    public $csv_has_header = true;
    public $excel_file_path = '';
    public $excel_sheet_name = '';
    public $db_table = '';
    public $db_columns = ['*'];
    public $db_connection = 'mysql';
    public $sheets_spreadsheet_id = '';
    public $sheets_range = 'A:Z';

    protected $rules = [
        'organization_id' => 'required|exists:organizations,id',
        'name' => 'required|string|max:255',
        'action_type' => 'required|string|max:255',
        'description' => 'required|string',
        'source_type' => 'required|in:api,csv,excel,google_sheets,database',
        'min_score_threshold' => 'required|numeric|between:0,1',
        'cache_ttl' => 'required|integer|min:0',
    ];

    public function mount()
    {
        $this->resetForm();
    }

    public function render()
    {
        $organizations = Organization::all();
        
        $query = OrganizationAction::with('organization')
            ->when($this->search, fn($q) => 
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('action_type', 'like', '%' . $this->search . '%')
                  ->orWhere('description', 'like', '%' . $this->search . '%')
            )
            ->when($this->selectedOrganization, fn($q) => 
                $q->where('organization_id', $this->selectedOrganization)
            )
            ->orderBy('organization_id')
            ->orderBy('name');

        $actions = $query->paginate(10);

        return view('livewire.admin.action-manager', compact('organizations', 'actions'))
            ->layout('layouts.admin');
    }

    public function openModal($actionId = null)
    {
        $this->resetForm();
        
        if ($actionId) {
            $this->editingAction = OrganizationAction::find($actionId);
            
            $this->organization_id = $this->editingAction->organization_id;
            $this->name = $this->editingAction->name;
            $this->action_type = $this->editingAction->action_type;
            $this->description = $this->editingAction->description;
            $this->aliases = $this->editingAction->aliases ?? [];
            $this->keywords = $this->editingAction->keywords ?? [];
            $this->source_type = $this->editingAction->source_type;
            $this->source_config = $this->editingAction->source_config ?? [];
            $this->params_template = $this->editingAction->params_template ?? [];
            $this->required_params = $this->editingAction->required_params ?? [];
            $this->optional_params = $this->editingAction->optional_params ?? [];
            $this->min_score_threshold = $this->editingAction->min_score_threshold;
            $this->cache_ttl = $this->editingAction->cache_ttl;
            $this->is_active = $this->editingAction->is_active;
            $this->roles_allowed = $this->editingAction->roles_allowed ?? [];
            $this->response_template = $this->editingAction->response_template ?? '';
            $this->output_format = $this->editingAction->output_format;
            
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
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'action_type' => $this->action_type,
            'description' => $this->description,
            'aliases' => array_filter($this->aliases),
            'keywords' => array_filter($this->keywords),
            'source_type' => $this->source_type,
            'source_config' => $this->source_config,
            'params_template' => $this->params_template,
            'required_params' => array_filter($this->required_params),
            'optional_params' => array_filter($this->optional_params),
            'min_score_threshold' => $this->min_score_threshold,
            'cache_ttl' => $this->cache_ttl,
            'is_active' => $this->is_active,
            'roles_allowed' => array_filter($this->roles_allowed),
            'response_template' => $this->response_template,
            'output_format' => $this->output_format,
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
        $action = OrganizationAction::find($actionId);
        
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
        $action = OrganizationAction::find($actionId);
        if ($action) {
            $action->update(['is_active' => !$action->is_active]);
            session()->flash('success', 'Action status updated!');
        }
    }

    // Helper methods for form management
    public function addAlias()
    {
        if ($this->aliasInput && !in_array($this->aliasInput, $this->aliases)) {
            $this->aliases[] = $this->aliasInput;
            $this->aliasInput = '';
        }
    }

    public function removeAlias($index)
    {
        unset($this->aliases[$index]);
        $this->aliases = array_values($this->aliases);
    }

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

    public function addRequiredParam()
    {
        if ($this->requiredParamInput && !in_array($this->requiredParamInput, $this->required_params)) {
            $this->required_params[] = $this->requiredParamInput;
            $this->requiredParamInput = '';
        }
    }

    public function removeRequiredParam($index)
    {
        unset($this->required_params[$index]);
        $this->required_params = array_values($this->required_params);
    }

    public function addOptionalParam()
    {
        if ($this->optionalParamInput && !in_array($this->optionalParamInput, $this->optional_params)) {
            $this->optional_params[] = $this->optionalParamInput;
            $this->optionalParamInput = '';
        }
    }

    public function removeOptionalParam($index)
    {
        unset($this->optional_params[$index]);
        $this->optional_params = array_values($this->optional_params);
    }

    public function testAction($actionId)
    {
        $action = OrganizationAction::find($actionId);
        
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

    private function resetForm()
    {
        $this->editingAction = null;
        $this->organization_id = '';
        $this->name = '';
        $this->action_type = '';
        $this->description = '';
        $this->aliases = [];
        $this->keywords = [];
        $this->source_type = '';
        $this->source_config = [];
        $this->params_template = [];
        $this->required_params = [];
        $this->optional_params = [];
        $this->min_score_threshold = 0.75;
        $this->cache_ttl = 300;
        $this->is_active = true;
        $this->roles_allowed = [];
        $this->response_template = '';
        $this->output_format = 'text';
        
        $this->aliasInput = '';
        $this->keywordInput = '';
        $this->requiredParamInput = '';
        $this->optionalParamInput = '';
        
        $this->resetSourceConfigFields();
    }

    private function resetSourceConfigFields()
    {
        $this->api_method = 'GET';
        $this->api_url = '';
        $this->api_headers = [];
        $this->api_timeout = 30;
        $this->csv_file_path = '';
        $this->csv_delimiter = ',';
        $this->csv_has_header = true;
        $this->excel_file_path = '';
        $this->excel_sheet_name = '';
        $this->db_table = '';
        $this->db_columns = ['*'];
        $this->db_connection = 'mysql';
        $this->sheets_spreadsheet_id = '';
        $this->sheets_range = 'A:Z';
    }

    private function loadSourceConfigFields()
    {
        $config = $this->source_config;
        
        switch ($this->source_type) {
            case 'api':
                $this->api_method = $config['method'] ?? 'GET';
                $this->api_url = $config['url'] ?? '';
                $this->api_headers = $config['headers'] ?? [];
                $this->api_timeout = $config['timeout'] ?? 30;
                break;
                
            case 'csv':
                $this->csv_file_path = $config['file_path'] ?? '';
                $this->csv_delimiter = $config['delimiter'] ?? ',';
                $this->csv_has_header = $config['has_header'] ?? true;
                break;
                
            case 'excel':
                $this->excel_file_path = $config['file_path'] ?? '';
                $this->excel_sheet_name = $config['sheet_name'] ?? '';
                break;
                
            case 'database':
                $this->db_table = $config['table'] ?? '';
                $this->db_columns = $config['columns'] ?? ['*'];
                $this->db_connection = $config['connection'] ?? 'mysql';
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
                    'method' => $this->api_method,
                    'url' => $this->api_url,
                    'headers' => $this->api_headers,
                    'timeout' => $this->api_timeout
                ];
                break;
                
            case 'csv':
                $this->source_config = [
                    'file_path' => $this->csv_file_path,
                    'delimiter' => $this->csv_delimiter,
                    'has_header' => $this->csv_has_header
                ];
                break;
                
            case 'excel':
                $this->source_config = [
                    'file_path' => $this->excel_file_path,
                    'sheet_name' => $this->excel_sheet_name,
                    'has_header' => true
                ];
                break;
                
            case 'database':
                $this->source_config = [
                    'table' => $this->db_table,
                    'columns' => is_array($this->db_columns) ? $this->db_columns : [$this->db_columns],
                    'connection' => $this->db_connection,
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