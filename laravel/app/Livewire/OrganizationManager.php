<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Organization;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class OrganizationManager extends Component
{
    public $organizations;
    public $selectedOrg;
    public $showCreateForm = false;
    public $showEditForm = false;
    public $editingOrgId = null;
    public $search = '';
    public $filterStatus = '';

    public $name = '';
    public $slug = '';
    public $description = '';
    public $website_url = '';
    // New unified contact fields used across the app
    public $website = '';
    public $contact_email = '';
    public $contact_phone = '';
    public $supplementary_instruction = '';
    public $widget_skip_intent_on_affirmative_follow_up = true;
    public $widget_skip_exact_match_on_affirmative_follow_up = true;
    public $widget_affirmative_follow_up_max_tokens = 140;

    protected $rules = [
        'name' => 'required|min:3',
        'slug' => 'required|unique:organizations,slug',
        'description' => 'nullable',
        'website_url' => 'nullable|url',
        // Prefer unified website field; keep website_url for backward UI binding
        'website' => 'nullable|url',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string|max:50',
        'supplementary_instruction' => 'nullable|string|max:2000',
        'widget_skip_intent_on_affirmative_follow_up' => 'boolean',
        'widget_skip_exact_match_on_affirmative_follow_up' => 'boolean',
        'widget_affirmative_follow_up_max_tokens' => 'required|integer|min:80|max:300'
    ];

    public function mount()
    {
        $this->loadOrganizations();
    }

    public function updatingSearch() { $this->loadOrganizations(); }
    public function updatingFilterStatus() { $this->loadOrganizations(); }

    public function loadOrganizations()
    {
        $query = Organization::with('users')->orderByDesc('id');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                  ->orWhere('slug', 'like', '%' . $this->search . '%')
                  ->orWhere('contact_email', 'like', '%' . $this->search . '%');
            });
        }

        if ($this->filterStatus === 'active') {
            $query->where('is_active', true);
        } elseif ($this->filterStatus === 'inactive') {
            $query->where('is_active', false);
        }

        $this->organizations = $query->get();
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
                'supplementary_instruction' => trim((string) $this->supplementary_instruction),
                'widget_rule_policy' => [
                    'skip_intent_on_affirmative_follow_up' => (bool) $this->widget_skip_intent_on_affirmative_follow_up,
                    'skip_exact_match_on_affirmative_follow_up' => (bool) $this->widget_skip_exact_match_on_affirmative_follow_up,
                    'affirmative_follow_up_max_tokens' => (int) $this->widget_affirmative_follow_up_max_tokens,
                ],
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

    $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'supplementary_instruction', 'widget_skip_intent_on_affirmative_follow_up', 'widget_skip_exact_match_on_affirmative_follow_up', 'widget_affirmative_follow_up_max_tokens']);
        $this->widget_skip_intent_on_affirmative_follow_up = true;
        $this->widget_skip_exact_match_on_affirmative_follow_up = true;
        $this->widget_affirmative_follow_up_max_tokens = 140;
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
        $this->supplementary_instruction = (string) data_get($org->settings, 'supplementary_instruction', '');
        $this->widget_skip_intent_on_affirmative_follow_up = (bool) data_get($org->settings, 'widget_rule_policy.skip_intent_on_affirmative_follow_up', true);
        $this->widget_skip_exact_match_on_affirmative_follow_up = (bool) data_get($org->settings, 'widget_rule_policy.skip_exact_match_on_affirmative_follow_up', true);
        $this->widget_affirmative_follow_up_max_tokens = (int) data_get($org->settings, 'widget_rule_policy.affirmative_follow_up_max_tokens', 140);
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
            'contact_phone' => 'nullable|string|max:50',
            'supplementary_instruction' => 'nullable|string|max:2000',
            'widget_skip_intent_on_affirmative_follow_up' => 'boolean',
            'widget_skip_exact_match_on_affirmative_follow_up' => 'boolean',
            'widget_affirmative_follow_up_max_tokens' => 'required|integer|min:80|max:300'
        ]);

        $org = Organization::find($this->editingOrgId);
        $oldSlug = $org->slug;
        $settings = is_array($org->settings) ? $org->settings : [];
        $settings['supplementary_instruction'] = trim((string) $this->supplementary_instruction);
        $settings['widget_rule_policy'] = [
            'skip_intent_on_affirmative_follow_up' => (bool) $this->widget_skip_intent_on_affirmative_follow_up,
            'skip_exact_match_on_affirmative_follow_up' => (bool) $this->widget_skip_exact_match_on_affirmative_follow_up,
            'affirmative_follow_up_max_tokens' => max(80, min(300, (int) $this->widget_affirmative_follow_up_max_tokens)),
        ];
        
        $org->update([
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            // Write canonical fields; keep legacy website_url untouched
            'website' => $this->website ?: $this->website_url,
            'contact_email' => $this->contact_email ?: null,
            'contact_phone' => $this->contact_phone ?: null,
            'settings' => $settings,
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

    $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'supplementary_instruction', 'widget_skip_intent_on_affirmative_follow_up', 'widget_skip_exact_match_on_affirmative_follow_up', 'widget_affirmative_follow_up_max_tokens', 'editingOrgId']);
        $this->widget_skip_intent_on_affirmative_follow_up = true;
        $this->widget_skip_exact_match_on_affirmative_follow_up = true;
        $this->widget_affirmative_follow_up_max_tokens = 140;
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
            $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'supplementary_instruction', 'widget_skip_intent_on_affirmative_follow_up', 'widget_skip_exact_match_on_affirmative_follow_up', 'widget_affirmative_follow_up_max_tokens']);
            $this->widget_skip_intent_on_affirmative_follow_up = true;
            $this->widget_skip_exact_match_on_affirmative_follow_up = true;
            $this->widget_affirmative_follow_up_max_tokens = 140;
        }
    }

    public function cancelEdit()
    {
        $this->showEditForm = false;
        $this->reset(['name', 'slug', 'description', 'website_url', 'website', 'contact_email', 'contact_phone', 'supplementary_instruction', 'widget_skip_intent_on_affirmative_follow_up', 'widget_skip_exact_match_on_affirmative_follow_up', 'widget_affirmative_follow_up_max_tokens', 'editingOrgId']);
        $this->widget_skip_intent_on_affirmative_follow_up = true;
        $this->widget_skip_exact_match_on_affirmative_follow_up = true;
        $this->widget_affirmative_follow_up_max_tokens = 140;
    }

    public function deleteOrganization($id)
    {
        $org = Organization::find($id);
        if (!$org) {
            session()->flash('error', 'Organization not found.');
            return;
        }

        try {
            // Delete Qdrant collection
            $aiService = new AiAgentService();
            $collectionName = str_replace('-', '_', $org->slug);
            $aiService->deleteCollection($collectionName);
            
            Log::info('Deleted Qdrant collection for organization', [
                'org_id' => $id,
                'collection' => $collectionName
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete Qdrant collection: ' . $e->getMessage(), [
                'org_id' => $id,
                'slug' => $org->slug
            ]);
        }

        // Find and handle users
        $users = $org->users;
        foreach ($users as $user) {
            // Only delete user if they belong to just this organization
            if ($user->organizations()->count() === 1) {
                Log::info('Deleting user with organization', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'org_id' => $id
                ]);
                $user->delete();
            } else {
                // Just detach from this organization
                $org->users()->detach($user->id);
                Log::info('Detached user from organization (user has other orgs)', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'org_id' => $id
                ]);
            }
        }

        // Delete related data
        $org->organizationData()->delete();
        
        // Delete chat sessions and their messages
        foreach ($org->chatSessions as $session) {
            $session->messages()->delete();
        }
        $org->chatSessions()->delete();
        
        // Delete chat conversations and their messages
        foreach ($org->chatConversations as $conversation) {
            $conversation->messages()->delete();
        }
        $org->chatConversations()->delete();
        
        $org->tokenUsageLogs()->delete();
        $org->integrations()->delete();
        
        // Delete the organization
        $org->delete();
        
        $this->loadOrganizations();
        session()->flash('message', 'Organization deleted successfully!');
    }

    public function render()
    {
        return view('livewire.organization-manager');
    }
}
