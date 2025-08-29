<?php

use Illuminate\Support\Facades\Artisan;

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Organization;
use App\Services\AiAgentService;

echo "AI Agent System Status Check\n";
echo "============================\n\n";

// Check Organizations
echo "1. Organizations:\n";
$organizations = Organization::all();
foreach ($organizations as $org) {
    echo "   - {$org->name} → Collection: {$org->collection_name}\n";
}
echo "\n";

// Check FastAPI connection
echo "2. FastAPI Connection:\n";
try {
    $aiService = app(AiAgentService::class);
    // Test by checking if we can connect to embed a simple text
    $result = $aiService->embed("test");
    if (!empty($result)) {
        echo "   ✓ FastAPI is running and accessible\n";
    } else {
        echo "   ✗ FastAPI connection failed: Empty response\n";
    }
} catch (Exception $e) {
    echo "   ✗ FastAPI connection failed: " . $e->getMessage() . "\n";
}
echo "\n";

// Check Qdrant collections
echo "3. Qdrant Collections:\n";
try {
    $aiService = app(AiAgentService::class);
    
    foreach ($organizations as $org) {
        $collectionExists = $aiService->collectionExists($org->collection_name);
        $status = $collectionExists ? "✓ EXISTS" : "✗ MISSING";
        echo "   - {$org->collection_name}: {$status}\n";
    }
} catch (Exception $e) {
    echo "   ✗ Failed to check collections: " . $e->getMessage() . "\n";
}
echo "\n";

echo "4. System Ready Status:\n";
$allReady = true;

// Check if FastAPI is running
try {
    $aiService->embed("test");
    echo "   ✓ FastAPI Backend: READY\n";
} catch (Exception $e) {
    echo "   ✗ FastAPI Backend: NOT READY - " . $e->getMessage() . "\n";
    $allReady = false;
}

// Check if organizations have collections
foreach ($organizations as $org) {
    if (empty($org->collection_name)) {
        echo "   ✗ Organization '{$org->name}': NO COLLECTION NAME\n";
        $allReady = false;
    }
}

if ($allReady) {
    echo "   ✓ Multi-Organization AI System: READY FOR USE\n";
} else {
    echo "   ✗ System: NEEDS SETUP\n";
}

echo "\n";
echo "Next Steps:\n";
echo "- Import data for each organization using: php artisan ai:import-data {org_id}\n";
echo "- Access chat interface at: https://ai-chat.support/ai-chat\n";
echo "- API endpoints available for third-party integration\n";
