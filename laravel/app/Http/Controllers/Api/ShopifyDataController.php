<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopifyApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopifyDataController extends Controller
{
    protected $shopifyService;

    public function __construct(ShopifyApiService $shopifyService)
    {
        $this->shopifyService = $shopifyService;
    }

    /**
     * Query Shopify data for AI chat
     * POST /api/shopify/query
     * 
     * Body: {
     *   "shop_domain": "store.myshopify.com",
     *   "query": "What products do you have?",
     *   "query_type": "products|order|shop_info|auto"
     * }
     */
    public function query(Request $request)
    {
        $validated = $request->validate([
            'shop_domain' => 'required|string',
            'query' => 'required|string',
            'query_type' => 'sometimes|string|in:products,order,shop_info,auto',
        ]);

        $shopDomain = $validated['shop_domain'];
        $query = $validated['query'];
        $queryType = $validated['query_type'] ?? 'auto';

        Log::info('[SHOPIFY API] Query received', [
            'shop_domain' => $shopDomain,
            'query' => $query,
            'query_type' => $queryType
        ]);

        // Get integration for this shop
        $integration = ShopifyApiService::getIntegrationByShop($shopDomain);
        
        if (!$integration) {
            Log::warning('[SHOPIFY API] No integration found', ['shop_domain' => $shopDomain]);
            return response()->json([
                'success' => false,
                'error' => 'Shop not connected',
                'data' => null,
                'formatted_text' => null,
            ], 404);
        }

        $this->shopifyService->setIntegration($integration);

        try {
            // Auto-detect query type if not specified
            if ($queryType === 'auto') {
                $queryType = $this->detectQueryType($query);
            }

            $data = null;
            $formattedText = null;

            switch ($queryType) {
                case 'products':
                    $data = $this->handleProductQuery($query);
                    $formattedText = $this->shopifyService->formatForAI($data, 'products');
                    break;

                case 'order':
                    $data = $this->handleOrderQuery($query);
                    $formattedText = $this->shopifyService->formatForAI($data, 'order');
                    break;

                case 'shop_info':
                    $data = $this->shopifyService->getShopInfo();
                    $formattedText = $this->shopifyService->formatForAI($data, 'shop_info');
                    break;

                default:
                    // Try to search all
                    $data = $this->shopifyService->searchAll($query);
                    $formattedText = $this->formatMixedResults($data);
                    break;
            }

            Log::info('[SHOPIFY API] Query successful', [
                'shop_domain' => $shopDomain,
                'query_type' => $queryType,
                'has_data' => !empty($data)
            ]);

            return response()->json([
                'success' => true,
                'query_type' => $queryType,
                'data' => $data,
                'formatted_text' => $formattedText,
            ]);

        } catch (\Exception $e) {
            Log::error('[SHOPIFY API] Query failed', [
                'shop_domain' => $shopDomain,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch data from Shopify',
                'data' => null,
                'formatted_text' => null,
            ], 500);
        }
    }

    /**
     * Detect query type from user message
     */
    protected function detectQueryType(string $query): string
    {
        $query = strtolower($query);

        // Order tracking patterns
        if (preg_match('/\b(order|tracking|track|shipment|delivery|where is my|order number|#\d+)\b/', $query)) {
            return 'order';
        }

        // Shop info patterns
        if (preg_match('/\b(store|shop|contact|location|address|phone|email|about you|who are you)\b/', $query)) {
            return 'shop_info';
        }

        // Product patterns (default)
        if (preg_match('/\b(product|item|sell|buy|price|cost|available|stock|inventory)\b/', $query)) {
            return 'products';
        }

        // Default to products
        return 'products';
    }

    /**
     * Handle product-related queries
     */
    protected function handleProductQuery(string $query)
    {
        // Extract search keywords (remove common words)
        $keywords = preg_replace('/\b(what|do|you|have|sell|any|products?|items?|looking|for|show|me)\b/i', '', $query);
        $keywords = trim($keywords);

        if (empty($keywords) || strlen($keywords) < 3) {
            // General product query - get all products
            return $this->shopifyService->getAllProducts(10);
        } else {
            // Specific product search
            return $this->shopifyService->searchProducts($keywords, 5);
        }
    }

    /**
     * Handle order-related queries
     */
    protected function handleOrderQuery(string $query)
    {
        // Extract order number
        if (preg_match('/#?(\d+)/', $query, $matches)) {
            $orderNumber = $matches[1];
            return $this->shopifyService->searchOrder($orderNumber);
        }

        // Extract email
        if (preg_match('/\b[\w\.-]+@[\w\.-]+\.\w{2,}\b/', $query, $matches)) {
            $email = $matches[0];
            $orders = $this->shopifyService->getCustomerOrders($email, 3);
            return !empty($orders) ? $orders[0] : null; // Return most recent order
        }

        return null;
    }

    /**
     * Format mixed search results
     */
    protected function formatMixedResults(array $data): string
    {
        $formatted = "";

        if (!empty($data['shop_info'])) {
            $formatted .= $this->shopifyService->formatForAI($data['shop_info'], 'shop_info') . "\n\n";
        }

        if (!empty($data['products'])) {
            $formatted .= $this->shopifyService->formatForAI($data['products'], 'products');
        }

        return $formatted ?: "No relevant information found.";
    }

    /**
     * Get shop information
     * GET /api/shopify/shop/{shop_domain}
     */
    public function getShopInfo(string $shopDomain)
    {
        $integration = ShopifyApiService::getIntegrationByShop($shopDomain);
        
        if (!$integration) {
            return response()->json([
                'success' => false,
                'error' => 'Shop not connected',
            ], 404);
        }

        $this->shopifyService->setIntegration($integration);
        $data = $this->shopifyService->getShopInfo();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get shop domain for organization
     * GET /api/organizations/{org_slug}/shopify-domain
     */
    public function getOrgShopDomain(string $orgSlug)
    {
        $organization = \App\Models\Organization::where('slug', $orgSlug)->first();
        
        if (!$organization) {
            return response()->json([
                'success' => false,
                'error' => 'Organization not found',
            ], 404);
        }

        $integration = $organization->integrations()
            ->where('provider', 'shopify')
            ->where('active', true)
            ->first();
        
        if (!$integration) {
            return response()->json([
                'success' => false,
                'shop_domain' => null,
                'message' => 'No Shopify integration found',
            ]);
        }

        return response()->json([
            'success' => true,
            'shop_domain' => $integration->shop,
        ]);
    }

    /**
     * Health check endpoint
     * GET /api/shopify/health
     */
    public function health()
    {
        return response()->json([
            'success' => true,
            'message' => 'Shopify API service is running',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
