<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use App\Models\Integration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class IntegrationSettingsManager extends Component
{
    public $organization;
    public $integration;
    
    // Organization settings
    public $name;
    public $description;
    public $website;
    public $contact_email;
    public $contact_phone;
    
    // Widget settings
    public $widget_position = 'bottom-right';
    public $primary_color = '#007bff';
    public $welcome_message = 'Hello! How can I help you today?';
    public $widget_offset_x = 20;
    public $widget_offset_y = 20;

    protected $rules = [
        'name' => 'required|min:3',
        'description' => 'nullable|string',
        'website' => 'nullable|url',
        'contact_email' => 'nullable|email',
        'contact_phone' => 'nullable|string|max:50',
        'widget_position' => 'required|in:bottom-right,bottom-left,top-right,top-left',
        'primary_color' => 'required|string',
        'welcome_message' => 'required|string|max:255',
        'widget_offset_x' => 'required|integer|min:0|max:200',
        'widget_offset_y' => 'required|integer|min:0|max:200',
    ];

    public function mount()
    {
        $user = Auth::user();
        $this->organization = $user->organizations->first();
        
        if (!$this->organization) {
            session()->flash('error', 'No organization found for your account.');
            return redirect()->route('customer.dashboard');
        }

        // Load integration
        $this->integration = Integration::where('organization_id', $this->organization->id)
            ->whereIn('provider', ['shopify', 'wordpress', 'woocommerce'])
            ->first();

        // Load organization data
        $this->name = $this->organization->name;
        $this->description = $this->organization->description ?? '';
        $this->website = $this->organization->website ?? '';
        $this->contact_email = $this->organization->contact_email ?? '';
        $this->contact_phone = $this->organization->contact_phone ?? '';

        // Load widget settings from organization settings
        $settings = $this->organization->settings ?? [];
        $this->widget_position = $settings['widget_position'] ?? 'bottom-right';
        $this->primary_color = $settings['primary_color'] ?? '#007bff';
        $this->welcome_message = $settings['welcome_message'] ?? 'Hello! How can I help you today?';
        $this->widget_offset_x = $settings['widget_offset_x'] ?? 20;
        $this->widget_offset_y = $settings['widget_offset_y'] ?? 20;
    }

    public function saveSettings()
    {
        $this->validate();

        try {
            // Update organization basic info
            $this->organization->update([
                'name' => $this->name,
                'description' => $this->description,
                'website' => $this->website,
                'contact_email' => $this->contact_email,
                'contact_phone' => $this->contact_phone,
            ]);

            // Update widget settings in organization settings
            $settings = $this->organization->settings ?? [];
            $settings['widget_position'] = $this->widget_position;
            $settings['primary_color'] = $this->primary_color;
            $settings['welcome_message'] = $this->welcome_message;
            $settings['widget_offset_x'] = $this->widget_offset_x;
            $settings['widget_offset_y'] = $this->widget_offset_y;
            
            $this->organization->settings = $settings;
            $this->organization->save();

            Log::info('Integration settings updated', [
                'org_id' => $this->organization->id,
                'user_id' => Auth::id(),
                'provider' => $this->integration?->provider
            ]);

            session()->flash('message', 'Settings updated successfully!');
        } catch (\Exception $e) {
            Log::error('Failed to update integration settings', [
                'error' => $e->getMessage(),
                'org_id' => $this->organization->id
            ]);
            
            session()->flash('error', 'Failed to update settings. Please try again.');
        }
    }

    public function render()
    {
        return view('livewire.customer.integration-settings-manager')
            ->layout('layouts.customer');
    }
}
