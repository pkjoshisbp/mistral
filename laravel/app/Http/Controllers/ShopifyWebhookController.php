<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;

class ShopifyWebhookController extends Controller
{
    /**
     * Handle all Shopify webhooks
     * This is the main entry point that will route to specific handlers
     */
    public function handle(Request $request)
    {
        // DETAILED LOGGING FOR SHOPIFY AUTOMATED TESTS
        Log::info('=== SHOPIFY WEBHOOK REQUEST RECEIVED ===', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'headers' => [
                'X-Shopify-Topic' => $request->header('X-Shopify-Topic'),
                'X-Shopify-Shop-Domain' => $request->header('X-Shopify-Shop-Domain'),
                'X-Shopify-Hmac-Sha256' => $request->header('X-Shopify-Hmac-Sha256') ? 'present' : 'MISSING',
                'X-Shopify-API-Version' => $request->header('X-Shopify-API-Version'),
                'Content-Type' => $request->header('Content-Type'),
            ],
            'body_length' => strlen($request->getContent()),
            'is_shopify_test' => strpos($request->userAgent() ?? '', 'Shopify') !== false
        ]);

        // Verify HMAC signature BEFORE processing
        if (!$this->verifyHmac($request)) {
            Log::warning('❌ SHOPIFY WEBHOOK HMAC VERIFICATION FAILED', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop' => $request->header('X-Shopify-Shop-Domain'),
                'hmac_header' => $request->header('X-Shopify-Hmac-Sha256') ? 'present' : 'MISSING',
                'body_preview' => substr($request->getContent(), 0, 200),
                'secret_configured' => config('services.shopify.secret') ? 'yes' : 'NO - CHECK .ENV'
            ]);
            return response('Unauthorized', 401);
        }

        $topic = $request->header('X-Shopify-Topic');
        $shop = $request->header('X-Shopify-Shop-Domain');
        $data = $request->all();

        Log::info('✅ SHOPIFY WEBHOOK HMAC VERIFIED - Processing...', [
            'topic' => $topic,
            'shop' => $shop,
            'data_keys' => array_keys($data),
            'timestamp' => now()->toIso8601String()
        ]);

        // Route to appropriate handler based on topic
        switch ($topic) {
            case 'app/uninstalled':
                Log::info('→ Routing to handleAppUninstalled', ['shop' => $shop]);
                return $this->handleAppUninstalled($shop, $data);
            
            case 'customers/data_request':
                Log::info('→ Routing to handleCustomersDataRequest', ['shop' => $shop]);
                return $this->handleCustomersDataRequest($shop, $data);
            
            case 'customers/redact':
                Log::info('→ Routing to handleCustomersRedact', ['shop' => $shop]);
                return $this->handleCustomersRedact($shop, $data);
            
            case 'shop/redact':
                Log::info('→ Routing to handleShopRedact', ['shop' => $shop]);
                return $this->handleShopRedact($shop, $data);
            
            default:
                Log::warning('⚠️ UNHANDLED SHOPIFY WEBHOOK TOPIC', [
                    'topic' => $topic,
                    'shop' => $shop,
                    'available_topics' => ['app/uninstalled', 'customers/data_request', 'customers/redact', 'shop/redact']
                ]);
                return response('ok', 200);
        }
    }

    /**
     * Handle app uninstalled webhook
     * This is triggered when a merchant uninstalls your app
     * 
     * Returns 200 immediately and processes cleanup asynchronously
     */
    private function handleAppUninstalled($shop, $data)
    {
        Log::info('Processing Shopify app uninstall', ['shop' => $shop]);

        $integration = Integration::where('shop', $shop)
            ->where('provider', 'shopify')
            ->first();

        if (!$integration) {
            Log::warning('App uninstall webhook received but no integration found', ['shop' => $shop]);
            return response('ok', 200);
        }

        $organization = $integration->organization;

        if ($organization) {
            // Dispatch cleanup job for async processing (return 200 fast)
            Log::info('Dispatching async cleanup job for Shopify uninstall', [
                'shop' => $shop,
                'org_id' => $organization->id
            ]);
            
            \App\Jobs\CleanupShopifyUninstall::dispatch($organization->id, $shop);
        }

        // Return 200 immediately (Shopify expects fast response)
        Log::info('✅ app/uninstalled webhook processed successfully - Returning HTTP 200', [
            'shop' => $shop,
            'response_time_ms' => round((microtime(true) - LARAVEL_START) * 1000, 2)
        ]);
        return response('ok', 200);
    }

    /**
     * Handle customers/data_request webhook
     * GDPR compliance: Provide customer data when requested
     * 
     * Shopify requires you to respond within 30 days with the customer's data
     */
    private function handleCustomersDataRequest($shop, $data)
    {
        $customerId = $data['customer']['id'] ?? null;
        $customerEmail = $data['customer']['email'] ?? null;
        $ordersRequested = $data['orders_requested'] ?? [];

        Log::info('GDPR: Customer data request received', [
            'shop' => $shop,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'orders_count' => count($ordersRequested)
        ]);

        // Find the integration
        $integration = Integration::where('shop', $shop)
            ->where('provider', 'shopify')
            ->first();

        if (!$integration) {
            Log::warning('Data request received but no integration found', ['shop' => $shop]);
            return response('ok', 200);
        }

        $organization = $integration->organization;

        // Collect customer data from your system
        $customerData = [
            'shop' => $shop,
            'customer_email' => $customerEmail,
            'customer_id' => $customerId,
            'data_collected' => now()->toIso8601String(),
            'chat_data' => []
        ];

        // Collect chat conversations for this customer (if email matches)
        if ($customerEmail && $organization) {
            $conversations = $organization->chatConversations()
                ->where('customer_email', $customerEmail)
                ->with('messages')
                ->get();

            foreach ($conversations as $conversation) {
                $customerData['chat_data'][] = [
                    'conversation_id' => $conversation->id,
                    'created_at' => $conversation->created_at->toIso8601String(),
                    'messages' => $conversation->messages->map(function ($msg) {
                        return [
                            'sender' => $msg->sender,
                            'message' => $msg->message,
                            'created_at' => $msg->created_at->toIso8601String()
                        ];
                    })
                ];
            }
        }

        // Log the data request for compliance tracking
        Log::info('GDPR: Customer data collected', [
            'shop' => $shop,
            'customer_email' => $customerEmail,
            'conversations_count' => count($customerData['chat_data'])
        ]);

        // In production, you should:
        // 1. Store this data securely
        // 2. Send it to the merchant or customer via email
        // 3. Keep an audit trail
        // For now, we're just logging it

        // You could also send this data via email
        try {
            // TODO: Implement email sending to merchant with customer data
            // Mail::to($merchant_email)->send(new CustomerDataRequest($customerData));
        } catch (\Exception $e) {
            Log::error('Failed to send customer data request email', [
                'error' => $e->getMessage()
            ]);
        }

        Log::info('✅ customers/data_request webhook processed - Returning HTTP 200', [
            'shop' => $shop,
            'customer_email' => $customerEmail
        ]);
        return response('ok', 200);
    }

    /**
     * Handle customers/redact webhook
     * GDPR compliance: Delete customer data when requested
     * 
     * This is triggered 48 hours after a customer requests deletion
     */
    private function handleCustomersRedact($shop, $data)
    {
        $customerId = $data['customer']['id'] ?? null;
        $customerEmail = $data['customer']['email'] ?? null;
        $ordersToRedact = $data['orders_to_redact'] ?? [];

        Log::info('GDPR: Customer redaction request received', [
            'shop' => $shop,
            'customer_id' => $customerId,
            'customer_email' => $customerEmail,
            'orders_count' => count($ordersToRedact)
        ]);

        $integration = Integration::where('shop', $shop)
            ->where('provider', 'shopify')
            ->first();

        if (!$integration) {
            Log::warning('Redaction request received but no integration found', ['shop' => $shop]);
            return response('ok', 200);
        }

        $organization = $integration->organization;

        if ($customerEmail && $organization) {
            // Find and delete all chat conversations for this customer
            $conversations = $organization->chatConversations()
                ->where('customer_email', $customerEmail)
                ->get();

            $deletedCount = 0;
            foreach ($conversations as $conversation) {
                // Delete messages first
                $conversation->messages()->delete();
                $conversation->delete();
                $deletedCount++;
            }

            Log::info('GDPR: Customer data redacted', [
                'shop' => $shop,
                'customer_email' => $customerEmail,
                'conversations_deleted' => $deletedCount
            ]);
        }

        Log::info('✅ customers/redact webhook processed - Returning HTTP 200', [
            'shop' => $shop,
            'customer_email' => $customerEmail
        ]);
        return response('ok', 200);
    }

    /**
     * Handle shop/redact webhook
     * GDPR compliance: Delete shop data after app uninstall
     * 
     * This is triggered 48 hours after a shop uninstalls your app
     */
    private function handleShopRedact($shop, $data)
    {
        $shopId = $data['shop_id'] ?? null;
        $shopDomain = $data['shop_domain'] ?? $shop;

        Log::info('GDPR: Shop redaction request received', [
            'shop' => $shop,
            'shop_id' => $shopId,
            'shop_domain' => $shopDomain
        ]);

        // Find and delete all data related to this shop
        $integration = Integration::where('shop', $shop)
            ->where('provider', 'shopify')
            ->first();

        if (!$integration) {
            Log::warning('Shop redaction request received but no integration found', ['shop' => $shop]);
            return response('ok', 200);
        }

        $organization = $integration->organization;

        if ($organization) {
            try {
                // Delete Qdrant collection
                $aiService = new \App\Services\AiAgentService();
                $collectionName = str_replace('-', '_', $organization->slug);
                $aiService->deleteCollection($collectionName);
            } catch (\Exception $e) {
                Log::error('Failed to delete Qdrant collection during shop redaction', [
                    'error' => $e->getMessage()
                ]);
            }

            // Delete all organization data
            $organization->organizationData()->delete();
            
            foreach ($organization->chatSessions as $session) {
                $session->messages()->delete();
            }
            $organization->chatSessions()->delete();
            
            foreach ($organization->chatConversations as $conversation) {
                $conversation->messages()->delete();
            }
            $organization->chatConversations()->delete();
            
            $organization->tokenUsageLogs()->delete();
            
            // Clean up users
            foreach ($organization->users as $user) {
                if ($user->organizations()->count() === 1) {
                    $user->delete();
                } else {
                    $organization->users()->detach($user->id);
                }
            }
            
            $organization->integrations()->delete();
            $organization->delete();

            Log::info('GDPR: Shop data fully redacted', [
                'shop' => $shop,
                'org_id' => $organization->id
            ]);
        }

        Log::info('✅ shop/redact webhook processed - Returning HTTP 200', [
            'shop' => $shop
        ]);
        return response('ok', 200);
    }

    /**
     * Verify Shopify webhook HMAC signature
     * This is CRITICAL for security - always verify webhooks are from Shopify
     */
    private function verifyHmac(Request $request)
    {
        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        
        if (!$hmac) {
            Log::warning('❌ HMAC VERIFICATION FAILED: Missing X-Shopify-Hmac-Sha256 header', [
                'all_headers' => $request->headers->all(),
                'url' => $request->fullUrl()
            ]);
            return false;
        }

        $data = $request->getContent();
        $secret = config('services.shopify.secret');
        
        if (!$secret) {
            Log::error('❌ CRITICAL: SHOPIFY SECRET NOT CONFIGURED', [
                'config_path' => 'services.shopify.secret',
                'env_check' => env('SHOPIFY_SECRET') ? 'ENV exists' : 'ENV MISSING',
                'help' => 'Add SHOPIFY_SECRET to .env file'
            ]);
            return false;
        }

        $calculatedHmac = base64_encode(hash_hmac('sha256', $data, $secret, true));
        
        // Use hash_equals to prevent timing attacks
        $verified = hash_equals($calculatedHmac, $hmac);
        
        if (!$verified) {
            Log::warning('❌ HMAC MISMATCH - Signature verification failed', [
                'expected_hmac' => substr($calculatedHmac, 0, 30) . '...',
                'received_hmac' => substr($hmac, 0, 30) . '...',
                'body_length' => strlen($data),
                'body_preview' => substr($data, 0, 100),
                'secret_length' => strlen($secret),
                'help' => 'Check that SHOPIFY_SECRET matches the App Client Secret in Partner Dashboard'
            ]);
        } else {
            Log::info('✅ HMAC VERIFIED SUCCESSFULLY', [
                'hmac_preview' => substr($hmac, 0, 20) . '...',
                'body_length' => strlen($data)
            ]);
        }
        
        return $verified;
    }
}
