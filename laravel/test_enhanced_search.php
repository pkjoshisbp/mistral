<?php

require_once 'vendor/autoload.php';

use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel without full app
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$aiService = new AiAgentService();

// Test query rewriting
echo "Testing Query Rewriting:\n";
echo "=" . str_repeat("=", 50) . "\n";

$testQueries = [
    "do you have any refund policy?",
    "how much does it cost?",
    "what are your business hours?",
    "can I get support?"
];

foreach ($testQueries as $query) {
    echo "Original: {$query}\n";
    $rewritten = $aiService->rewriteQueryForSearch($query);
    echo "Rewritten: {$rewritten}\n";
    echo str_repeat("-", 50) . "\n";
}

// Test enhanced search
echo "\nTesting Enhanced Search:\n";
echo "=" . str_repeat("=", 50) . "\n";

$searchResults = $aiService->enhancedSearch('ai-chat-support', 'do you have any refund policy?', 3);

if ($searchResults && isset($searchResults['results'])) {
    echo "Found " . count($searchResults['results']) . " results:\n";
    foreach ($searchResults['results'] as $i => $result) {
        echo "Result " . ($i + 1) . ":\n";
        echo "  Score: " . $result['score'] . "\n";
        echo "  Question: " . ($result['payload']['title'] ?? 'N/A') . "\n";
        echo "  Category: " . ($result['payload']['category'] ?? 'N/A') . "\n";
        echo str_repeat("-", 50) . "\n";
    }
} else {
    echo "No results found.\n";
}

echo "Test completed!\n";
