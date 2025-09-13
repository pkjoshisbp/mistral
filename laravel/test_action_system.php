<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\AiAgentService;
use App\Services\ActionService;
use App\Services\IntentDetectionService;
use App\Services\ActionExecutorService;
use App\Models\Organization;
use App\Models\OrganizationAction;
use Illuminate\Support\Facades\Log;

// Initialize Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== AI Action System Test ===\n\n";

try {
    // Get AI Chat Support organization
    $organization = Organization::where('slug', 'ai-chat-support')->first();
    
    if (!$organization) {
        echo "❌ Organization 'ai-chat-support' not found\n";
        exit(1);
    }
    
    echo "✅ Found organization: {$organization->name} (ID: {$organization->id})\n\n";
    
    // Check actions
    $actions = OrganizationAction::forOrganization($organization->id)->active()->get();
    echo "📋 Found {$actions->count()} active actions:\n";
    
    foreach ($actions as $action) {
        echo "  - {$action->name} ({$action->action_type}, {$action->source_type})\n";
    }
    echo "\n";
    
    // Initialize services
    $aiAgent = new AiAgentService();
    $intentDetector = new IntentDetectionService($aiAgent);
    $executor = new ActionExecutorService($aiAgent);
    $actionService = new ActionService($aiAgent, $intentDetector, $executor);
    
    // Test queries
    $testQueries = [
        "What are your pricing plans?",
        "How much does premium support cost?",
        "Can I check room availability for tomorrow?",
        "What is your refund policy?", // Should use KB
        "Show me service fees",
    ];
    
    foreach ($testQueries as $query) {
        echo "🤖 Testing Query: \"$query\"\n";
        echo str_repeat("-", 50) . "\n";
        
        // Test intent detection
        $intent = $intentDetector->detectIntent($query, $organization->id);
        echo "Intent: {$intent['intent']} (confidence: {$intent['confidence']}, method: {$intent['method']})\n";
        
        // Test action processing
        $result = $actionService->processQuery($query, $organization->id);
        
        echo "Result Type: {$result['type']}\n";
        
        if ($result['type'] === 'action_executed') {
            $success = $result['result']['success'] ? '✅' : '❌';
            echo "Action: {$result['action']['name']} $success\n";
            
            if ($result['result']['success']) {
                $dataCount = is_array($result['result']['data']) ? count($result['result']['data']) : 1;
                echo "Data Retrieved: $dataCount records\n";
                
                // Show sample data
                if (isset($result['result']['data']) && is_array($result['result']['data'])) {
                    $sample = array_slice($result['result']['data'], 0, 2);
                    foreach ($sample as $i => $record) {
                        echo "  Sample " . ($i + 1) . ": " . json_encode($record) . "\n";
                    }
                }
            } else {
                echo "Error: {$result['result']['error']}\n";
            }
        } elseif ($result['type'] === 'knowledge_base') {
            echo "Using Knowledge Base (no action needed)\n";
        } elseif ($result['type'] === 'error') {
            echo "❌ Error: {$result['error']}\n";
        }
        
        echo "\n";
    }
    
    // Test CSV action specifically
    echo "🧪 Testing CSV Action Directly\n";
    echo str_repeat("=", 50) . "\n";
    
    $pricingAction = OrganizationAction::where('action_type', 'GET_PRICING')->first();
    if ($pricingAction) {
        $params = ['service' => 'Premium'];
        $csvResult = $executor->executeAction($pricingAction, $params);
        
        if ($csvResult['success']) {
            echo "✅ CSV Action Success!\n";
            echo "Source: {$csvResult['source']}\n";
            echo "Total Rows: {$csvResult['total_rows']}\n";
            echo "Sample Data: " . json_encode(array_slice($csvResult['data'], 0, 2), JSON_PRETTY_PRINT) . "\n";
        } else {
            echo "❌ CSV Action Failed: {$csvResult['error']}\n";
        }
    }
    
    echo "\n✅ Action System Test Complete!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}