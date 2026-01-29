<?php
/**
 * Sync pricing actions to Qdrant vector database
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OrganizationAction;
use App\Services\ActionService;
use App\Services\AiAgentService;

echo "\n=== Syncing Pricing Actions to Qdrant ===\n\n";

$actionService = app(ActionService::class);
$aiAgent = app(AiAgentService::class);

// Ensure collection exists
$collectionName = 'org_28';
echo "Checking if collection '{$collectionName}' exists...\n";

try {
    // Try to create collection (will fail if exists, which is fine)
    $result = $aiAgent->createCollection($collectionName, 768);
    if ($result) {
        echo "✅ Collection created successfully\n\n";
    } else {
        echo "ℹ️  Collection may already exist (trying to continue)\n\n";
    }
} catch (\Exception $e) {
    echo "ℹ️  Collection creation error (may already exist): " . $e->getMessage() . "\n\n";
}

// Get the two actions we just created
$actions = OrganizationAction::where('organization_id', 28)
    ->whereIn('id', [7, 8])
    ->get();

if ($actions->isEmpty()) {
    echo "❌ No actions found!\n";
    exit(1);
}

foreach ($actions as $action) {
    echo "Syncing action: {$action->name} (ID: {$action->id})...\n";
    
    try {
        $result = $actionService->syncActionToVectorDB($action);
        
        if ($result) {
            echo "✅ Successfully synced to Qdrant\n\n";
        } else {
            echo "❌ Failed to sync to Qdrant\n\n";
        }
    } catch (\Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "=== Sync Complete ===\n";
