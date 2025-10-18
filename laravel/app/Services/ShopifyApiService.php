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

    public function __construct(?Integration $integration = null)
    {
        if ($integration) {
            $this->integration = $integration;
            $this->shopDomain = $integration->shop_domain;
            $this->accessToken = $integration->access_token;
        }
    }

    /**
     * Set integration dynamically
     */
    public function setIntegration(Integration $integration)
    {
        $this->integration = $integration;
        $this->shopDomain = $integration->shop_domain;
        $this->accessToken = $integration->access_token;
        return $this;
    }

    /**
     * Get shop info for integration by shop domain
     */
    public static function getIntegrationByShop(string $shopDomain): ?Integration
    {
        return Integration::where('shop_domain', $shopDomain)
            ->where('integration_type', 'shopify')
            ->whereNull('deleted_at')
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
     * Search products by query
     */
    public function searchProducts(string $query, int $limit = 10)
    {
        $cacheKey = "shopify_products_{$this->shopDomain}_" . md5($query);
        
        return Cache::remember($cacheKey, 300, function () use ($query, $limit) {
            $response = $this->makeRequest('products.json', [
                'limit' => $limit,
                'title' => $query,
                'status' => 'active'
            ]);

            if (!$response || !isset($response['products'])) {
                return [];
            }

            return array_map(function ($product) {
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'description' => strip_tags($product['body_html'] ?? ''),
                    'price' => $product['variants'][0]['price'] ?? 'N/A',
                    'currency' => $product['variants'][0]['currency_code'] ?? 'USD',
                    'available' => ($product['variants'][0]['inventory_quantity'] ?? 0) > 0,
                    'inventory' => $product['variants'][0]['inventory_quantity'] ?? 0,
                    'url' => "https://{$this->shopDomain}/products/{$product['handle']}",
                    'image' => $product['image']['src'] ?? null,
                ];
            }, $response['products']);
        });
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
            return [
                'id' => $product['id'],
                'title' => $product['title'],
                'description' => strip_tags($product['body_html'] ?? ''),
                'price' => $product['variants'][0]['price'] ?? 'N/A',
                'currency' => $product['variants'][0]['currency_code'] ?? 'USD',
                'available' => ($product['variants'][0]['inventory_quantity'] ?? 0) > 0,
                'inventory' => $product['variants'][0]['inventory_quantity'] ?? 0,
                'url' => "https://{$this->shopDomain}/products/{$product['handle']}",
                'image' => $product['image']['src'] ?? null,
                'variants' => array_map(function ($variant) {
                    return [
                        'id' => $variant['id'],
                        'title' => $variant['title'],
                        'price' => $variant['price'],
                        'available' => ($variant['inventory_quantity'] ?? 0) > 0,
                        'inventory' => $variant['inventory_quantity'] ?? 0,
                    ];
                }, $product['variants'] ?? []),
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
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'description' => strip_tags($product['body_html'] ?? ''),
                    'price' => $product['variants'][0]['price'] ?? 'N/A',
                    'available' => ($product['variants'][0]['inventory_quantity'] ?? 0) > 0,
                    'url' => "https://{$this->shopDomain}/products/{$product['handle']}",
                ];
            }, $response['products']);
        });
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

        if ($response && isset($response['orders']) && count($response['orders']) > 0) {
            return $this->formatOrder($response['orders'][0]);
        }

        // Try by ID if numeric
        if (is_numeric($orderIdentifier)) {
            $response = $this->makeRequest("orders/{$orderIdentifier}.json");
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
                'tracking_url' => $fulfillments[0]['tracking_url'] ?? null,
                'tracking_company' => $fulfillments[0]['tracking_company'] ?? null,
                'status' => $fulfillments[0]['status'] ?? null,
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
                $formatted = "Order {$data['name']}:\n";
                $formatted .= "Status: " . ucfirst($data['fulfillment_status'] ?? 'pending') . "\n";
                $formatted .= "Payment: " . ucfirst($data['financial_status']) . "\n";
                $formatted .= "Total: {$data['currency']} {$data['total_price']}\n";
                
                if (!empty($data['tracking']['tracking_number'])) {
                    $formatted .= "Tracking: {$data['tracking']['tracking_number']}\n";
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
}
