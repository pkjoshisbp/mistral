<?php

require_once __DIR__ . '/laravel/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;

echo "Testing Service Sync to Qdrant\n";
echo "==============================\n\n";

// Get the organization
$org = Organization::where('slug', 'ai-chat-support')->first();
if (!$org) {
    echo "Organization not found!\n";
    exit(1);
}

echo "Organization: {$org->name} (slug: {$org->slug})\n";

// Get the WhatsApp service
$service = OrganizationData::where('organization_id', $org->id)->where('type', 'service')->first();
if (!$service) {
    echo "WhatsApp service not found in database!\n";
    exit(1);
}

echo "Service found: {$service->name}\n";
echo "Service ID: {$service->id}\n";
echo "Content: " . substr($service->content, 0, 100) . "...\n\n";

// Check current Qdrant collection count
echo "Checking current Qdrant collection count...\n";
$response = file_get_contents('http://localhost:8111/qdrant/collections');
$collections = json_decode($response, true);

$currentCount = 0;
foreach ($collections['collections'] as $collection) {
    if ($collection['name'] === 'ai-chat-support') {
        $currentCount = $collection['points_count'];
        break;
    }
}
echo "Current points in ai-chat-support collection: {$currentCount}\n\n";

// Prepare service data
$serviceData = [
    'data_type' => 'service',
    'item_id' => 'service_' . $service->id,
    'title' => $service->name,
    'content' => $service->content,
    'category' => $service->metadata['category'] ?? 'integration',
    'organization_slug' => $org->slug,
    'table_id' => $service->id,
    'updated_at' => $service->updated_at->toISOString(),
    'price' => $service->metadata['price'] ?? '5000',
    'requirements' => $service->metadata['requirements'] ?? '',
    'duration' => $service->metadata['duration'] ?? '',
    'availability' => $service->metadata['availability'] ?? '',
    'keywords' => $service->metadata['keywords'] ?? 'whatsapp',
];

echo "Syncing service to Qdrant...\n";
$ai = new AiAgentService();
$result = $ai->storeDataToQdrant($org->slug, 'service', [$serviceData]);

if ($result) {
    echo "Sync result: SUCCESS\n";
    echo "Successful stores: " . ($result['successful_stores'] ?? 0) . "\n";
    echo "Failed stores: " . ($result['failed_stores'] ?? 0) . "\n";
} else {
    echo "Sync result: FAILED\n";
}

// Check collection count after sync
echo "\nChecking collection count after sync...\n";
sleep(2); // Wait for sync to complete
$response = file_get_contents('http://localhost:8111/qdrant/collections');
$collections = json_decode($response, true);

foreach ($collections['collections'] as $collection) {
    if ($collection['name'] === 'ai-chat-support') {
        $newCount = $collection['points_count'];
        echo "Points in ai-chat-support collection after sync: {$newCount}\n";
        if ($newCount > $currentCount) {
            echo "✅ SUCCESS: Service data was added to Qdrant!\n";
        } else {
            echo "❌ FAILED: No new points added to Qdrant\n";
        }
        break;
    }
}

echo "\nTesting search for WhatsApp service...\n";

// Test search for the service
$searchPayload = [
    'collection' => 'ai-chat-support',
    'query' => 'WhatsApp integration service',
    'limit' => 5
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8111/qdrant/search');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($searchPayload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$searchResponse = curl_exec($ch);
curl_close($ch);

if ($searchResponse) {
    $searchResult = json_decode($searchResponse, true);
    if (isset($searchResult['results'])) {
        echo "Search results: " . count($searchResult['results']) . " found\n";
        foreach ($searchResult['results'] as $i => $result) {
            $payload = $result['payload'];
            echo "  " . ($i + 1) . ". Score: " . number_format($result['score'], 3) . "\n";
            echo "     Type: " . ($payload['data_type'] ?? 'unknown') . "\n";
            echo "     Title: " . ($payload['title'] ?? 'N/A') . "\n";
            echo "     Item ID: " . ($payload['item_id'] ?? 'N/A') . "\n";
            if ($payload['data_type'] === 'service' && strpos($payload['item_id'] ?? '', 'service_') === 0) {
                echo "     ✅ Found our service!\n";
            }
            echo "\n";
        }
    } else {
        echo "No search results returned\n";
    }
} else {
    echo "Search request failed\n";
}

echo "Test completed!\n";
