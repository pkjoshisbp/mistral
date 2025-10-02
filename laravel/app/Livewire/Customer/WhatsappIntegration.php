<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\Organization;
use App\Models\AdminSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsappIntegration extends Component
{
    public $accessToken;
    public $phoneNumberId;
    public $verifyToken;
    public $webhookUrl;
    public $isConnected = false;
    public $connectionStatus;

    protected $rules = [
        'accessToken' => 'required|string',
        'phoneNumberId' => 'required|string',
    ];

    public function mount()
    {
        $org = Auth::user()->primaryOrganization();
        if ($org && $org->settings) {
            $this->accessToken = $org->settings['whatsapp_access_token'] ?? '';
            $this->phoneNumberId = $org->settings['whatsapp_phone_number_id'] ?? '';
            // If an invalid value (like an email) was stored earlier, don't prefill
            if (!empty($this->phoneNumberId) && !preg_match('/^\d{5,}$/', $this->phoneNumberId)) {
                $this->phoneNumberId = '';
            }
            $this->isConnected = !empty($this->accessToken) && !empty($this->phoneNumberId);
        }
        
        // Generate organization-specific verify token
        $this->verifyToken = 'ai_chat_' . ($org->slug ?? 'org') . '_' . substr(md5($org->id ?? 'default'), 0, 8);
        
        // Use the correct webhook URL (the working one)
        $this->webhookUrl = config('app.url') . '/api/webhooks/whatsapp';
        
        $this->updateConnectionStatus();
    }

    public function saveConfiguration()
    {
        $this->validate();

        $org = Auth::user()->primaryOrganization();
        if (!$org) {
            session()->flash('error', 'No organization found.');
            return;
        }

        try {
            $settings = $org->settings ?? [];
            $settings['whatsapp_access_token'] = $this->accessToken;
            $settings['whatsapp_phone_number_id'] = $this->phoneNumberId;
            $settings['whatsapp_verify_token'] = $this->verifyToken;
            
            $org->settings = $settings;
            $org->save();

            $this->isConnected = true;
            $this->updateConnectionStatus();

            Log::info('WhatsApp configuration saved for organization', [
                'org_id' => $org->id,
                'org_name' => $org->name,
                'phone_number_id' => $this->phoneNumberId
            ]);

            session()->flash('success', 'WhatsApp configuration saved successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to save configuration: ' . $e->getMessage());
            Log::error('Failed to save WhatsApp configuration', [
                'org_id' => $org->id ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    public function testConnection()
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            session()->flash('error', 'Please provide Access Token and Phone Number ID first.');
            return;
        }

        try {
            // Test WhatsApp API connection by getting phone number info
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
            ])->get("https://graph.facebook.com/v17.0/{$this->phoneNumberId}");

            if ($response->successful()) {
                $phoneInfo = $response->json();
                session()->flash('success', 'Connection successful! Phone: ' . ($phoneInfo['display_phone_number'] ?? 'N/A'));
                $this->connectionStatus = 'Connected - ' . ($phoneInfo['display_phone_number'] ?? 'Unknown');
                $this->isConnected = true;
            } else {
                session()->flash('error', 'Connection failed: ' . $response->body());
                $this->connectionStatus = 'Connection Failed';
                $this->isConnected = false;
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Connection test failed: ' . $e->getMessage());
            $this->connectionStatus = 'Error: ' . $e->getMessage();
            $this->isConnected = false;
        }
    }

    public function disconnect()
    {
        $org = Auth::user()->primaryOrganization();
        if (!$org) return;

        try {
            $settings = $org->settings ?? [];
            unset($settings['whatsapp_access_token']);
            unset($settings['whatsapp_phone_number_id']);
            unset($settings['whatsapp_verify_token']);
            
            $org->settings = $settings;
            $org->save();

            $this->accessToken = '';
            $this->phoneNumberId = '';
            $this->isConnected = false;
            $this->connectionStatus = 'Not Connected';

            session()->flash('success', 'WhatsApp integration disconnected.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to disconnect: ' . $e->getMessage());
        }
    }

    private function updateConnectionStatus()
    {
        if ($this->isConnected) {
            $this->connectionStatus = 'Configured - Test connection to verify';
        } else {
            $this->connectionStatus = 'Not Connected';
        }
    }

    public function render()
    {
        // Render within the customer layout so header/sidebar appear
        return view('livewire.customer.whatsapp-integration')->layout('layouts.customer');
    }
}