<?php

namespace App\Livewire\Public;

use Livewire\Component;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ShopifyInstall extends Component
{
    public $step = 'start';
    public $shopDomain = '';
    public $errorMessage = '';
    public $showManualEntry = false;
    public $autoDetectedShop = null;
    
    protected $rules = [
        'shopDomain' => 'required|string|min:3|max:100'
    ];
    
    public function mount()
    {
        // ISSUE 7 FIX: Auto-detect shop from URL parameter (Shopify provides this)
        $shop = request('shop');
        
        if ($shop) {
            // Shopify provided the shop parameter - auto-redirect
            $this->autoDetectedShop = $shop;
            Log::info('Shopify install auto-detected', ['shop' => $shop]);
            
            // ISSUE 5A FIX: Check if already authenticated
            $integration = Integration::where('shop', $shop)
                ->where('provider', 'shopify')
                ->first();
                
            if ($integration && $integration->access_token) {
                // Already installed - check if user is logged in
                if (Auth::check()) {
                    Log::info('Shopify app already installed and user authenticated', [
                        'shop' => $shop,
                        'user_id' => Auth::id()
                    ]);
                    return redirect()->route('customer.dashboard')
                        ->with('info', 'Your Shopify store is already connected!');
                }
            }
            
            // Auto-initiate OAuth
            $this->shopDomain = str_replace('.myshopify.com', '', $shop);
            return $this->startInstallation();
        } else {
            // No shop parameter - show manual entry (edge case only)
            $this->showManualEntry = true;
            Log::info('No shop parameter - showing manual entry form');
        }
    }
    
    public function startInstallation()
    {
        if (!$this->autoDetectedShop) {
            $this->validate();
        }
        
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
        // Updated scopes (removed write_script_tags per Issue 3 fix)
        $scopes = 'read_products,read_orders,read_themes';
        
        $shopifyUrl = "https://{$domain}.myshopify.com/admin/oauth/authorize?" . http_build_query([
            'client_id' => $shopifyClientId,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
        ]);
        
        Log::info('Shopify OAuth initiated', [
            'shop' => $domain . '.myshopify.com',
            'redirect_uri' => $redirectUri,
            'auto_detected' => $this->autoDetectedShop ? 'yes' : 'no'
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