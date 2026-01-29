<?php
/**
 * Direct test of Qdrant add endpoint
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\Http;

echo "\n=== Testing Qdrant Add Endpoint ===\n\n";

// Create a simple test vector (768 dimensions, all 0.1)
$testVector = array_fill(0, 768, 0.1);

$payload = [
    'collection_name' => 'org_28',
    'vector' => $testVector,
    'payload' => [
        'test' => 'simple test',
        'source_type' => 'action'
    ],
    'id' => 'test_action_1'
];

echo "Sending request to FastAPI...\n";
echo "URL: http://localhost:8111/qdrant/add\n\n";

try {
    $response = Http::timeout(30)->post('http://localhost:8111/qdrant/add', $payload);
    
    echo "Status Code: " . $response->status() . "\n";
    echo "Response Body: " . $response->body() . "\n\n";
    
    if ($response->successful()) {
        echo "✅ SUCCESS!\n";
        $data = $response->json();
        print_r($data);
    } else {
        echo "❌ FAILED!\n";
        echo "Response: " . $response->body() . "\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
