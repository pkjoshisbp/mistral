<?php

namespace App\Http\Livewire;

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

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:organizations,slug',
        'description' => 'nullable',
        'database_name' => 'nullable'
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

        $org = Organization::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'database_name' => $this->database_name,
            'api_key' => \Str::random(32)
        ]);

        // Create Qdrant collection for this organization
        $aiService = new AiAgentService();
        $aiService->createCollection("org_{$org->id}");

        $this->reset(['name', 'slug', 'description', 'database_name']);
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
