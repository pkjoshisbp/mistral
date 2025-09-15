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

        $config = json_encode($this->widgetSettings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $analyticsEnabled = isset($this->widgetSettings['analytics']) && $this->widgetSettings['analytics'] ? 'true' : 'false';
        
        $this->generatedScript = <<<JS
<!-- AI Chat Support Widget Script -->
<!-- Organization: {$organization->name} -->
<script>
(function() {
    // Widget Configuration
    window.aiChatConfig = {$config};
    
    // Organization ID (required for analytics and chat routing)
    window.aiChatConfig.organizationId = '{$organization->id}';
    window.aiChatConfig.organizationName = '{$organization->name}';
    
    // Analytics Configuration
    window.aiChatAnalytics = {
        enabled: {$analyticsEnabled},
        trackingUrl: 'https://ai-chat.support/api/analytics/track',
        organizationId: '{$organization->id}'
    };

    // Load Widget Script
    var script = document.createElement('script');
    script.src = 'https://ai-chat.support/widget/ai-chat-widget.js?v=' + Date.now();
    script.async = true;
    script.onload = function() {
        if (typeof AIChatWidget !== 'undefined') {
            AIChatWidget.init(window.aiChatConfig);
        }
    };
    document.head.appendChild(script);

    // Load Widget Styles
    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://ai-chat.support/widget/ai-chat-widget.css?v=' + Date.now();
    document.head.appendChild(link);

    // Analytics Tracking (if enabled)
    if (window.aiChatAnalytics.enabled) {
        // Track page view
        fetch(window.aiChatAnalytics.trackingUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                organization_id: window.aiChatAnalytics.organizationId,
                event_type: 'page_view',
                page_url: window.location.href,
                page_title: document.title,
                referrer: document.referrer,
                user_agent: navigator.userAgent,
                timestamp: new Date().toISOString()
            })
        }).catch(function(error) {
            console.log('Analytics tracking error:', error);
        });

        // Track widget interactions
        document.addEventListener('aiChatWidget:opened', function() {
            fetch(window.aiChatAnalytics.trackingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    organization_id: window.aiChatAnalytics.organizationId,
                    event_type: 'widget_open',
                    page_url: window.location.href,
                    timestamp: new Date().toISOString()
                })
            }).catch(function(error) {
                console.log('Analytics tracking error:', error);
            });
        });

        document.addEventListener('aiChatWidget:messageSent', function(event) {
            fetch(window.aiChatAnalytics.trackingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    organization_id: window.aiChatAnalytics.organizationId,
                    event_type: 'chat_message',
                    page_url: window.location.href,
                    event_data: {
                        message_length: event.detail.message ? event.detail.message.length : 0
                    },
                    timestamp: new Date().toISOString()
                })
            }).catch(function(error) {
                console.log('Analytics tracking error:', error);
            });
        });
    }
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
