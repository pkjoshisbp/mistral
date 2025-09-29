<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Organization;
use App\Models\Integration;

class IntegrationController extends Controller
{
    /**
     * Register a new integration (WordPress/WooCommerce or initiate Shopify OAuth)
     */
    public function register(Request $request)
    {
        $request->validate([
            'provider' => 'required|in:shopify,woocommerce,wordpress',
            'shop' => 'required|string',
            'return_url' => 'nullable|url'
        ]);

        $provider = $request->input('provider');
        $shop = $request->input('shop');
        
        Log::info('Integration registration attempt', [
            'provider' => $provider,
            'shop' => $shop
        ]);

        if ($provider === 'shopify') {
            return $this->initiateShopifyOAuth($shop, $request->input('return_url'));
        }

        if (in_array($provider, ['woocommerce', 'wordpress'])) {
            return $this->registerWordPress($provider, $shop);
        }

        return response()->json(['ok' => false, 'message' => 'Invalid provider'], 400);
    }

    /**
     * Complete WordPress/WooCommerce registration with token
     */
    public function completeRegistration(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'site_name' => 'nullable|string',
            'admin_email' => 'nullable|email',
            'phone' => 'nullable|string',
            'description' => 'nullable|string',
            'welcome_message' => 'nullable|string'
        ]);

        $token = $request->input('token');
        
        // Find pending registration
        $pending = DB::table('integration_pending')
            ->where('token', $token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$pending) {
            return response()->json(['ok' => false, 'message' => 'Invalid or expired token'], 400);
        }

        // Create organization and integration
        $orgName = $request->input('site_name', parse_url($pending->site, PHP_URL_HOST));
        
        // Check if organization already exists with same website
        $existingOrg = Organization::where('website', $pending->site)->first();
        
        if ($existingOrg) {
            // Use existing organization
            $organization = $existingOrg;
            Log::info('Using existing organization for WordPress integration', [
                'existing_org_id' => $organization->id,
                'website' => $pending->site
            ]);
        } else {
            // Create new organization
            $slug = Str::slug($orgName . '-' . Str::random(6));
            
            $organization = Organization::create([
                'name' => $orgName,
                'slug' => $slug,
                'website' => $pending->site,
                'contact_email' => $request->input('admin_email'),
                'phone' => $request->input('phone', ''),
                'description' => $request->input('description', "WordPress/WooCommerce site integrated via plugin"),
                'token_balance' => 20000 // Initial 20K tokens for new organizations
            ]);
            
            Log::info('Created new organization for WordPress integration', [
                'org_id' => $organization->id,
                'website' => $pending->site
            ]);
        }

        // Check if integration already exists, otherwise create new one
        $integration = Integration::updateOrCreate(
            [
                'organization_id' => $organization->id,
                'provider' => $pending->provider
            ],
            [
                'shop' => $pending->site,
                'settings' => [] // No widget settings here anymore
            ]
        );

        // Save widget settings to organization instead
        $organizationSettings = $organization->settings ?? [];
        $organizationSettings = array_merge($organizationSettings, [
            'widget_position' => 'bottom-right',
            'primary_color' => '#007bff',
            'welcome_message' => $request->input('welcome_message', 'Hello! How can I help you today?'),
            'widget_offset_x' => 20,
            'widget_offset_y' => 20
        ]);
        
        $organization->settings = $organizationSettings;
        $organization->save();

        // Remove pending token
        DB::table('integration_pending')->where('id', $pending->id)->delete();

        Log::info('WordPress integration completed', [
            'org_id' => $organization->id,
            'provider' => $pending->provider,
            'site' => $pending->site
        ]);

        return response()->json([
            'ok' => true,
            'org_id' => $organization->id,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug
            ]
        ]);
    }

    /**
     * Get widget script for a specific organization
     */
    public function widgetScript($org_id)
    {
        $organization = Organization::find($org_id);
        if (!$organization) {
            return response('// Organization not found', 404)
                ->header('Content-Type', 'application/javascript');
        }

        // Use organization settings as single source of truth
        $settings = $organization->settings ?? [];

        // Generate the widget loader script
        $config = [
            'org_id' => $organization->id,
            'widget_position' => $settings['widget_position'] ?? 'bottom-right',
            'primary_color' => $settings['primary_color'] ?? '#007bff',
            'welcome_message' => $settings['welcome_message'] ?? 'Hello! How can I help you today?',
            'widget_offset_x' => $settings['widget_offset_x'] ?? 20,
            'widget_offset_y' => $settings['widget_offset_y'] ?? 20
        ];

        $js = "(function(){
            // AI Chat Support Widget Loader
            var config = " . json_encode($config) . ";
            
            // Load main widget script
            var script = document.createElement('script');
            script.src = 'https://ai-chat.support/widget/' + config.org_id + '/script.js';
            script.async = true;
            
            // Set widget configuration
            window.aiChatConfig = config;
            
            document.head.appendChild(script);
        })();";

        return response($js)->header('Content-Type', 'application/javascript');
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(Request $request, $org_id)
    {
        $organization = Organization::findOrFail($org_id);

        $settings = $organization->settings ?? [];
        
        // Update widget settings
        if ($request->has('widget_position')) {
            $settings['widget_position'] = $request->input('widget_position');
        }
        if ($request->has('primary_color')) {
            $settings['primary_color'] = $request->input('primary_color');
        }
        if ($request->has('welcome_message')) {
            $settings['welcome_message'] = $request->input('welcome_message');
        }
        if ($request->has('widget_offset_x')) {
            $settings['widget_offset_x'] = (int) $request->input('widget_offset_x');
        }
        if ($request->has('widget_offset_y')) {
            $settings['widget_offset_y'] = (int) $request->input('widget_offset_y');
        }

        $organization->settings = $settings;
        $organization->save();

        Log::info('Widget config updated', [
            'org_id' => $org_id,
            'settings' => $settings
        ]);

        return response()->json(['ok' => true, 'settings' => $settings]);
    }

    /**
     * Get widget configuration
     */
    public function getWidgetConfig($org_id)
    {
        $organization = Organization::findOrFail($org_id);

        $settings = $organization->settings ?? [
            'widget_position' => 'bottom-right',
            'primary_color' => '#007bff',
            'welcome_message' => 'Hello! How can I help you today?',
            'widget_offset_x' => 20,
            'widget_offset_y' => 20
        ];

        return response()->json([
            'ok' => true,
            'org_id' => $org_id,
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug
            ],
            'settings' => $settings
        ]);
    }

    /**
     * Initiate Shopify OAuth flow
     */
    private function initiateShopifyOAuth($shop, $returnUrl = null)
    {
        $apiKey = config('services.shopify.key');
        if (!$apiKey) {
            return response()->json(['ok' => false, 'message' => 'Shopify API not configured'], 500);
        }

        $scopes = 'read_products,read_orders,read_customers,write_script_tags';
        $state = Str::random(24);
        $redirectUri = urlencode(config('app.url') . '/api/integrations/shopify/oauth/callback');
        
        $installUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => config('app.url') . '/api/integrations/shopify/oauth/callback',
            'state' => $state
        ]);

        // Store state for verification
        session(['shopify_oauth_state' => $state, 'shopify_return_url' => $returnUrl]);

        return response()->json([
            'ok' => true,
            'install_url' => $installUrl,
            'message' => 'Redirect to install_url to complete Shopify installation'
        ]);
    }

    /**
     * Register WordPress/WooCommerce site
     */
    private function registerWordPress($provider, $site)
    {
        $token = Str::random(32);
        
        DB::table('integration_pending')->insert([
            'provider' => $provider,
            'site' => $site,
            'token' => $token,
            'expires_at' => now()->addHours(24), // 24-hour expiry
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return response()->json([
            'ok' => true,
            'token' => $token,
            'message' => 'Use this token to complete registration',
            'expires_in' => '24 hours'
        ]);
    }

    /**
     * Handle Shopify OAuth callback
     */
    public function shopifyCallback(Request $request)
    {
        $shop = $request->get('shop');
        $code = $request->get('code');
        $state = $request->get('state');

        // Verify state
        if (!$state || $state !== session('shopify_oauth_state')) {
            Log::warning('Shopify OAuth state mismatch', ['provided' => $state]);
            return response('Invalid state parameter', 400);
        }

        // Exchange code for access token
        $tokenResponse = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('services.shopify.key'),
            'client_secret' => config('services.shopify.secret'),
            'code' => $code
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Shopify token exchange failed', ['response' => $tokenResponse->body()]);
            return response('Failed to get access token', 500);
        }

        $accessToken = $tokenResponse->json()['access_token'];

        // Create or find organization
        $existingOrg = Organization::where('website', "https://{$shop}")->first();
        
        if ($existingOrg) {
            // Use existing organization
            $organization = $existingOrg;
            Log::info('Using existing organization for Shopify integration', [
                'existing_org_id' => $organization->id,
                'shop' => $shop
            ]);
        } else {
            // Create new organization with default values
            $organization = Organization::create([
                'name' => $shop,
                'slug' => Str::slug($shop . '-' . Str::random(6)),
                'website' => "https://{$shop}",
                'description' => 'Shopify store integrated via app',
                'token_balance' => 20000 // Initial 20K tokens for new organizations
            ]);
            
            Log::info('Created new organization for Shopify integration', [
                'org_id' => $organization->id,
                'shop' => $shop
            ]);
        }

        // Save integration (only provider-specific data)
        $integration = Integration::updateOrCreate(
            ['organization_id' => $organization->id, 'provider' => 'shopify'],
            [
                'shop' => $shop,
                'access_token' => $accessToken,
                'settings' => [] // No widget settings here anymore
            ]
        );

        // Save widget settings to organization instead
        $organizationSettings = $organization->settings ?? [];
        $organizationSettings = array_merge($organizationSettings, [
            'widget_position' => 'bottom-right',
            'primary_color' => '#007bff',
            'welcome_message' => 'Hello! How can I help you today?',
            'widget_offset_x' => 20,
            'widget_offset_y' => 20
        ]);
        
        $organization->settings = $organizationSettings;
        $organization->save();

        // Create ScriptTag to inject widget
        $this->createShopifyScriptTag($shop, $accessToken, $organization->id);

        Log::info('Shopify integration completed', [
            'shop' => $shop,
            'org_id' => $organization->id
        ]);

        // Redirect to return URL or app dashboard
        $returnUrl = session('shopify_return_url', config('app.url') . '/dashboard');
        return redirect($returnUrl . '?installed=true&org_id=' . $organization->id);
    }

    /**
     * Create Shopify ScriptTag
     */
    private function createShopifyScriptTag($shop, $accessToken, $orgId)
    {
        $scriptSrc = config('app.url') . "/api/integrations/widget-script/{$orgId}";
        
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json'
        ])->post("https://{$shop}/admin/api/2025-01/script_tags.json", [
            'script_tag' => [
                'event' => 'onload',
                'src' => $scriptSrc
            ]
        ]);

        if ($response->successful()) {
            Log::info('Shopify ScriptTag created successfully', [
                'shop' => $shop,
                'org_id' => $orgId,
                'script_src' => $scriptSrc
            ]);
        } else {
            Log::error('Failed to create Shopify ScriptTag', [
                'shop' => $shop,
                'org_id' => $orgId,
                'response' => $response->body()
            ]);
        }
    }

    /**
     * Handle Shopify webhooks
     */
    public function shopifyWebhook(Request $request)
    {
        $topic = $request->header('X-Shopify-Topic');
        $shop = $request->header('X-Shopify-Shop-Domain');
        
        Log::info('Shopify webhook received', [
            'topic' => $topic,
            'shop' => $shop
        ]);

        if ($topic === 'app/uninstalled') {
            // Mark integration as inactive when app is uninstalled
            $integration = Integration::where('shop', $shop)
                ->where('provider', 'shopify')
                ->first();
                
            if ($integration) {
                $integration->update(['active' => false]);
                Log::info('Shopify integration deactivated', [
                    'shop' => $shop,
                    'org_id' => $integration->organization_id
                ]);
            }
        }

        return response('ok', 200);
    }
}
