<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Integration;
use App\Services\ShopifyApiService;

$integration = Integration::where('shop', 'ai-chat-support.myshopify.com')
    ->where('provider', 'shopify')
    ->first();

if (!$integration) {
    echo "No integration found\n";
    exit(1);
}

echo "Integration ID: {$integration->id}\n";
echo "Shop: {$integration->shop}\n";
echo "Has token: " . (!empty($integration->access_token) ? 'yes' : 'no') . "\n\n";

$service = new ShopifyApiService($integration);
$products = $service->getAllProducts(5);

echo "Products returned: " . count($products) . "\n\n";

if (!empty($products)) {
    foreach ($products as $product) {
        echo "- {$product['title']} - \${$product['price']} (ID: {$product['id']})\n";
    }
} else {
    echo "No products found - checking raw API response...\n\n";
    
    // Try to call the API directly to see what's happening
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('makeRequest');
    $method->setAccessible(true);
    
    $response = $method->invoke($service, 'products.json', ['limit' => 5, 'status' => 'active']);
    echo "Raw API response:\n";
    print_r($response);
}
