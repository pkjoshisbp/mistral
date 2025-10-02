<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Organization;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Log;

class OrganizationAiManager extends Component
{
    public $organizations;
    public $selectedOrganization;
    public $aiBackendType;
    public $aiModelProvider;
    public $aiModel;
    public $availableModels = [];
    public $showSettings = false;
    public $assistantDisplayName;

    protected $rules = [
        'aiBackendType' => 'required|in:ollama,llamacpp',
        'aiModelProvider' => 'required|in:llama,openai',
        'aiModel' => 'required|string',
        'assistantDisplayName' => 'nullable|string|max:60'
    ];

    public function mount()
    {
        $this->organizations = Organization::orderBy('name')->get();
        $this->loadAvailableModels();
    }

    public function selectOrganization($orgId)
    {
        $this->selectedOrganization = Organization::find($orgId);
        $this->loadOrganizationAiSettings();
        $this->showSettings = true;
    }

    public function loadOrganizationAiSettings()
    {
        if (!$this->selectedOrganization) return;

        $settings = $this->selectedOrganization->settings ?? [];
        
        // Load organization-specific settings or fallback to global admin settings
        $this->aiBackendType = $settings['ai_backend_type'] ?? AdminSetting::get('ai_backend_type', 'ollama');
        $this->aiModelProvider = $settings['ai_model_provider'] ?? AdminSetting::get('ai_model_provider', 'llama');
        $this->aiModel = $settings['ai_model'] ?? AdminSetting::get('ai_model', 'llama3.2:3b');
        $this->assistantDisplayName = $settings['assistant_display_name'] ?? 'AI Assistant';
    }

    public function loadAvailableModels()
    {
        // Define available models based on provider type
        $this->availableModels = [
            'ollama' => [
                'llama' => [
                    'llama3.2:3b' => 'Llama 3.2 3B (Fast)',
                    'llama3.2:1b' => 'Llama 3.2 1B (Fastest)', 
                    'llama3.1:8b' => 'Llama 3.1 8B (Balanced)',
                    'llama3.1:70b' => 'Llama 3.1 70B (Most Capable)',
                    'mistral:7b' => 'Mistral 7B',
                    'codellama:7b' => 'Code Llama 7B'
                ]
            ],
            'llamacpp' => [
                'llama' => [
                    'llama-3.2-3b-instruct-q4_k_m.gguf' => 'Llama 3.2 3B (Q4_K_M)',
                    'llama-3.2-1b-instruct-q4_k_m.gguf' => 'Llama 3.2 1B (Q4_K_M)',
                    'llama-3.1-8b-instruct-q4_k_m.gguf' => 'Llama 3.1 8B (Q4_K_M)'
                ]
            ]
        ];
    }

    public function saveAiSettings()
    {
        $this->validate();

        if (!$this->selectedOrganization) {
            session()->flash('error', 'No organization selected.');
            return;
        }

        try {
            $currentSettings = $this->selectedOrganization->settings ?? [];
            
            // Update AI model settings
            $currentSettings['ai_backend_type'] = $this->aiBackendType;
            $currentSettings['ai_model_provider'] = $this->aiModelProvider;
            $currentSettings['ai_model'] = $this->aiModel;
            // Assistant branding
            if ($this->assistantDisplayName !== null) {
                $trimmed = trim($this->assistantDisplayName);
                $currentSettings['assistant_display_name'] = $trimmed !== '' ? $trimmed : 'AI Assistant';
            }
            
            $this->selectedOrganization->settings = $currentSettings;
            $this->selectedOrganization->save();

            Log::info('AI model settings updated for organization', [
                'org_id' => $this->selectedOrganization->id,
                'org_name' => $this->selectedOrganization->name,
                'ai_backend_type' => $this->aiBackendType,
                'ai_model_provider' => $this->aiModelProvider,
                'ai_model' => $this->aiModel
            ]);

            session()->flash('success', 'AI model settings updated successfully for ' . $this->selectedOrganization->name);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to update settings: ' . $e->getMessage());
            Log::error('Failed to update organization AI settings', [
                'org_id' => $this->selectedOrganization->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function resetToGlobal()
    {
        if (!$this->selectedOrganization) return;

        try {
            $currentSettings = $this->selectedOrganization->settings ?? [];
            
            // Remove AI model settings to use global defaults
            unset($currentSettings['ai_backend_type']);
            unset($currentSettings['ai_model_provider']);
            unset($currentSettings['ai_model']);
            
            $this->selectedOrganization->settings = $currentSettings;
            $this->selectedOrganization->save();

            // Reload settings to show global defaults
            $this->loadOrganizationAiSettings();

            session()->flash('success', 'Reset to global AI settings for ' . $this->selectedOrganization->name);
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to reset settings: ' . $e->getMessage());
        }
    }

    public function getModelsForCurrentProvider()
    {
        return $this->availableModels[$this->aiBackendType][$this->aiModelProvider] ?? [];
    }

    public function render()
    {
        // Render within the admin layout (supports $slot)
        return view('livewire.admin.organization-ai-manager')->layout('layouts.admin');
    }
}
        // Use the admin layout explicitly to avoid missing default Livewire layout errors
        return view('livewire.admin.organization-ai-manager')->layout('layouts.admin');