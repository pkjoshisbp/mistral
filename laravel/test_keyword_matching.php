<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OrganizationAction;

echo "\n=== Debug Action Matching ===\n\n";

$actions = OrganizationAction::where('organization_id', 28)
    ->where('is_active', 1)
    ->get();

echo "Found {$actions->count()} active actions for org 28\n\n";

$query = "tell me about your credit packages?";
$queryLower = strtolower($query);

foreach ($actions as $action) {
    echo "Action: {$action->name} (ID: {$action->id})\n";
    echo "Type: {$action->action_type}\n";
    echo "Keywords: " . json_encode($action->keywords) . "\n";
    echo "Aliases: " . json_encode($action->aliases) . "\n";
    
    $keywordScore = 0;
    $matchedTerms = [];
    
    foreach ($action->keywords ?? [] as $keyword) {
        if (str_contains($queryLower, strtolower($keyword))) {
            $keywordScore += 0.2;
            $matchedTerms[] = "keyword: {$keyword}";
        }
    }
    
    foreach ($action->aliases ?? [] as $alias) {
        if (str_contains($queryLower, strtolower($alias))) {
            $keywordScore += 0.15;
            $matchedTerms[] = "alias: {$alias}";
        }
    }
    
    echo "Score: {$keywordScore}\n";
    echo "Matched: " . implode(', ', $matchedTerms) . "\n";
    echo "\n";
}
