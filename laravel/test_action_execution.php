<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\ActionService;

echo "\n=== Testing Action Execution ===\n\n";

$actionService = app(ActionService::class);

$query = "tell me about your credit packages?";
$orgId = 28;

echo "Query: {$query}\n";
echo "Organization ID: {$orgId}\n\n";

try {
    $result = $actionService->processQuery($query, $orgId);
    
    echo "Result Type: " . $result['type'] . "\n";
    
    if ($result['type'] === 'action_executed') {
        echo "✅ ACTION EXECUTED!\n";
        echo "Action: " . ($result['action']['action_name'] ?? 'Unknown') . "\n";
        echo "Success: " . ($result['result']['success'] ? 'YES' : 'NO') . "\n";
        
        if (isset($result['result']['data'])) {
            echo "\nData Retrieved:\n";
            echo json_encode($result['result']['data'], JSON_PRETTY_PRINT) . "\n";
        }
    } elseif ($result['type'] === 'knowledge_base') {
        echo "❌ Fell back to knowledge base\n";
        echo "Reason: " . ($result['message'] ?? 'Unknown') . "\n";
    } else {
        echo "❓ Unknown result type\n";
        print_r($result);
    }
    
} catch (\Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
