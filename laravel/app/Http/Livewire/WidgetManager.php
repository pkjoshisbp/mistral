<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Organization;

class WidgetManager extends Component
{
    public $organizations;
    public $selectedOrgId;
    public $settings = [];
    public $embedCode = '';

    protected $rules = [
        'settings.welcome_message' => 'required|string|max:255',
        'settings.primary_color' => 'required|string',
        'settings.widget_position' => 'required|string',
        'settings.widget_theme' => 'required|string'
    ];

    public function mount()
    {
        $this->organizations = Organization::all();
        $this->resetSettings();
    }

    public function resetSettings()
    {
        $this->settings = [
            'welcome_message' => 'Hello! How can I help you today?',
            'primary_color' => '#007bff',
            'secondary_color' => '#f8f9fa',
            'text_color' => '#333333',
            'border_radius' => '10px',
            'widget_position' => 'bottom-right',
            'widget_theme' => 'default'
        ];
    }

    public function selectOrganization($orgId)
    {
        $this->selectedOrgId = $orgId;
        $organization = Organization::find($orgId);
        
        if ($organization && $organization->settings) {
            $this->settings = array_merge($this->settings, $organization->settings);
        }
        
        $this->generateEmbedCode();
    }

    public function saveSettings()
    {
        $this->validate();

        if (!$this->selectedOrgId) {
            session()->flash('error', 'Please select an organization first.');
            return;
        }

        $organization = Organization::find($this->selectedOrgId);
        $organization->settings = array_merge($organization->settings ?? [], $this->settings);
        $organization->save();

        $this->generateEmbedCode();
        session()->flash('message', 'Widget settings saved successfully!');
    }

    public function generateEmbedCode()
    {
        if (!$this->selectedOrgId) {
            $this->embedCode = '';
            return;
        }

        $baseUrl = config('app.url');
        $this->embedCode = "<!-- AI Chat Widget for Organization ID: {$this->selectedOrgId} -->
<script>
    (function() {
        var script = document.createElement('script');
        script.src = '{$baseUrl}/widget/{$this->selectedOrgId}/script.js';
        script.async = true;
        document.head.appendChild(script);
    })();
</script>";
    }

    public function render()
    {
        return view('livewire.widget-manager');
    }
}
