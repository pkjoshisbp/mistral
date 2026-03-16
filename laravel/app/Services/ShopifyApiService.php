<?php

namespace App\Services;

use App\Models\Integration;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ShopifyApiService
{
    protected $integration;
    protected $shopDomain;
    protected $accessToken;
    protected $apiVersion = '2025-01';
    protected int $lastSearchMaxScore = 0;

    public function __construct(?Integration $integration = null)
    {
        if ($integration) {
            $this->integration = $integration;
            $this->shopDomain = $integration->shop;
            $this->accessToken = $integration->access_token;
        }
    }

    /**
     * Set integration dynamically
     */
    public function setIntegration(Integration $integration)
    {
        $this->integration = $integration;
        $this->shopDomain = $integration->shop;
        $this->accessToken = $integration->access_token;
        return $this;
    }

    /**
     * Get shop info for integration by shop domain
     */
    public static function getIntegrationByShop(string $shopDomain): ?Integration
    {
        return Integration::where('shop', $shopDomain)
            ->where('provider', 'shopify')
            ->where('active', true)
            ->first();
    }

    /**
     * Make API request to Shopify
     */
    protected function makeRequest(string $endpoint, array $params = [])
    {
        if (!$this->shopDomain || !$this->accessToken) {
            Log::error('[SHOPIFY API] Missing credentials', [
                'shop_domain' => $this->shopDomain,
                'has_token' => !empty($this->accessToken)
            ]);
            return null;
        }

        $url = "https://{$this->shopDomain}/admin/api/{$this->apiVersion}/{$endpoint}";

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $this->accessToken,
                'Content-Type' => 'application/json',
            ])->get($url, $params);

            if ($response->successful()) {
                return $response->json();
            }

            // Handle 403 - Protected Customer Data
            if ($response->status() === 403 && str_contains($response->body(), 'protected customer data')) {
                Log::warning('[SHOPIFY API] Protected customer data access denied', [
                    'endpoint' => $endpoint,
                    'message' => 'App needs Shopify approval to access orders'
                ]);
                return ['error' => 'protected_data_access_denied', 'requires_approval' => true];
            }

            Log::error('[SHOPIFY API] Request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('[SHOPIFY API] Exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Search products by keyword (client-side filtering since Shopify API doesn't have fuzzy search)
     */
    public function searchProducts(string $query, int $limit = 10)
    {
        // Get all products (cached) and filter client-side
        $allProducts = $this->getAllProducts(250); // Get more to search through
        
        \Log::info('[SHOPIFY] searchProducts called', [
            'query' => $query,
            'all_products_count' => count($allProducts),
            'limit' => $limit
        ]);
        
        if (empty($allProducts)) {
            \Log::warning('[SHOPIFY] No products from getAllProducts()');
            return [];
        }
        
        $searchTerm = strtolower(trim($query));
        $searchTerm = preg_replace('/[^a-z0-9\s-]/i', ' ', $searchTerm);
        $searchTerm = preg_replace('/\s+/', ' ', (string) $searchTerm);
        $searchTerm = trim((string) $searchTerm);

        $stopWords = [
            'tell', 'about', 'show', 'me', 'please', 'the', 'a', 'an', 'do', 'does',
            'you', 'your', 'have', 'has', 'in', 'stock', 'available', 'product', 'products',
            'item', 'items', 'for', 'with', 'and', 'or', 'to', 'of', 'info', 'information',
            'details', 'model', 'looking', 'find', 'search'
        ];

        $rawTokens = array_values(array_filter(explode(' ', $searchTerm)));
        $tokens = array_values(array_filter($rawTokens, function ($token) use ($stopWords) {
            return strlen($token) >= 3 && !in_array($token, $stopWords, true);
        }));

        if (empty($tokens) && $searchTerm !== '') {
            $tokens = [$searchTerm];
        }

        $this->lastSearchMaxScore = 0;
        $scored = [];

        foreach ($allProducts as $product) {
            $titleLower = strtolower((string) ($product['title'] ?? ''));
            $descLower = strtolower((string) ($product['description'] ?? ''));
            $haystack = $titleLower . ' ' . $descLower;
            $productSkus = array_values(array_filter(array_map(static function ($sku) {
                return strtolower(trim((string) $sku));
            }, is_array($product['skus'] ?? null) ? $product['skus'] : [])));

            $score = 0;
            if ($searchTerm !== '' && (str_contains($titleLower, $searchTerm) || str_contains($descLower, $searchTerm))) {
                $score += 8;
            }

            foreach ($productSkus as $sku) {
                if ($sku === '') {
                    continue;
                }

                if ($searchTerm !== '' && ($searchTerm === $sku || str_contains($searchTerm, $sku) || str_contains($sku, $searchTerm))) {
                    $score += 20;
                }

                foreach ($tokens as $token) {
                    if ($token === $sku) {
                        $score += 20;
                    } elseif (strlen($token) >= 3 && (str_contains($sku, $token) || str_contains($token, $sku))) {
                        $score += 8;
                    }
                }
            }

            foreach ($tokens as $token) {
                if (str_contains($titleLower, $token)) {
                    $score += 5;
                } elseif (str_contains($descLower, $token)) {
                    $score += 2;
                }

                if (str_ends_with($token, 's') && strlen($token) > 3) {
                    $singular = substr($token, 0, -1);
                    if ($singular !== '' && str_contains($haystack, $singular)) {
                        $score += 1;
                    }
                }
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'product' => $product];
                if ($score > $this->lastSearchMaxScore) {
                    $this->lastSearchMaxScore = $score;
                }
            }
        }

        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        $matches = array_map(function ($item) {
            return $item['product'];
        }, array_slice($scored, 0, $limit));
        
        \Log::info('[SHOPIFY] searchProducts results', [
            'query' => $query,
            'tokens' => $tokens,
            'matches_found' => count($matches),
            'max_score' => $this->lastSearchMaxScore,
        ]);
        
        return $matches;
    }

    /**
     * Return the max relevance score from the last searchProducts() call.
     * Score >= 5 means at least one title matched a keyword.
     */
    public function getLastSearchMaxScore(): int
    {
        return $this->lastSearchMaxScore;
    }

    /**
     * Get product by ID
     */
    public function getProduct(int $productId)
    {
        $cacheKey = "shopify_product_{$this->shopDomain}_{$productId}";
        
        return Cache::remember($cacheKey, 600, function () use ($productId) {
            $response = $this->makeRequest("products/{$productId}.json");

            if (!$response || !isset($response['product'])) {
                return null;
            }

            $product = $response['product'];
            $variantSummary = $this->summarizeProductVariants($product['variants'] ?? []);
            return [
                'id' => $product['id'],
                'title' => $product['title'],
                'description' => strip_tags($product['body_html'] ?? ''),
                'price' => $variantSummary['primary_price'] ?? ($product['variants'][0]['price'] ?? 'N/A'),
                'currency' => $variantSummary['currency'] ?? ($product['variants'][0]['currency_code'] ?? 'USD'),
                'available' => $variantSummary['available'],
                'inventory' => $variantSummary['inventory'],
                'url' => "https://{$this->shopDomain}/products/{$product['handle']}",
                'image' => $product['image']['src'] ?? null,
                'variants' => $variantSummary['variants'],
            ];
        });
    }

    /**
     * Get all products (paginated)
     */
    public function getAllProducts(int $limit = 50)
    {
        $cacheKey = "shopify_all_products_{$this->shopDomain}_{$limit}";
        
        return Cache::remember($cacheKey, 600, function () use ($limit) {
            $response = $this->makeRequest('products.json', [
                'limit' => $limit,
                'status' => 'active'
            ]);

            if (!$response || !isset($response['products'])) {
                return [];
            }

            return array_map(function ($product) {
                $variantSummary = $this->summarizeProductVariants($product['variants'] ?? []);
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'description' => strip_tags($product['body_html'] ?? ''),
                    'price' => $variantSummary['primary_price'] ?? (($product['variants'][0]['price'] ?? 'N/A')),
                    'currency' => $variantSummary['currency'] ?? ($product['currency'] ?? 'USD'),
                    'inventory' => $variantSummary['inventory'],
                    'available' => $variantSummary['available'],
                    'url' => "https://{$this->shopDomain}/products/{$product['handle']}",
                    // Map all variant titles so size/option info is available for LLM context
                    'variants' => $variantSummary['variants'],
                    'skus' => $variantSummary['skus'],
                ];
            }, $response['products']);
        });
    }

    private function summarizeProductVariants(array $variants): array
    {
        $mapped = array_map(function ($variant) {
            $inventory = (int) ($variant['inventory_quantity'] ?? 0);
            $available = $inventory > 0;
            $sku = trim((string) ($variant['sku'] ?? ''));

            return [
                'id' => $variant['id'] ?? null,
                'title' => $variant['title'] ?? 'Default Title',
                'price' => $variant['price'] ?? 'N/A',
                'available' => $available,
                'inventory' => $inventory,
                'currency' => $variant['currency_code'] ?? null,
                'sku' => $sku !== '' ? $sku : null,
            ];
        }, $variants);

        $totalInventory = array_sum(array_map(function ($variant) {
            return (int) ($variant['inventory'] ?? 0);
        }, $mapped));

        $availableVariants = array_values(array_filter($mapped, function ($variant) {
            return !empty($variant['available']);
        }));

        $primaryVariant = $availableVariants[0] ?? ($mapped[0] ?? []);

        return [
            'available' => !empty($availableVariants),
            'inventory' => $totalInventory,
            'primary_price' => $primaryVariant['price'] ?? 'N/A',
            'currency' => $primaryVariant['currency'] ?? null,
            'skus' => array_values(array_unique(array_filter(array_map(static function ($variant) {
                return trim((string) ($variant['sku'] ?? ''));
            }, $mapped)))),
            'variants' => array_map(function ($variant) {
                unset($variant['currency']);
                return $variant;
            }, $mapped),
        ];
    }

    /**
     * Get order by order number or ID
     */
    public function searchOrder(string $orderIdentifier)
    {
        // Try by order name first (e.g., #1001)
        $orderName = str_replace('#', '', $orderIdentifier);
        
        $response = $this->makeRequest('orders.json', [
            'name' => "#{$orderName}",
            'limit' => 1,
            'status' => 'any'
        ]);

        // Check for protected data error
        if (is_array($response) && isset($response['error']) && $response['error'] === 'protected_data_access_denied') {
            return [
                'error' => 'requires_approval',
                'message' => 'Order details are not directly accessible through this chat. Advise the customer to: (1) check their order confirmation email for tracking information, (2) log into their account on our website to view order status, or (3) contact our support team with their order number for assistance.'
            ];
        }

        if ($response && isset($response['orders']) && count($response['orders']) > 0) {
            return $this->formatOrder($response['orders'][0]);
        }

        // Try by ID if numeric
        if (is_numeric($orderIdentifier)) {
            $response = $this->makeRequest("orders/{$orderIdentifier}.json");
            
            // Check for protected data error
            if (is_array($response) && isset($response['error']) && $response['error'] === 'protected_data_access_denied') {
                return [
                    'error' => 'requires_approval',
                    'message' => 'Order details are not directly accessible through this chat. Advise the customer to: (1) check their order confirmation email for tracking information, (2) log into their account on our website to view order status, or (3) contact our support team with their order number for assistance.'
                ];
            }
            
            if ($response && isset($response['order'])) {
                return $this->formatOrder($response['order']);
            }
        }

        return null;
    }

    /**
     * Get order by customer email
     */
    public function getCustomerOrders(string $email, int $limit = 5)
    {
        $response = $this->makeRequest('orders.json', [
            'email' => $email,
            'limit' => $limit,
            'status' => 'any'
        ]);

        if (!$response || !isset($response['orders'])) {
            return [];
        }

        return array_map(function ($order) {
            return $this->formatOrder($order);
        }, $response['orders']);
    }

    /**
     * Format order data
     */
    protected function formatOrder(array $order)
    {
        $fulfillments = $order['fulfillments'] ?? [];
        $trackingInfo = [];
        
        if (count($fulfillments) > 0) {
            $trackingInfo = [
                'tracking_number' => $fulfillments[0]['tracking_number'] ?? null,
                'tracking_url'     => $fulfillments[0]['tracking_url'] ?? null,
                'tracking_company' => $fulfillments[0]['tracking_company'] ?? null,
                'status'           => $fulfillments[0]['status'] ?? null,
                'shipped_at'       => $fulfillments[0]['created_at'] ?? null,
                'updated_at'       => $fulfillments[0]['updated_at'] ?? null,
            ];
        }

        return [
            'id' => $order['id'],
            'order_number' => $order['order_number'],
            'name' => $order['name'], // e.g., #1001
            'email' => $order['email'] ?? null,
            'created_at' => $order['created_at'],
            'financial_status' => $order['financial_status'], // paid, pending, refunded
            'fulfillment_status' => $order['fulfillment_status'], // fulfilled, partial, null
            'total_price' => $order['total_price'],
            'currency' => $order['currency'],
            'line_items' => array_map(function ($item) {
                return [
                    'title' => $item['title'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];
            }, $order['line_items'] ?? []),
            'shipping_address' => [
                'city' => $order['shipping_address']['city'] ?? null,
                'country' => $order['shipping_address']['country'] ?? null,
            ],
            'tracking' => $trackingInfo,
            'fulfilled_at' => count($fulfillments) > 0 ? ($fulfillments[0]['created_at'] ?? null) : null,
        ];
    }

    /**
     * Get shipping/tracking info for order
     */
    public function getOrderTracking(int $orderId)
    {
        $response = $this->makeRequest("orders/{$orderId}/fulfillments.json");

        if (!$response || !isset($response['fulfillments'])) {
            return null;
        }

        $fulfillments = $response['fulfillments'];
        if (empty($fulfillments)) {
            return null;
        }

        return array_map(function ($fulfillment) {
            return [
                'id' => $fulfillment['id'],
                'status' => $fulfillment['status'],
                'tracking_number' => $fulfillment['tracking_number'] ?? null,
                'tracking_url' => $fulfillment['tracking_url'] ?? null,
                'tracking_company' => $fulfillment['tracking_company'] ?? null,
                'created_at' => $fulfillment['created_at'],
                'updated_at' => $fulfillment['updated_at'],
            ];
        }, $fulfillments);
    }

    /**
     * Get shop information
     */
    public function getShopInfo()
    {
        $cacheKey = "shopify_shop_info_{$this->shopDomain}";
        
        return Cache::remember($cacheKey, 3600, function () {
            $response = $this->makeRequest('shop.json');

            if (!$response || !isset($response['shop'])) {
                return null;
            }

            $shop = $response['shop'];
            return [
                'name' => $shop['name'],
                'email' => $shop['email'],
                'domain' => $shop['domain'],
                'currency' => $shop['currency'],
                'phone' => $shop['phone'] ?? null,
                'address' => [
                    'address1' => $shop['address1'] ?? null,
                    'city' => $shop['city'] ?? null,
                    'province' => $shop['province'] ?? null,
                    'country' => $shop['country_name'] ?? null,
                    'zip' => $shop['zip'] ?? null,
                ],
            ];
        });
    }

    /**
     * Search all data types (products, orders, etc.) based on query
     */
    public function searchAll(string $query)
    {
        $results = [
            'products' => [],
            'shop_info' => null,
        ];

        // Search products
        $results['products'] = $this->searchProducts($query, 5);

        // Get shop info if query mentions store/shop
        if (preg_match('/\b(store|shop|business|company|contact|location)\b/i', $query)) {
            $results['shop_info'] = $this->getShopInfo();
        }

        return $results;
    }

    /**
     * Format data for AI context
     */
    public function formatForAI(array $data, string $dataType): string
    {
        switch ($dataType) {
            case 'products':
                if (empty($data)) {
                    return "No products found.";
                }
                $formatted = "Products:\n";
                foreach ($data as $product) {
                    $formatted .= "- {$product['title']}: {$product['currency']} {$product['price']}";
                    $formatted .= $product['available'] ? " (In stock: {$product['inventory']})" : " (Out of stock)";
                    $formatted .= "\n  Description: " . substr($product['description'], 0, 100) . "...\n";
                    $formatted .= "  URL: {$product['url']}\n";
                }
                return $formatted;

            case 'order':
                if (empty($data)) {
                    return "Order not found.";
                }

                if (array_is_list($data)) {
                    return $this->formatOrderHistoryForAI($data);
                }
                
                // Handle error cases
                if (isset($data['error']) && $data['error'] === 'requires_approval') {
                    $orderId = $data['queried_identifier'] ?? null;
                    $note = $orderId
                        ? "[Order lookup for '{$orderId}': " . $data['message'] . "]"
                        : '[' . $data['message'] . ']';
                    return $note;
                }
                
                $formatted = "Order {$data['name']}:\n";
                // Map Shopify fulfillment_status to plain English so the LLM does not
                // misinterpret "fulfilled" as "ready to ship" — it means already shipped.
                $fsRaw = strtolower(trim((string)($data['fulfillment_status'] ?? '')));
                $fsLabel = match($fsRaw) {
                    'fulfilled' => 'Shipped (Fulfilled)',
                    'partial'   => 'Partially Shipped',
                    'restocked' => 'Restocked / Returned',
                    default     => 'Not Yet Shipped',
                };
                $formatted .= "Fulfillment Status: {$fsLabel}\n";
                // Include shipped date from the first fulfillment if available
                if (!empty($data['tracking']['shipped_at'])) {
                    $formatted .= "Shipped On: {$data['tracking']['shipped_at']}\n";
                } elseif (!empty($data['tracking']['updated_at'])) {
                    $formatted .= "Last Updated: {$data['tracking']['updated_at']}\n";
                }
                $formatted .= "Payment: " . ucfirst($data['financial_status']) . "\n";
                $formatted .= "Total: {$data['currency']} {$data['total_price']}\n";
                
                if (!empty($data['tracking']['tracking_number'])) {
                    $trackingCompany = $data['tracking']['tracking_company'] ?? null;
                    if ($trackingCompany) {
                        $formatted .= "Carrier: {$trackingCompany}\n";
                    }
                    $formatted .= "Tracking Number: {$data['tracking']['tracking_number']}\n";
                    // Shopify shipment status: success=delivered, in_transit, out_for_delivery, etc.
                    if (!empty($data['tracking']['status'])) {
                        $deliveryStatusMap = [
                            'success'          => 'Delivered',
                            'in_transit'       => 'In Transit',
                            'out_for_delivery' => 'Out For Delivery',
                            'attempted_delivery' => 'Delivery Attempted',
                            'ready_for_pickup' => 'Ready For Pickup',
                            'confirmed'        => 'Shipping Confirmed',
                            'failure'          => 'Delivery Failed',
                        ];
                        $dStatus = strtolower(trim($data['tracking']['status']));
                        $dLabel  = $deliveryStatusMap[$dStatus] ?? ucwords(str_replace('_', ' ', $dStatus));
                        $formatted .= "Delivery Status: {$dLabel}\n";
                    }
                    if (!empty($data['tracking']['tracking_url'])) {
                        $formatted .= "Track at: {$data['tracking']['tracking_url']}\n";
                    }
                }
                
                $formatted .= "Items:\n";
                foreach ($data['line_items'] as $item) {
                    $formatted .= "- {$item['title']} (Qty: {$item['quantity']}) - {$data['currency']} {$item['price']}\n";
                }
                return $formatted;

            case 'shop_info':
                if (empty($data)) {
                    return "Shop information not available.";
                }
                $formatted = "Store Information:\n";
                $formatted .= "Name: {$data['name']}\n";
                if (!empty($data['email'])) {
                    $formatted .= "Email: {$data['email']}\n";
                }
                if (!empty($data['phone'])) {
                    $formatted .= "Phone: {$data['phone']}\n";
                }
                if (!empty($data['address']['address1'])) {
                    $formatted .= "Address: {$data['address']['address1']}, {$data['address']['city']}, {$data['address']['province']}, {$data['address']['country']} {$data['address']['zip']}\n";
                }
                return $formatted;

            default:
                return json_encode($data, JSON_PRETTY_PRINT);
        }
    }

    /**
     * Get theme colors from active theme
     */
    public function getThemeColors()
    {
        try {
            // First, get the active theme
            $themesResponse = $this->makeRequest('themes.json');
            
            if (!$themesResponse || !isset($themesResponse['themes'])) {
                return null;
            }

            // Find the active/published theme
            $activeTheme = collect($themesResponse['themes'])
                ->first(fn($theme) => $theme['role'] === 'main');

            if (!$activeTheme) {
                return null;
            }

            // Get theme assets to extract color settings
            $assetsResponse = $this->makeRequest("themes/{$activeTheme['id']}/assets.json");
            
            if (!$assetsResponse || !isset($assetsResponse['assets'])) {
                return null;
            }

            // Look for settings_data.json which contains theme color settings
            $settingsAsset = collect($assetsResponse['assets'])
                ->first(fn($asset) => $asset['key'] === 'config/settings_data.json');

            if (!$settingsAsset) {
                return null;
            }

            // Fetch the actual settings data
            $settingsResponse = $this->makeRequest(
                "themes/{$activeTheme['id']}/assets.json",
                ['asset[key]' => 'config/settings_data.json']
            );

            if (!$settingsResponse || !isset($settingsResponse['asset']['value'])) {
                return null;
            }

            $settingsData = json_decode($settingsResponse['asset']['value'], true);
            
            if (!$settingsData || !isset($settingsData['current'])) {
                return null;
            }

            // Extract color values from theme settings
            $colors = $settingsData['current']['colors'] ?? [];
            
            // Common Shopify theme color keys
            $primaryColor = $colors['button'] ?? 
                           $colors['accent'] ?? 
                           $colors['color_primary'] ?? 
                           $colors['primary'] ?? 
                           '#007bff';

            $textColor = $colors['text'] ?? 
                        $colors['color_text'] ?? 
                        $colors['body_text'] ?? 
                        '#333333';

            return [
                'primary_color' => $this->normalizeColor($primaryColor),
                'text_color' => $this->normalizeColor($textColor),
            ];

        } catch (\Exception $e) {
            Log::warning('[SHOPIFY] Failed to fetch theme colors', [
                'error' => $e->getMessage(),
                'shop' => $this->shopDomain
            ]);
            return null;
        }
    }

    /**
     * Normalize color format (handle rgba, rgb, hex)
     */
    private function normalizeColor($color)
    {
        // If it's already a hex color, return it
        if (preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            return $color;
        }

        // If it's rgb or rgba, try to convert (simplified)
        if (preg_match('/rgba?\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches)) {
            return sprintf('#%02x%02x%02x', $matches[1], $matches[2], $matches[3]);
        }

        // Default fallback
        return '#007bff';
    }

    public function buildStorefrontAddToCartUrl(array $product, ?string $variantHint = null, ?string $discountCode = null): ?string
    {
        $baseUrl = trim((string) ($product['url'] ?? ''));
        if ($baseUrl === '') {
            return null;
        }

        $variant = $this->resolvePreferredVariantForCart($product, $variantHint);
        $variantId = $variant['id'] ?? null;
        if (!$variantId) {
            return null;
        }

        $parts = parse_url($baseUrl);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $url = $parts['scheme'] . '://' . $parts['host'] . '/cart/' . $variantId . ':1';

        return $this->appendDiscountToStorefrontUrl($url, $discountCode);
    }

    public function resolvePreferredVariantForCart(array $product, ?string $variantHint = null): ?array
    {
        $variants = array_values(array_filter($product['variants'] ?? [], static function ($variant) {
            return is_array($variant) && !empty($variant['id']);
        }));

        if (empty($variants)) {
            return null;
        }

        $hint = trim(mb_strtolower((string) $variantHint));
        if ($hint !== '') {
            foreach ($variants as $variant) {
                $sku = mb_strtolower(trim((string) ($variant['sku'] ?? '')));
                if ($sku !== '' && (str_contains($hint, $sku) || preg_match('/\b' . preg_quote($sku, '/') . '\b/i', $hint))) {
                    return $variant;
                }

                $title = mb_strtolower(trim((string) ($variant['title'] ?? '')));
                if ($title !== '' && $title !== 'default title' && str_contains($hint, $title)) {
                    return $variant;
                }

                $titleTokens = array_values(array_filter(preg_split('/[^a-z0-9]+/i', $title) ?: [], static function ($token) {
                    return $token !== '' && $token !== 'default' && $token !== 'title';
                }));

                if (!empty($titleTokens)) {
                    $allTokensPresent = true;
                    foreach ($titleTokens as $token) {
                        if (!str_contains($hint, mb_strtolower($token))) {
                            $allTokensPresent = false;
                            break;
                        }
                    }

                    if ($allTokensPresent) {
                        return $variant;
                    }
                }

                $normalizedTitle = trim((string) preg_replace('/[^a-z0-9]+/i', ' ', $title));
                if ($normalizedTitle !== '' && str_contains($hint, $normalizedTitle)) {
                    return $variant;
                }
            }
        }

        foreach ($variants as $variant) {
            if (!empty($variant['available'])) {
                return $variant;
            }
        }

        return $variants[0];
    }

    public function buildStorefrontApplyDiscountUrl(string $storeUrlOrDomain, string $discountCode, string $redirectPath = '/cart'): ?string
    {
        $discountCode = trim($discountCode);
        if ($discountCode === '') {
            return null;
        }

        $baseUrl = $this->normalizeStorefrontBaseUrl($storeUrlOrDomain);
        if ($baseUrl === null) {
            return null;
        }

        $redirect = '/' . ltrim($redirectPath, '/');

        return rtrim($baseUrl, '/') . '/discount/' . rawurlencode($discountCode) . '?redirect=' . rawurlencode($redirect);
    }

    private function appendDiscountToStorefrontUrl(string $url, ?string $discountCode = null): string
    {
        $discountCode = trim((string) $discountCode);
        if ($discountCode === '') {
            return $url;
        }

        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'discount=' . rawurlencode($discountCode);
    }

    private function normalizeStorefrontBaseUrl(string $storeUrlOrDomain): ?string
    {
        $value = trim($storeUrlOrDomain);
        if ($value === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        $parts = parse_url($value);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        return $parts['scheme'] . '://' . $parts['host'];
    }

    private function formatOrderHistoryForAI(array $orders): string
    {
        $orders = array_values(array_filter($orders, static function ($order) {
            return is_array($order) && !empty($order['name']);
        }));

        if (empty($orders)) {
            return 'No previous purchases were found for this customer.';
        }

        $lines = ['Recent purchases:'];
        foreach (array_slice($orders, 0, 5) as $order) {
            $status = $this->deriveOrderHistoryStatus($order);
            $items = array_values(array_filter(array_map(static function ($item) {
                $title = trim((string) ($item['title'] ?? ''));
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                return $title !== '' ? $title . ' x' . $quantity : '';
            }, $order['line_items'] ?? [])));

            $line = '- ' . ($order['name'] ?? 'Order') . ' | ' . ($order['currency'] ?? 'USD') . ' ' . ($order['total_price'] ?? '0');
            if ($status !== '') {
                $line .= ' | ' . $status;
            }
            if (!empty($order['created_at'])) {
                $line .= ' | ' . $order['created_at'];
            }

            $lines[] = $line;
            if (!empty($items)) {
                $lines[] = '  Items: ' . implode(', ', array_slice($items, 0, 5));
            }
        }

        return implode("\n", $lines);
    }

    private function deriveOrderHistoryStatus(array $order): string
    {
        $trackingStatus = strtolower(trim((string) ($order['tracking']['status'] ?? '')));
        if ($trackingStatus !== '') {
            return match ($trackingStatus) {
                'success' => 'Delivered',
                'in_transit' => 'In Transit',
                'out_for_delivery' => 'Out For Delivery',
                'attempted_delivery' => 'Delivery Attempted',
                'ready_for_pickup' => 'Ready For Pickup',
                'confirmed' => 'Shipping Confirmed',
                'failure' => 'Delivery Failed',
                default => ucwords(str_replace('_', ' ', $trackingStatus)),
            };
        }

        $fulfillmentStatus = strtolower(trim((string) ($order['fulfillment_status'] ?? '')));
        return match ($fulfillmentStatus) {
            'fulfilled' => 'Shipped',
            'partial' => 'Partially Shipped',
            'restocked' => 'Restocked / Returned',
            default => 'Not Yet Shipped',
        };
    }
}
