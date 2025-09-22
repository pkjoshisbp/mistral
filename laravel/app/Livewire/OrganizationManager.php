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
    // New unified contact fields used across the app
    public $website = '';
    public $contact_email = '';
    public $contact_phone = '';

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:organizations,slug',
        'description' => 'nullable',
        'website_url' => 'nullable|url',
        // Prefer unified website field; keep website_url for backward UI binding
        'website' => 'nullable|url',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string|max:50'
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
            // Write to canonical columns
            'website' => $this->website ?: $this->website_url,
            'contact_email' => $this->contact_email ?: null,
            'contact_phone' => $this->contact_phone ?: null,
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

    $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone']);
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
        // Backward compatibility: load both
        $this->website_url = $org->website_url ?? '';
        $this->website = $org->website ?? ($org->website_url ?? '');
        $this->contact_email = $org->contact_email ?? '';
        $this->contact_phone = $org->contact_phone ?? '';
        $this->showEditForm = true;
    }

    public function updateOrganization()
    {
        $this->validate([
            'name' => 'required|min:3',
            'slug' => 'required|unique:organizations,slug,' . $this->editingOrgId,
            'description' => 'nullable',
            'website_url' => 'nullable|url',
            'website' => 'nullable|url',
            'contact_email' => 'nullable|email',
            'contact_phone' => 'nullable|string|max:50'
        ]);

        $org = Organization::find($this->editingOrgId);
        $oldSlug = $org->slug;
        
        $org->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // Write canonical fields; keep legacy website_url untouched
            'website' => $this->website ?: $this->website_url,
            'contact_email' => $this->contact_email ?: null,
            'contact_phone' => $this->contact_phone ?: null,
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

    $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'editingOrgId']);
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
            $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone']);
        }
    }

    public function cancelEdit()
    {
        $this->showEditForm = false;
        $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'editingOrgId']);
    }

    public function render()
    {
        return view('livewire.organization-manager');
    }
}
