<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Integration;
use Illuminate\Support\Str;

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
        
        // Create pending integration record
        $integration = Integration::create([
            'organization_id' => null, // Will be set after completion
            'platform' => 'shopify',
            'shop_domain' => $domain . '.myshopify.com',
            'status' => 'pending',
            'installation_token' => Str::random(32),
            'settings' => json_encode([
                'initiated_at' => now(),
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip()
            ])
        ]);
        
        // Build Shopify OAuth URL
        $shopifyClientId = env('SHOPIFY_CLIENT_ID', 'your_shopify_client_id');
        $redirectUri = urlencode(route('api.integrations.shopify.oauth.callback'));
        $scopes = 'read_themes,write_themes,read_script_tags,write_script_tags';
        $state = $integration->installation_token;
        
        $shopifyUrl = "https://{$domain}.myshopify.com/admin/oauth/authorize?" . http_build_query([
            'client_id' => $shopifyClientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'grant_options[]' => 'per_user'
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