<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Models\Organization;
use App\Models\Integration;
use App\Models\User;
use App\Mail\ShopifyWelcomeEmail;

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
            'shop' => $shop,
            'return_url' => $request->input('return_url'),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        if ($provider === 'shopify') {
            return $this->initiateShopifyOAuth($shop, $request->input('return_url'));
        }

        if (in_array($provider, ['woocommerce', 'wordpress'])) {
            return $this->registerWordPress($provider, $shop);
        }

        Log::warning('Invalid provider specified', ['provider' => $provider]);
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
        
        Log::info('Initiating Shopify OAuth', [
            'shop' => $shop,
            'has_api_key' => !empty($apiKey),
            'return_url' => $returnUrl
        ]);
        
        if (!$apiKey) {
            Log::error('Shopify OAuth failed - API key not configured');
            return response()->json(['ok' => false, 'message' => 'Shopify API not configured'], 500);
        }

        // Minimal permissions - only what's needed for chat widget
        // write_script_tags: Required to inject the chat widget script into the store
        $scopes = 'write_script_tags';
        $state = Str::random(24);
        $redirectUri = config('app.url') . '/api/integrations/shopify/oauth/callback';
        
        $installUrl = "https://{$shop}/admin/oauth/authorize?" . http_build_query([
            'client_id' => $apiKey,
            'scope' => $scopes,
            'redirect_uri' => $redirectUri,
            'state' => $state
        ]);

        // Store state for verification
        session(['shopify_oauth_state' => $state, 'shopify_return_url' => $returnUrl]);

        Log::info('Shopify OAuth URL generated', [
            'shop' => $shop,
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'scopes' => $scopes
        ]);

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
        $hmac = $request->get('hmac');

        Log::info('Shopify OAuth callback received', [
            'shop' => $shop,
            'has_code' => !empty($code),
            'has_hmac' => !empty($hmac),
            'all_params' => $request->all()
        ]);

        // Verify HMAC instead of session state (more reliable for OAuth redirects)
        if (!$this->verifyShopifyHmac($request)) {
            Log::warning('Shopify OAuth HMAC verification failed', [
                'shop' => $shop,
                'provided_hmac' => $hmac
            ]);
            return response('Invalid request signature', 400);
        }

        Log::info('Shopify OAuth HMAC verified, exchanging code for token', [
            'shop' => $shop
        ]);

        // Exchange code for access token
        $tokenResponse = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('services.shopify.key'),
            'client_secret' => config('services.shopify.secret'),
            'code' => $code
        ]);

        if (!$tokenResponse->successful()) {
            Log::error('Shopify token exchange failed', [
                'shop' => $shop,
                'status' => $tokenResponse->status(),
                'response' => $tokenResponse->body()
            ]);
            return response('Failed to get access token', 500);
        }

        $accessToken = $tokenResponse->json()['access_token'];
        
        Log::info('Shopify access token obtained successfully', [
            'shop' => $shop,
            'has_token' => !empty($accessToken)
        ]);

        // Fetch shop details to get owner email and information
        $shopResponse = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
        ])->get("https://{$shop}/admin/api/2025-01/shop.json");

        $shopData = $shopResponse->json()['shop'] ?? [];
        $shopOwnerEmail = $shopData['email'] ?? null;
        $shopOwnerName = $shopData['shop_owner'] ?? 'Store Owner';
        $shopName = $shopData['name'] ?? str_replace('.myshopify.com', '', $shop);
        $shopPhone = $shopData['phone'] ?? null;
        
        Log::info('Shopify shop details fetched', [
            'shop' => $shop,
            'shop_name' => $shopName,
            'has_email' => !empty($shopOwnerEmail),
            'owner_name' => $shopOwnerName
        ]);

        // Strategy: Check if user exists and has an organization first
        $organization = null;
        $existingUser = null;
        
        if ($shopOwnerEmail) {
            $existingUser = User::where('email', $shopOwnerEmail)->first();
            
            if ($existingUser) {
                // User exists - check if they have an organization
                $userOrganizations = $existingUser->organizations;
                
                if ($userOrganizations->count() > 0) {
                    // Use the user's first organization (they're already set up)
                    $organization = $userOrganizations->first();
                    
                    Log::info('User already has organization - using existing org', [
                        'user_id' => $existingUser->id,
                        'org_id' => $organization->id,
                        'org_name' => $organization->name,
                        'shop' => $shop
                    ]);
                }
            }
        }
        
        // If no organization found from user, check by website URL
        if (!$organization) {
            $organization = Organization::where('website', "https://{$shop}")->first();
            
            if ($organization) {
                Log::info('Found existing organization by website URL', [
                    'org_id' => $organization->id,
                    'shop' => $shop
                ]);
            }
        }
        
        // If still no organization, create a new one
        if (!$organization) {
            // Generate unique slug
            $baseSlug = Str::slug($shopName);
            $slug = $baseSlug;
            $counter = 2;
            
            while (Organization::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            $organization = Organization::create([
                'name' => $shopName, // Use shop name, not owner name
                'slug' => $slug,
                'website' => "https://{$shop}",
                'contact_email' => $shopOwnerEmail,
                'contact_phone' => $shopPhone,
                'description' => 'Shopify E-Commerce Store',
                'token_balance' => 20000 // Initial 20K tokens for new organizations
            ]);
            
            Log::info('Created new organization for Shopify integration', [
                'org_id' => $organization->id,
                'org_name' => $organization->name,
                'org_slug' => $organization->slug,
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

        Log::info('Shopify integration record saved', [
            'integration_id' => $integration->id,
            'org_id' => $organization->id,
            'shop' => $shop
        ]);

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

        Log::info('Organization widget settings saved', [
            'org_id' => $organization->id,
            'settings' => $organizationSettings
        ]);

        // Create or find user and associate with organization
        $user = null;
        $isNewUser = false;
        $generatedPassword = null;

        if ($shopOwnerEmail) {
            // Check if user already exists with this email
            $user = User::where('email', $shopOwnerEmail)->first();

            if (!$user) {
                // Generate strong random password
                $generatedPassword = Str::random(16);
                
                // Create new user
                $user = User::create([
                    'name' => $shopOwnerName,
                    'email' => $shopOwnerEmail,
                    'password' => Hash::make($generatedPassword),
                    'email_verified_at' => now(), // Auto-verify since from Shopify
                ]);
                
                // Give initial credits to new Shopify users (20,000 tokens for trial)
                $userCredit = \App\Models\UserCredit::getOrCreateForUser($user->id);
                $userCredit->addCredits(20000.00, 'Initial trial credits for Shopify app installation', [
                    'source' => 'shopify_install',
                    'shop' => $shop
                ]);
                
                $isNewUser = true;
                
                Log::info('Created new user for Shopify installation', [
                    'user_id' => $user->id,
                    'email' => $shopOwnerEmail,
                    'org_id' => $organization->id,
                    'initial_credits' => 20000.00
                ]);
            } else {
                Log::info('Found existing user for Shopify installation', [
                    'user_id' => $user->id,
                    'email' => $shopOwnerEmail,
                    'org_id' => $organization->id
                ]);
            }

            // Associate user with organization (if not already associated)
            if (!$organization->users()->where('user_id', $user->id)->exists()) {
                $organization->users()->attach($user->id);
                
                Log::info('Associated user with organization', [
                    'user_id' => $user->id,
                    'org_id' => $organization->id
                ]);
            }

            // Send welcome email if new user
            if ($isNewUser && $generatedPassword) {
                try {
                    Mail::to($user->email)->send(new ShopifyWelcomeEmail($user, $generatedPassword, $organization));
                    
                    Log::info('Welcome email sent to new Shopify user', [
                        'user_id' => $user->id,
                        'email' => $user->email,
                        'org_id' => $organization->id
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send welcome email', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Auto-login the user for seamless experience
            Auth::login($user);
            
            // Regenerate session to ensure fresh data
            request()->session()->regenerate();
            
            // Reload user with organizations to ensure relationship is available
            $user->load('organizations');
            
            Log::info('User auto-logged in after Shopify installation', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'organizations_count' => $user->organizations->count()
            ]);
        } else {
            Log::warning('No email found from Shopify shop data - user not created', [
                'shop' => $shop,
                'org_id' => $organization->id
            ]);
        }

        // Create ScriptTag to inject widget
        $scriptTagResult = $this->createShopifyScriptTag($shop, $accessToken, $organization->id);

        Log::info('Shopify integration completed', [
            'shop' => $shop,
            'org_id' => $organization->id,
            'script_tag_created' => $scriptTagResult,
            'user_created' => $isNewUser,
            'user_logged_in' => $user !== null
        ]);

        // Redirect to customer dashboard with success message
        if ($user) {
            return redirect()->route('customer.dashboard')
                ->with('success', 'Shopify app installed successfully! Your AI chat widget is now live on your store.' . 
                    ($isNewUser ? ' Check your email for login credentials.' : ''));
        } else {
            // No user created (no email from Shopify) - redirect to manual setup
            return redirect()->route('shopify.complete-setup', [
                'org_id' => $organization->id,
                'shop' => $shop
            ])->with('warning', 'Please complete your account setup to manage your AI chat widget.');
        }
    }

    /**
     * Create Shopify ScriptTag
     */
    private function createShopifyScriptTag($shop, $accessToken, $orgId)
    {
        $scriptSrc = config('app.url') . "/api/integrations/widget-script/{$orgId}";
        
        Log::info('Creating Shopify ScriptTag', [
            'shop' => $shop,
            'org_id' => $orgId,
            'script_src' => $scriptSrc
        ]);
        
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
            $scriptTagData = $response->json();
            Log::info('Shopify ScriptTag created successfully', [
                'shop' => $shop,
                'org_id' => $orgId,
                'script_src' => $scriptSrc,
                'script_tag_id' => $scriptTagData['script_tag']['id'] ?? null,
                'response' => $scriptTagData
            ]);
            return true;
        } else {
            Log::error('Failed to create Shopify ScriptTag', [
                'shop' => $shop,
                'org_id' => $orgId,
                'status' => $response->status(),
                'response' => $response->body(),
                'error' => $response->json()
            ]);
            return false;
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
            // Find the integration and associated organization
            $integration = Integration::where('shop', $shop)
                ->where('provider', 'shopify')
                ->first();
                
            if ($integration) {
                $organization = $integration->organization;
                
                if ($organization) {
                    Log::info('Processing Shopify app uninstall - cleaning up data', [
                        'shop' => $shop,
                        'org_id' => $organization->id,
                        'org_slug' => $organization->slug
                    ]);

                    try {
                        // Delete Qdrant collection
                        $aiService = new \App\Services\AiAgentService();
                        $collectionName = str_replace('-', '_', $organization->slug);
                        $aiService->deleteCollection($collectionName);
                        Log::info('Deleted Qdrant collection for uninstalled Shopify app', [
                            'collection' => $collectionName
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Failed to delete Qdrant collection during Shopify uninstall', [
                            'shop' => $shop,
                            'error' => $e->getMessage()
                        ]);
                    }

                    // Find and delete users associated ONLY with this organization
                    $users = $organization->users;
                    foreach ($users as $user) {
                        // Only delete user if they belong to just this organization
                        if ($user->organizations()->count() === 1) {
                            Log::info('Deleting user associated with uninstalled Shopify app', [
                                'user_id' => $user->id,
                                'email' => $user->email
                            ]);
                            $user->delete();
                        } else {
                            // Just detach from this organization
                            $organization->users()->detach($user->id);
                            Log::info('Detached user from organization (user has other orgs)', [
                                'user_id' => $user->id,
                                'email' => $user->email
                            ]);
                        }
                    }

                    // Delete related data
                    $organization->organizationData()->delete();
                    
                    // Delete chat sessions and their messages
                    foreach ($organization->chatSessions as $session) {
                        $session->messages()->delete();
                    }
                    $organization->chatSessions()->delete();
                    
                    // Delete chat conversations and their messages
                    foreach ($organization->chatConversations as $conversation) {
                        $conversation->messages()->delete();
                    }
                    $organization->chatConversations()->delete();
                    
                    $organization->tokenUsageLogs()->delete();
                    $organization->integrations()->delete();
                    
                    // Delete the organization itself
                    $organization->delete();
                    
                    Log::info('Successfully cleaned up organization for uninstalled Shopify app', [
                        'shop' => $shop,
                        'org_id' => $organization->id
                    ]);
                } else {
                    // Just delete the integration if no organization found
                    $integration->delete();
                    Log::warning('Shopify integration found but no organization - deleted integration only', [
                        'shop' => $shop
                    ]);
                }
            } else {
                Log::warning('Shopify uninstall webhook received but no integration found', [
                    'shop' => $shop
                ]);
            }
        }

        return response('ok', 200);
    }

    /**
     * Verify Shopify HMAC signature
     */
    private function verifyShopifyHmac(Request $request)
    {
        $hmac = $request->get('hmac');
        
        if (!$hmac) {
            return false;
        }

        // Get all parameters except hmac and signature
        $params = $request->except(['hmac', 'signature']);
        
        // Sort parameters alphabetically
        ksort($params);
        
        // Build query string
        $queryString = http_build_query($params);
        
        // Calculate HMAC
        $calculatedHmac = hash_hmac('sha256', $queryString, config('services.shopify.secret'));
        
        // Compare HMACs (constant time comparison to prevent timing attacks)
        return hash_equals($calculatedHmac, $hmac);
    }
}

