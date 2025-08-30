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
    public $showEditForm = false;
    public $editingOrgId = null;
    public $name = '';
    public $slug = '';
    public $description = '';
    public $website_url = '';

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:organizations,slug',
        'description' => 'nullable',
        'website_url' => 'nullable|url'
    ];

    public function mount()
    {
        $this->loadOrganizations();
    }

    public function loadOrganizations()
    {
        $this->organizations = Organization::with('users')->get();
    }

    public function createOrganization()
    {
        $this->validate();

        $org = Organization::create([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $this->website_url,
            'api_key' => \Str::random(32),
            'settings' => [
                'sync_enabled' => true,
            ]
        ]);

        // Create Qdrant collection for this organization
        try {
            $aiService = new AiAgentService();
            $collectionName = str_replace('-', '_', $this->slug);
            $aiService->createCollection($collectionName);
        } catch (\Exception $e) {
            \Log::error('Failed to create Qdrant collection: ' . $e->getMessage());
        }

        $this->reset(['name', 'slug', 'description', 'website_url']);
        $this->showCreateForm = false;
        $this->loadOrganizations();
        
        session()->flash('message', 'Organization created successfully!');
    }

    public function editOrganization($id)
    {
        $org = Organization::find($id);
        if (!$org) return;

        $this->editingOrgId = $id;
        $this->name = $org->name;
        $this->slug = $org->slug;
        $this->description = $org->description;
        $this->website_url = $org->website_url;
        $this->showEditForm = true;
    }

    public function updateOrganization()
    {
        $this->validate([
            'name' => 'required|min:3',
            'slug' => 'required|unique:organizations,slug,' . $this->editingOrgId,
            'description' => 'nullable',
            'website_url' => 'nullable|url'
        ]);

        $org = Organization::find($this->editingOrgId);
        $oldSlug = $org->slug;
        
        $org->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website_url' => $this->website_url,
        ]);

        // If slug changed, update Qdrant collection
        if ($oldSlug !== $this->slug) {
            try {
                $aiService = new AiAgentService();
                $oldCollectionName = str_replace('-', '_', $oldSlug);
                $newCollectionName = str_replace('-', '_', $this->slug);
                
                // Create new collection
                $aiService->createCollection($newCollectionName);
                
                // Copy data from old to new collection (if needed)
                // This is a basic implementation - you might want to improve this
                
                // Delete old collection
                $aiService->deleteCollection($oldCollectionName);
            } catch (\Exception $e) {
                \Log::error('Failed to update Qdrant collection: ' . $e->getMessage());
            }
        }

        $this->reset(['name', 'slug', 'description', 'website_url', 'editingOrgId']);
        $this->showEditForm = false;
        $this->loadOrganizations();
        
        session()->flash('message', 'Organization updated successfully!');
    }

    public function selectOrganization($id)
    {
        $this->selectedOrg = Organization::find($id);
    }

    public function toggleCreateForm()
    {
        $this->showCreateForm = !$this->showCreateForm;
        $this->showEditForm = false;
        if (!$this->showCreateForm) {
            $this->reset(['name', 'slug', 'description', 'website_url']);
        }
    }

    public function cancelEdit()
    {
        $this->showEditForm = false;
        $this->reset(['name', 'slug', 'description', 'website_url', 'editingOrgId']);
    }

    public function render()
    {
        return view('livewire.organization-manager');
    }
}
