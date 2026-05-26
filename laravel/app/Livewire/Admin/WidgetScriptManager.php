<?php

namespace App\Livewire\Admin;

use App\Models\Organization;
use Livewire\Component;

class WidgetScriptManager extends Component
{
    public $selectedOrganization = '';
    public $widgetSettings = [
        'position' => 'bottom-right',
        'theme' => 'default',
        'primaryColor' => '#007bff',
        'greeting' => 'Hello! How can I help you today?',
        'placeholder' => 'Type your message here...',
        'title' => 'AI Chat Support',
        'subtitle' => 'We\'re here to help',
        'showOnPages' => 'all',
        'excludePages' => '',
        'analytics' => true,
        'collectEmail' => false,
        'offline_message' => 'We\'ll get back to you soon!',
        'welcome_delay' => 3000,
        'auto_open' => false,
    ];

    public $generatedScript = '';
    public $showScriptModal = false;

    protected $queryString = ['selectedOrganization'];

    public function mount()
    {
        if ($this->selectedOrganization) {
            $this->generateScript();
        }
    }

    public function updatedSelectedOrganization()
    {
        $this->generateScript();
    }

    public function generateScript()
    {
        if (!$this->selectedOrganization) {
            $this->generatedScript = '';
            return;
        }

        $organization = Organization::find($this->selectedOrganization);
        if (!$organization) {
            return;
        }

        $baseUrl = rtrim(config('app.url'), '/');
        $embedTarget = $organization->slug ?: (string) $organization->id;

        $this->generatedScript = <<<JS
<!-- AI Chat Support Widget -->
<!-- Organization: {$organization->name} -->
<script>
(function() {
    var script = document.createElement('script');
    script.src = '{$baseUrl}/widget/{$embedTarget}/script.js';
    script.async = true;
    document.head.appendChild(script);
})();
</script>
JS;
    }

    public function showScript()
    {
        $this->generateScript();
        $this->showScriptModal = true;
    }

    public function closeScriptModal()
    {
        $this->showScriptModal = false;
    }

    public function updateSettings()
    {
        $this->generateScript();
        session()->flash('success', 'Widget settings updated successfully!');
    }

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function render()
    {
        return view('livewire.admin.widget-script-manager')
            ->layout('layouts.admin');
    }
}
