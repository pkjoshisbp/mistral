<?php

require_once 'laravel/vendor/autoload.php';
require_once 'laravel/bootstrap/app.php';

use App\Models\TokenUsageLog;
use App\Models\User;
use App\Models\Organization;

echo "Creating sample token usage logs for testing..." . PHP_EOL;

// Get a test user and organization
$user = User::where('email', 'customer@ai-chat.support')->first();
$organization = Organization::first();

if (!$user) {
    echo "User not found. Please ensure customer@ai-chat.support exists." . PHP_EOL;
    exit(1);
}

if (!$organization) {
    echo "No organization found. Please create an organization first." . PHP_EOL;
    exit(1);
}

echo "Found user: {$user->name} (ID: {$user->id})" . PHP_EOL;
echo "Found organization: {$organization->name} (ID: {$organization->id})" . PHP_EOL;

// Create sample token usage logs
$sampleLogs = [
    [
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'subscription_id' => $user->activeSubscription ? $user->activeSubscription->id : null,
        'endpoint_type' => 'openai_chat',
        'tokens_used' => 250,
        'request_summary' => 'Customer inquiry about pricing plans',
        'used_at' => now()->subDays(1),
    ],
    [
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'subscription_id' => $user->activeSubscription ? $user->activeSubscription->id : null,
        'endpoint_type' => 'llm_chat',
        'tokens_used' => 180,
        'request_summary' => 'Widget chat about service availability',
        'used_at' => now()->subDays(2),
    ],
    [
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'subscription_id' => $user->activeSubscription ? $user->activeSubscription->id : null,
        'endpoint_type' => 'enhanced_search',
        'tokens_used' => 120,
        'request_summary' => 'Document search for FAQ content',
        'used_at' => now()->subDays(3),
    ],
    [
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'subscription_id' => $user->activeSubscription ? $user->activeSubscription->id : null,
        'endpoint_type' => 'openai_chat',
        'tokens_used' => 340,
        'request_summary' => 'Complex customer query about integrations',
        'used_at' => now()->subHours(6),
    ],
    [
        'user_id' => $user->id,
        'organization_id' => $organization->id,
        'subscription_id' => $user->activeSubscription ? $user->activeSubscription->id : null,
        'endpoint_type' => 'action_execution',
        'tokens_used' => 95,
        'request_summary' => 'Live data action for pricing query',
        'used_at' => now()->subHours(12),
    ],
];

foreach ($sampleLogs as $logData) {
    try {
        TokenUsageLog::create($logData);
        echo "✓ Created token log: {$logData['endpoint_type']} - {$logData['tokens_used']} tokens" . PHP_EOL;
    } catch (Exception $e) {
        echo "✗ Failed to create log: " . $e->getMessage() . PHP_EOL;
    }
}

echo PHP_EOL . "Sample token usage logs created successfully!" . PHP_EOL;
echo "Total logs in database: " . TokenUsageLog::count() . PHP_EOL;