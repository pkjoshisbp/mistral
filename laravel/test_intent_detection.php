<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\IntentDetectionService;

echo "\n=== Debug Intent Detection ===\n\n";

$intentService = app(IntentDetectionService::class);

$query = "tell me about your credit packages?";
$orgId = 28;

echo "Query: {$query}\n";
echo "Org ID: {$orgId}\n\n";

$result = $intentService->detectIntent($query, $orgId);

echo "Intent Result:\n";
print_r($result);

echo "\nRecommended Action Types:\n";
$recommendedTypes = $intentService->getRecommendedActionTypes($result);
print_r($recommendedTypes);

echo "\n=== Complete ===\n";
