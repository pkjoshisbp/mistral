<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

class ShopifyOnboarding extends Component
{
    public $shop;
    public $organization;
    public $integration;
    public $currentStep = 1;
    public $deepLink = '';
    
    public function mount()
    {
        $this->shop = request('shop');
        
        if (!$this->shop) {
            abort(404, 'Shop parameter required');
        }
        
        // Find the integration
        $this->integration = Integration::where('shop', $this->shop)
            ->where('provider', 'shopify')
            ->first();
            
        if (!$this->integration) {
            abort(404, 'Shopify integration not found');
        }
        
        $this->organization = $this->integration->organization;
        
        if (!$this->organization) {
            abort(404, 'Organization not found');
        }
        
        // Generate deep link to theme editor - activates the app embed directly
        // extensionUid comes from extensions/ai-chat-widget/shopify.extension.toml (uid field)
        // blockHandle is the filename of the liquid block without .liquid extension
        $extensionUid = '45c51f62-01cb-9718-b977-78f5108db8351ae77f7a';
        $blockHandle = 'chat-widget';
        $this->deepLink = "https://{$this->shop}/admin/themes/current/editor?context=apps&activateAppId={$extensionUid}/{$blockHandle}";
        
        Log::info('Shopify onboarding page loaded', [
            'shop' => $this->shop,
            'org_id' => $this->organization->id,
            'deep_link' => $this->deepLink
        ]);
    }
    
    public function nextStep()
    {
        if ($this->currentStep < 5) {
            $this->currentStep++;
        }
    }
    
    public function previousStep()
    {
        if ($this->currentStep > 1) {
            $this->currentStep--;
        }
    }
    
    public function completedSetup()
    {
        return redirect()->route('customer.dashboard')
            ->with('success', 'Great! Your AI Chat Support is ready to go.');
    }
    
    public function render()
    {
        return view('livewire.public.shopify-onboarding')
            ->layout('layouts.public')
            ->title('Setup AI Chat Support - ' . $this->organization->name);
    }
}
