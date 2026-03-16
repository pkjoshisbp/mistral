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
            'shop_domain'    => 'required|string',
            'query'          => 'required|string',
            'query_type'     => 'sometimes|string|in:products,order,shop_info,auto',
            'customer_email' => 'sometimes|nullable|email',
        ]);

        $shopDomain    = $validated['shop_domain'];
        $query         = $validated['query'];
        $queryType     = $validated['query_type'] ?? 'auto';
        $customerEmail = $validated['customer_email'] ?? null;

        Log::info('[SHOPIFY API] Query received', [
            'shop_domain'    => $shopDomain,
            'query'          => $query,
            'query_type'     => $queryType,
            'has_customer'   => $customerEmail !== null,
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
                    $productResult = $this->handleProductQuery($query);
                    $data = $productResult['results'] ?? $productResult;
                    $specificMatch = $productResult['specific_match'] ?? true;
                    $formattedText = $this->shopifyService->formatForAI($data, 'products');
                    break;

                case 'order':
                    $data = $this->handleOrderQuery($query);
                    // If no order found by ID/name in query, try customer email if provided (logged-in Shopify customer)
                    if (!$data && $customerEmail) {
                        Log::info('[SHOPIFY API] No order in query text — trying customer email lookup', [
                            'customer_email' => $customerEmail,
                        ]);
                        $customerOrders = $this->shopifyService->getCustomerOrders($customerEmail, 3);
                        if (!empty($customerOrders)) {
                            $data = $customerOrders[0]; // Most recent order
                        }
                    }
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
                'specific_match' => $specificMatch ?? true,
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
     * Direct PHP call — same logic as query() but returns a plain array instead of a JsonResponse.
     * Use this from within the application to avoid an Apache/HTTP self-loop.
     */
    public function queryDirect(string $shopDomain, string $query, ?string $customerEmail = null): array
    {
        $integration = ShopifyApiService::getIntegrationByShop($shopDomain);
        if (!$integration) {
            Log::warning('[SHOPIFY DIRECT] No integration found', ['shop_domain' => $shopDomain]);
            return ['success' => false, 'error' => 'Shop not connected', 'data' => null, 'formatted_text' => null, 'query_type' => 'auto', 'specific_match' => true];
        }

        $this->shopifyService->setIntegration($integration);

        try {
            $queryType  = $this->detectQueryType($query);
            $data       = null;
            $formattedText = null;
            $specificMatch = true;

            switch ($queryType) {
                case 'products':
                    $productResult = $this->handleProductQuery($query);
                    $data          = $productResult['results'] ?? $productResult;
                    $specificMatch = $productResult['specific_match'] ?? true;
                    $formattedText = $this->shopifyService->formatForAI($data, 'products');
                    break;

                case 'order':
                    $data = $this->handleOrderQuery($query);
                    if (!$data && $customerEmail) {
                        Log::info('[SHOPIFY DIRECT] No order in query — trying customer email', ['customer_email' => $customerEmail]);
                        $customerOrders = $this->shopifyService->getCustomerOrders($customerEmail, 3);
                        if (!empty($customerOrders)) {
                            $data = $customerOrders[0];
                        }
                    }
                    $formattedText = $data
                        ? $this->shopifyService->formatForAI($data, 'order')
                        : 'No order found with that order number or email.';
                    break;

                case 'shop_info':
                    $data          = $this->shopifyService->getShopInfo();
                    $formattedText = $this->shopifyService->formatForAI($data, 'shop_info');
                    break;

                default:
                    $data          = $this->shopifyService->searchAll($query);
                    $formattedText = $this->formatMixedResults($data);
                    break;
            }

            return [
                'success'        => true,
                'query_type'     => $queryType,
                'data'           => $data,
                'formatted_text' => $formattedText,
                'specific_match' => $specificMatch,
            ];
        } catch (\Exception $e) {
            Log::error('[SHOPIFY DIRECT] Query failed', ['shop_domain' => $shopDomain, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'Failed to fetch data from Shopify', 'data' => null, 'formatted_text' => null, 'query_type' => 'auto', 'specific_match' => true];
        }
    }

    /**
     * Detect query type from user message
     */
    protected function detectQueryType(string $query): string
    {
        $original = $query;
        $query = strtolower($query);

        // Order tracking patterns
        if (preg_match('/\b(order|tracking|track|shipment|delivery|where is my|order number|#\d+)\b/', $query)) {
            return 'order';
        }

        // Alphanumeric order number patterns (e.g. SPF2606, DR-1023, #1001, ORD-2024-001)
        // These look like order references even without the word "order" — check on the original (case-preserved)
        if (preg_match('/\b[A-Z]{1,6}-?\d{3,}\b/i', $original) || preg_match('/#\d{3,}/', $original)) {
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

        // Normalize spec-format separators before LLM extraction
        // e.g. "JACKET,APPRON: SIZE 29 IN" → "JACKET APPRON SIZE 29 IN"
        $normalizedQuery = preg_replace('/[,;:]+/', ' ', $query);
        $normalizedQuery = preg_replace('/\s+/', ' ', $normalizedQuery);
        $normalizedQuery = trim($normalizedQuery);

        $specificReference = $this->extractSpecificProductReference($normalizedQuery);
        if (!empty($specificReference)) {
            Log::info('[SHOPIFY] Specific product reference search', [
                'original' => $original,
                'reference' => $specificReference,
            ]);

            $referenceResults = $this->shopifyService->searchProducts($specificReference, 10);
            $referenceMaxScore = $this->shopifyService->getLastSearchMaxScore();

            if (!empty($referenceResults) && $this->isConfidentSpecificProductMatch($specificReference, $referenceResults, $referenceMaxScore)) {
                return ['results' => $referenceResults, 'specific_match' => true];
            }
        }
        
        // Use LLM to extract product keyword(s) from natural language
        $keywords = $this->extractProductKeyword($normalizedQuery);

        if (!empty($keywords)) {
            Log::info('[SHOPIFY] Specific product search', ['original' => $original, 'keywords' => $keywords]);
            $results = $this->shopifyService->searchProducts($keywords, 10);
            $maxScore = $this->shopifyService->getLastSearchMaxScore();

            if (!empty($results) && $this->isConfidentSpecificProductMatch((string) $keywords, $results, $maxScore)) {
                return ['results' => $results, 'specific_match' => true];
            }

            $fallbackKeywords = $this->fallbackKeywordExtraction($normalizedQuery);
            if (!empty($fallbackKeywords) && strtolower($fallbackKeywords) !== strtolower($keywords)) {
                Log::info('[SHOPIFY] Product search fallback keywords', [
                    'original' => $original,
                    'primary_keywords' => $keywords,
                    'fallback_keywords' => $fallbackKeywords,
                ]);

                $fallbackResults = $this->shopifyService->searchProducts($fallbackKeywords, 10);
                $fallbackMaxScore = $this->shopifyService->getLastSearchMaxScore();
                if (!empty($fallbackResults) && $this->isConfidentSpecificProductMatch((string) $fallbackKeywords, $fallbackResults, $fallbackMaxScore)) {
                    return ['results' => $fallbackResults, 'specific_match' => true];
                }
            }

            // Last resort: extract English-looking words embedded in a foreign-language query.
            // e.g. "say lagi ada kebutuhan apron untuk lab kimia" contains "apron" and "lab".
            $embeddedEnglish = $this->extractEmbeddedEnglishWords($original);
            if (!empty($embeddedEnglish)
                && strtolower($embeddedEnglish) !== strtolower((string) $keywords)
                && strtolower($embeddedEnglish) !== strtolower((string) $fallbackKeywords)
            ) {
                Log::info('[SHOPIFY] Embedded English word search', [
                    'original' => $original,
                    'embedded' => $embeddedEnglish,
                ]);
                $embeddedResults = $this->shopifyService->searchProducts($embeddedEnglish, 10);
                $embeddedMaxScore = $this->shopifyService->getLastSearchMaxScore();
                if (!empty($embeddedResults) && $this->isConfidentSpecificProductMatch((string) $embeddedEnglish, $embeddedResults, $embeddedMaxScore)) {
                    return ['results' => $embeddedResults, 'specific_match' => true];
                }
            }
        }

        Log::info('[SHOPIFY] General product query fallback - no specific match', [
            'original' => $original,
            'keywords' => $keywords ?: 'none',
        ]);

        // Return general catalog but flag it as no specific match so AI can ask for clarification
        return ['results' => $this->shopifyService->getAllProducts(10), 'specific_match' => false];
    }

    protected function extractSpecificProductReference(string $query): ?string
    {
        $candidate = trim($query);
        if ($candidate === '') {
            return null;
        }

        $candidate = preg_replace('/^(?:do\s+you\s+have|can\s+you\s+check|can\s+you\s+tell\s+me|have\s+in\s+stock|is|are|what\s+is\s+the\s+price\s+of|what\s+is\s+price\s+of|price\s+for|tell\s+me\s+about|show\s+me|looking\s+for)\b[\s\-:]+/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(?:available|availability|in\s+stock|stock|price|pricing|cost|details|detail|please|currently|now)\b/i', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/[?]+$/', '', $candidate) ?? $candidate;
        $candidate = trim((string) preg_replace('/\s+/', ' ', $candidate), " -:\t\n\r\0\x0B");

        $tokens = $this->extractSearchableTokens($candidate);
        if (count($tokens) < 4) {
            return null;
        }

        return implode(' ', array_slice($tokens, 0, 12));
    }

    protected function isConfidentSpecificProductMatch(string $query, array $results, int $maxScore): bool
    {
        if (empty($results)) {
            return false;
        }

        $queryTokens = $this->extractSearchableTokens($query);
        if (empty($queryTokens)) {
            return false;
        }

        $topResult = $results[0] ?? [];
        $titleTokens = $this->extractSearchableTokens((string) ($topResult['title'] ?? ''));
        $matchedTokenCount = count(array_intersect($queryTokens, $titleTokens));
        $requiredMatches = min(4, max(2, (int) ceil(count($queryTokens) * 0.45)));

        if ($matchedTokenCount >= $requiredMatches) {
            return true;
        }

        return $maxScore >= 12;
    }

    /**
     * Use LLM to extract product name/keyword from natural language query
     */
    /**
     * Detect the likely language of a short query string.
     * Uses only regex pattern matching — zero latency, no API call.
     * Returns a BCP-47 language tag or 'en' as default.
     */
    protected function detectLanguage(string $text): string
    {
        $lower = strtolower($text);

        // Indonesian / Malay (common function words and product-related words)
        if (preg_match('/\b(ada|untuk|dengan|adalah|yang|tidak|ini|itu|bisa|dari|saya|kami|lagi|mau|atau|dan|dalam|kebutuhan|butuh|cari|harga|stok|barang|produk|pesan|tersedia)\b/', $lower)) {
            return 'id'; // Indonesian
        }

        // Spanish / Portuguese
        if (preg_match('/\b(necesito|tengo|busco|precio|comprar|disponible|producto|quiero|hola|gracias|necesita|tener|tiene)\b/', $lower)) {
            return 'es';
        }

        // Arabic script
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text)) {
            return 'ar';
        }

        // Chinese characters
        if (preg_match('/[\x{4E00}-\x{9FFF}]/u', $text)) {
            return 'zh';
        }

        return 'en';
    }

    protected function extractProductKeyword(string $query): ?string
    {
        try {
            $detectedLang = $this->detectLanguage($query);
            $langHint = $detectedLang !== 'en'
                ? "NOTE: The query appears to be in language code '{$detectedLang}'. Translate the product need to English.\n\n"
                : '';

            $prompt = "You are extracting a product type from a customer query. The query may be in ANY language.\n\n{$langHint}Rules:\n- Extract ONLY the product type/category in English\n- If query is in another language (Indonesian, Spanish, etc.), translate the product need to English\n- Ignore size, color, material, and specification details\n- Ignore price/quality adjectives like 'cheapest', 'best', 'lowest'\n- Return just 1-3 English words\n- Return [empty] if no specific product is mentioned\n\nExamples:\n\"do you have snowboard in stock?\" → snowboard\n\"say lagi ada kebutuhan apron untuk lab kimia\" → lab apron\n\"JACKET APPRON SIZE 29 IN X 35 IN COLOR BLACK FOR CHEMICAL LABORATORY\" → lab jacket\n\"necesito guantes de laboratorio\" → lab gloves\n\"what is the cheapest snowboard?\" → snowboard\n\"what is the lowest price for a gift card?\" → gift card\n\"show me your best ski wax products\" → ski wax\n\"what products do you sell?\" → [empty]\n\"tell me about featured items\" → [empty]\n\nQuery: \"$query\"\nProduct type:";
            
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

        if (preg_match('/\b(rule|rules|extract|extraction|type|query|customer|english|product\s+type|follow)\b/i', $result)) {
            return $this->fallbackKeywordExtraction($originalQuery);
        }

        $result = preg_replace('/\b(tell|about|show|give|share|details?|information|info|me|please|on|for|the|a|an|some|any|product|products|item|items|model|do|does|you|your|have|has|what|which|is|are|in|stock|available|looking|find|search)\b/i', ' ', $result);
        $result = preg_replace('/\s+/', ' ', (string) $result);
        $result = trim((string) $result);

        if ($result === '' || in_array($result, ['featured', 'products', 'items', 'catalog'], true)) {
            return $this->fallbackKeywordExtraction($originalQuery);
        }

        $originalTokens = $this->extractSearchableTokens($originalQuery);
        $resultTokens = $this->extractSearchableTokens($result);
        if (empty(array_intersect($originalTokens, $resultTokens))) {
            return $this->fallbackKeywordExtraction($originalQuery);
        }

        return $result;
    }

    /**
     * Extract English words embedded inside a non-English query.
     * Useful for multilingual queries like "ada kebutuhan apron untuk lab kimia"
     * where "apron" and "lab" are English product keywords mixed into Indonesian.
     * Only picks ASCII-alpha tokens >= 4 chars that are not common stop words.
     */
    protected function extractEmbeddedEnglishWords(string $query): ?string
    {
        $stopWords = [
            'tell','show','have','does','what','which','this','that','they',
            'with','from','your','will','also','more','need','want','like',
            'some','into','been','were','when','then','than','such','each',
        ];

        $tokens = preg_split('/[\s,;:]+/', strtolower($query));
        $english = [];
        foreach ($tokens as $token) {
            $token = preg_replace('/[^a-z]/', '', $token);
            if (strlen($token) >= 4
                && preg_match('/^[a-z]+$/', $token)     // pure ASCII letters = likely English
                && !in_array($token, $stopWords, true)
                && !preg_match('/^(yang|yang|untuk|lagi|kimia|dengan|atau|saya|kami|ada|kebutuhan|dari|pada|tidak|bisa|juga|hanya|sama|oleh|atas|bawah|depan|belakang)$/', $token) // known Indonesian stop words
            ) {
                $english[] = $token;
            }
        }

        if (empty($english)) {
            return null;
        }

        // Take up to 2 most distinctive (longest) tokens as product keywords
        usort($english, fn($a, $b) => strlen($b) - strlen($a));
        $best = array_slice(array_unique($english), 0, 2);
        $result = implode(' ', $best);
        return strlen($result) >= 3 ? $result : null;
    }

    /**
     * Fallback keyword extraction using regex (if LLM fails)
     */
    protected function fallbackKeywordExtraction(string $query): ?string
    {
        // Remove common question words, articles, and price/quality adjectives
        $keywords = preg_replace('/\b(what|which|do|does|you|your|have|has|sell|selling|any|all|products?|items?|looking|for|show|me|my|the|a|an|available|current|can|i|see|get|list|in|stock|and|is|what|price|lowest|highest|cost|cheapest|most expensive|best|worst|featured|tell|about|details?|information|info|please|find|search|model)\b/i', '', $query);
        $keywords = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $keywords);
        $keywords = trim((string) preg_replace('/\s+/', ' ', $keywords));

        $words = $this->extractSearchableTokens($keywords);
        $words = array_slice($words, 0, 8);
        $keywords = implode(' ', $words);

        return strlen($keywords) >= 3 ? $keywords : null;
    }

    protected function extractSearchableTokens(string $text): array
    {
        $normalized = strtolower(trim($text));
        $normalized = preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $normalized);
        $normalized = trim((string) preg_replace('/\s+/', ' ', $normalized));
        if ($normalized === '') {
            return [];
        }

        $stopWords = [
            'tell', 'about', 'show', 'me', 'please', 'the', 'a', 'an', 'do', 'does',
            'you', 'your', 'have', 'has', 'in', 'stock', 'available', 'product', 'products',
            'item', 'items', 'for', 'with', 'and', 'or', 'to', 'of', 'info', 'information',
            'details', 'detail', 'model', 'looking', 'find', 'search', 'price', 'pricing',
            'cost', 'current', 'currently', 'check', 'can', 'is', 'are', 'what', 'how',
            'much', 'from', 'that', 'this', 'these', 'those', 'now', 'sku', 'code'
        ];

        return array_values(array_filter(explode(' ', $normalized), function ($token) use ($stopWords) {
            return $token !== ''
                && strlen($token) >= 2
                && !in_array($token, $stopWords, true)
                && $token !== '-';
        }));
    }

    /**
     * Handle order-related queries
     */
    protected function handleOrderQuery(string $query)
    {
        $orderIdentifier = null;

        // Match alphanumeric order IDs first (e.g. SPF2606, DR-1023, ABC123)
        if (preg_match('/\b([A-Z]{1,5}[-]?\d{3,})\b/i', $query, $matches)) {
            $orderIdentifier = strtoupper($matches[1]);
        } elseif (preg_match('/#(\d{3,})/', $query, $matches)) {
            // Explicit # prefix (e.g. #1001)
            $orderIdentifier = $matches[1];
        } elseif (preg_match('/\b(\d{4,})\b/', $query, $matches)) {
            // Bare long number (e.g. 10045)
            $orderIdentifier = $matches[1];
        }

        if ($orderIdentifier) {
            $result = $this->shopifyService->searchOrder($orderIdentifier);
            if ($result) {
                // Attach the queried identifier so formatForAI can reference it
                if (isset($result['error'])) {
                    $result['queried_identifier'] = $orderIdentifier;
                }
                return $result;
            }
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
