<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;

class OrganizationManager extends Component
{
    public $organizations;
    public $selectedOrg;
    public $showCreateForm = false;
    public $name = '';
    public $slug = '';
    public $description = '';
    public $database_name = '';
    public $database_host = 'localhost';
    public $database_username = '';
    public $database_password = '';
    public $database_port = '3306';

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:organizations,slug',
        'description' => 'nullable',
        'database_name' => 'nullable',
        'database_host' => 'nullable',
        'database_username' => 'nullable', 
        'database_password' => 'nullable',
        'database_port' => 'nullable'
    ];

    public function mount()
    {
        $this->loadOrganizations();
    }

    public function loadOrganizations()
    {
        $this->organizations = Organization::all();
    }

    public function createOrganization()
    {
        $this->validate();

        // Generate unique collection name from organization name
        $collectionName = Organization::generateUniqueCollectionName($this->name);

        $org = Organization::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'database_name' => $this->database_name,
            'api_key' => \Str::random(32),
            'collection_name' => $collectionName,
            'settings' => [
                'database' => [
                    'host' => $this->database_host,
                    'username' => $this->database_username,
                    'password' => $this->database_password ? encrypt($this->database_password) : null,
                    'port' => $this->database_port,
                ],
                'sync_enabled' => !empty($this->database_name),
            ]
        ]);

        // Create Qdrant collection with the generated name
        $aiService = new AiAgentService();
        $aiService->createCollection($collectionName);

        $this->reset(['name', 'slug', 'description', 'database_name', 'database_host', 'database_username', 'database_password', 'database_port']);
        $this->showCreateForm = false;
        $this->loadOrganizations();
        
        session()->flash('message', 'Organization created successfully!');
    }

    public function selectOrganization($id)
    {
        $this->selectedOrg = Organization::find($id);
    }

    public function toggleCreateForm()
    {
        $this->showCreateForm = !$this->showCreateForm;
        if (!$this->showCreateForm) {
            $this->reset(['name', 'slug', 'description', 'database_name']);
        }
    }

    public function render()
    {
        return view('livewire.organization-manager');
    }
}
