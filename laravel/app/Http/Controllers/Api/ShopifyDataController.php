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
                    $formattedText = $data ? $this->shopifyService->formatForAI($data, 'order') : 'No order found with that order number or email.';
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
        $original = $query;
        
        // Use LLM to extract product keyword(s) from natural language
        $keywords = $this->extractProductKeyword($query);

        if (!empty($keywords)) {
            Log::info('[SHOPIFY] Specific product search', ['original' => $original, 'keywords' => $keywords]);
            $results = $this->shopifyService->searchProducts($keywords, 10);
            if (!empty($results)) {
                return $results;
            }

            $fallbackKeywords = $this->fallbackKeywordExtraction($query);
            if (!empty($fallbackKeywords) && strtolower($fallbackKeywords) !== strtolower($keywords)) {
                Log::info('[SHOPIFY] Product search fallback keywords', [
                    'original' => $original,
                    'primary_keywords' => $keywords,
                    'fallback_keywords' => $fallbackKeywords,
                ]);

                $fallbackResults = $this->shopifyService->searchProducts($fallbackKeywords, 10);
                if (!empty($fallbackResults)) {
                    return $fallbackResults;
                }
            }
        }

        Log::info('[SHOPIFY] General product query fallback', [
            'original' => $original,
            'keywords' => $keywords ?: 'none',
        ]);

        return $this->shopifyService->getAllProducts(10);
    }

    /**
     * Use LLM to extract product name/keyword from natural language query
     */
    protected function extractProductKeyword(string $query): ?string
    {
        try {
            $prompt = "Extract ONLY the product name or category being asked about. Ignore price/quality adjectives like 'cheapest', 'best', 'lowest'. Return just the product type in 1-3 words.\n\nExamples:\n\"do you have snowboard in stock?\" → snowboard\n\"what is the cheapest snowboard?\" → snowboard\n\"what is the lowest price for a gift card?\" → gift card\n\"show me your best ski wax products\" → ski wax\n\"looking for hydrogen model\" → hydrogen\n\"what products do you sell?\" → [empty]\n\"tell me about featured items\" → [empty]\n\nQuery: \"$query\"\nProduct type:";
            
            $response = \Illuminate\Support\Facades\Http::timeout(5)->post(config('services.ai_agent.url') . '/extract', [
                'prompt' => $prompt,
                'max_tokens' => 20
            ]);
            
            if ($response->successful()) {
                $result = trim($response->json('result', ''));

                $result = $this->normalizeProductKeyword($result, $query);
                if ($result === null) {
                    return null;
                }
                
                Log::info('[SHOPIFY] LLM keyword extraction', [
                    'query' => $query,
                    'extracted' => $result
                ]);
                
                return $result;
            }
        } catch (\Exception $e) {
            Log::error('[SHOPIFY] LLM extraction failed', ['error' => $e->getMessage()]);
            // Fallback to simple regex if LLM fails
            return $this->fallbackKeywordExtraction($query);
        }

        return $this->fallbackKeywordExtraction($query);
    }

    protected function normalizeProductKeyword(string $candidate, string $originalQuery): ?string
    {
        $result = strtolower(trim($candidate));
        $result = preg_replace('/[^a-z0-9\s-]/i', ' ', $result);
        $result = preg_replace('/\s+/', ' ', (string) $result);
        $result = trim((string) $result);

        if ($result === '' || stripos($result, '[empty]') !== false || strlen($result) > 50) {
            return $this->fallbackKeywordExtraction($originalQuery);
        }

        $result = preg_replace('/\b(tell|about|show|give|share|details?|information|info|me|please|on|for|the|a|an|some|any|product|products|item|items|model|do|does|you|your|have|has|what|which|is|are|in|stock|available|looking|find|search)\b/i', ' ', $result);
        $result = preg_replace('/\s+/', ' ', (string) $result);
        $result = trim((string) $result);

        if ($result === '' || in_array($result, ['featured', 'products', 'items', 'catalog'], true)) {
            return $this->fallbackKeywordExtraction($originalQuery);
        }

        return $result;
    }

    /**
     * Fallback keyword extraction using regex (if LLM fails)
     */
    protected function fallbackKeywordExtraction(string $query): ?string
    {
        // Remove common question words, articles, and price/quality adjectives
        $keywords = preg_replace('/\b(what|which|do|does|you|your|have|has|sell|selling|any|all|products?|items?|looking|for|show|me|my|the|a|an|available|current|can|i|see|get|list|in|stock|and|is|what|price|lowest|highest|cost|cheapest|most expensive|best|worst|featured|tell|about|details?|information|info|please|find|search|model)\b/i', '', $query);
        $keywords = preg_replace('/[^\w\s-]/', '', $keywords);
        $keywords = trim($keywords);
        
        // Only return first 1-2 words
        $words = explode(' ', $keywords);
        $words = array_filter($words); // Remove empty elements
        $words = array_slice($words, 0, 2);
        $keywords = implode(' ', $words);
        
        return strlen($keywords) >= 3 ? $keywords : null;
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
