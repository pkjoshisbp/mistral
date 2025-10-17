<?php

namespace App\Livewire\Shopify;

use Livewire\Component;
use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

class Preferences extends Component
{
    public $shop;
    public $organization;
    public $integration;
    
    // Widget settings
    public $widget_enabled = true;
    public $widget_position = 'bottom-right';
    public $primary_color = '#007bff';
    public $welcome_message = 'Hello! How can I help you today?';
    public $widget_offset_x = 20;
    public $widget_offset_y = 20;
    
    public $successMessage = '';
    public $errorMessage = '';

    public function mount()
    {
        // Get shop from query parameter (Shopify sends it)
        $this->shop = request()->query('shop');
        
        if (!$this->shop) {
            $this->errorMessage = 'Shop parameter missing. Please access this page from your Shopify admin.';
            return;
        }

        Log::info('Shopify Preferences page accessed', ['shop' => $this->shop]);

        // Find integration
        $this->integration = Integration::where('provider', 'shopify')
            ->where('shop', $this->shop)
            ->first();

        if (!$this->integration) {
            $this->errorMessage = 'Integration not found. Please reinstall the app.';
            return;
        }

        $this->organization = $this->integration->organization;

        if (!$this->organization) {
            $this->errorMessage = 'Organization not found. Please contact support.';
            return;
        }

        // Load current settings
        $settings = $this->organization->widget_settings ?? [];
        
        $this->widget_enabled = $settings['widget_enabled'] ?? true;
        $this->widget_position = $settings['widget_position'] ?? 'bottom-right';
        $this->primary_color = $settings['primary_color'] ?? '#007bff';
        $this->welcome_message = $settings['welcome_message'] ?? 'Hello! How can I help you today?';
        $this->widget_offset_x = $settings['widget_offset_x'] ?? 20;
        $this->widget_offset_y = $settings['widget_offset_y'] ?? 20;

        Log::info('Shopify Preferences loaded', [
            'shop' => $this->shop,
            'org_id' => $this->organization->id,
            'settings' => $settings
        ]);
    }

    public function savePreferences()
    {
        $this->validate([
            'widget_position' => 'required|in:bottom-right,bottom-left,top-right,top-left',
            'primary_color' => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'welcome_message' => 'required|max:200',
            'widget_offset_x' => 'required|integer|min:0|max:200',
            'widget_offset_y' => 'required|integer|min:0|max:200',
        ]);

        $settings = [
            'widget_enabled' => $this->widget_enabled,
            'widget_position' => $this->widget_position,
            'primary_color' => $this->primary_color,
            'welcome_message' => $this->welcome_message,
            'widget_offset_x' => $this->widget_offset_x,
            'widget_offset_y' => $this->widget_offset_y,
        ];

        $this->organization->update([
            'widget_settings' => $settings
        ]);

        Log::info('Shopify Preferences saved', [
            'shop' => $this->shop,
            'org_id' => $this->organization->id,
            'settings' => $settings
        ]);

        $this->successMessage = 'Settings saved successfully!';
        $this->errorMessage = '';

        // Clear success message after 3 seconds
        $this->dispatch('preferences-saved');
    }

    public function render()
    {
        return view('livewire.shopify.preferences')
            ->layout('layouts.shopify-app');
    }
}
