<?php
/**
 * Debug Action Matching Script
 * Tests why actions aren't being triggered
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Organization;
use App\Models\OrganizationAction;
use App\Services\ActionService;
use App\Services\IntentDetectionService;

echo "\n=== Debug Action Matching ===\n";

try {
    $organization = Organization::first();
    $action = OrganizationAction::where('organization_id', $organization->id)->first();
    
    if (!$action) {
        echo "❌ No actions found\n";
        exit;
    }
    
    echo "🔍 Testing action: {$action->name}\n";
    echo "   Keywords: " . implode(', ', $action->keywords ?? []) . "\n";
    echo "   Action type: {$action->action_type}\n";
    
    // Test intent detection
    $intentService = new IntentDetectionService(app(\App\Services\AiAgentService::class));
    
    $testQueries = [
        "What are your pricing plans?",
        "How much does it cost?",
        "What's your pricing?"
    ];
    
    foreach ($testQueries as $query) {
        echo "\n--- Testing: '$query' ---\n";
        
        // Check intent detection
        $intent = $intentService->detectIntent($query, $organization->id);
        echo "Intent: {$intent['intent']} (confidence: {$intent['confidence']})\n";
        echo "Entities: " . implode(', ', $intent['entities'] ?? []) . "\n";
        
        // Check keyword matching
        $hasKeywordMatch = false;
        $queryWords = str_word_count(strtolower($query), 1);
        
        foreach ($action->keywords ?? [] as $keyword) {
            if (in_array(strtolower($keyword), $queryWords) || 
                str_contains(strtolower($query), strtolower($keyword))) {
                $hasKeywordMatch = true;
                echo "✅ Keyword match found: '$keyword'\n";
                break;
            }
        }
        
        if (!$hasKeywordMatch) {
            echo "❌ No keyword matches found\n";
        }
        
        // Check action type matching
        $recommendedTypes = $intentService->getRecommendedActionTypes($intent);
        echo "Recommended action types: " . implode(', ', $recommendedTypes) . "\n";
        
        if (empty($recommendedTypes) || in_array($action->action_type, $recommendedTypes)) {
            echo "✅ Action type matches or no restriction\n";
        } else {
            echo "❌ Action type '{$action->action_type}' not in recommended types\n";
        }
    }
    
    // Test simpler rule-based matching
    echo "\n=== Testing Rule-based Matching ===\n";
    
    $actionService = app(ActionService::class);
    
    foreach ($testQueries as $query) {
        echo "\nTesting rule-based match for: '$query'\n";
        
        $actions = OrganizationAction::forOrganization($organization->id)->active()->get();
        
        foreach ($actions as $testAction) {
            $match = false;
            
            // Simple keyword check
            foreach ($testAction->keywords ?? [] as $keyword) {
                if (str_contains(strtolower($query), strtolower($keyword))) {
                    $match = true;
                    echo "✅ Rule-based match: '{$testAction->name}' (keyword: '$keyword')\n";
                    break;
                }
            }
            
            if (!$match) {
                echo "❌ No rule-based match for: '{$testAction->name}'\n";
            }
        }
    }
    
} catch (\Exception $e) {
    echo "\n❌ Debug failed: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== Debug Complete ===\n";