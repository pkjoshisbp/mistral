<?php

namespace App\Livewire\Public;

use Livewire\Component;

class ShopifyInstall extends Component
{
    public $step = 'start';
    public $shopDomain = '';
    public $errorMessage = '';
    
    protected $rules = [
        'shopDomain' => 'required|string|min:3|max:100'
    ];
    
    public function startInstallation()
    {
        $this->validate();
        
        // Clean up the shop domain
        $domain = strtolower(trim($this->shopDomain));
        $domain = str_replace(['http://', 'https://', '.myshopify.com'], '', $domain);
        
        if (empty($domain)) {
            $this->errorMessage = 'Please enter a valid shop domain.';
            return;
        }
        
        // Build Shopify OAuth URL
        $shopifyClientId = config('services.shopify.key');
        $redirectUri = route('api.integrations.shopify.oauth.callback');
        $scopes = 'read_script_tags,write_script_tags';
        
        $shopifyUrl = "https://{$domain}.myshopify.com/admin/oauth/authorize?" . http_build_query([
            'client_id' => $shopifyClientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
        ]);
        
        \Log::info('Shopify OAuth initiated', [
            'shop' => $domain . '.myshopify.com',
            'redirect_uri' => $redirectUri,
        ]);
        
        // Redirect to Shopify OAuth
        return redirect()->to($shopifyUrl);
    }
    
    public function render()
    {
        return view('livewire.public.shopify-install')
            ->layout('layouts.public')
            ->title('Install AI Chat Support - Shopify App');
    }
}