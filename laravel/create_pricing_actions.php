<?php
/**
 * Create Credit Packages and Subscription Plans actions for AI Chat Support organization
 */

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\OrganizationAction;
use App\Models\Organization;

echo "\n=== Creating Pricing Actions for AI Chat Support ===\n\n";

// Get organization
$org = Organization::where('id', 28)->first();

if (!$org) {
    echo "❌ Organization ID 28 not found!\n";
    exit(1);
}

echo "✅ Found organization: {$org->name} (ID: {$org->id})\n\n";

// Create Credit Packages Action
echo "Creating Credit Packages action...\n";
$creditAction = OrganizationAction::create([
    'organization_id' => $org->id,
    'name' => 'Get Credit Packages',
    'action_type' => 'get_credit_packages',
    'description' => 'Retrieves one-time purchase credit packages. Use when user asks about credit packages, buying credits, one-time purchase, or credits that never expire.',
    'aliases' => [
        'credit packages',
        'buy credits',
        'one-time credits',
        'purchase credits',
        'credit plans'
    ],
    'keywords' => [
        'credit',
        'credits',
        'package',
        'packages',
        'one-time',
        'purchase',
        'buy',
        'never expire',
        'lifetime'
    ],
    'source_type' => 'database',
    'source_config' => [
        'table' => 'credit_packages',
        'query_type' => 'select',
        'query_template' => 'SELECT name, description, usd_price, inr_price, tokens, features FROM credit_packages WHERE is_active = 1 ORDER BY sort_order',
        'connection' => 'mysql'
    ],
    'params_template' => [],
    'required_params' => [],
    'optional_params' => [],
    'min_score_threshold' => 0.70,
    'cache_ttl' => 3600, // Cache for 1 hour
    'is_active' => true,
    'roles_allowed' => [],
    'response_template' => 'Here are our one-time credit packages that never expire:

{{#each results}}
**{{name}}**
- USD Price: ${{usd_price}}
- INR Price: ₹{{inr_price}}
- Tokens: {{tokens}} tokens
- Features: {{features}}

{{/each}}

These are one-time purchases and the credits never expire. You can use them whenever you need.',
    'output_format' => 'structured'
]);

echo "✅ Credit Packages action created (ID: {$creditAction->id})\n\n";

// Create Subscription Plans Action
echo "Creating Subscription Plans action...\n";
$subscriptionAction = OrganizationAction::create([
    'organization_id' => $org->id,
    'name' => 'Get Subscription Plans',
    'action_type' => 'get_subscription_plans',
    'description' => 'Retrieves monthly/yearly subscription plans. Use when user asks about subscriptions, monthly plans, recurring payments, or subscription pricing.',
    'aliases' => [
        'subscription plans',
        'monthly plans',
        'recurring plans',
        'subscriptions',
        'monthly pricing'
    ],
    'keywords' => [
        'subscription',
        'subscriptions',
        'monthly',
        'yearly',
        'recurring',
        'plan',
        'plans',
        'per month',
        '/month',
        '/mo'
    ],
    'source_type' => 'database',
    'source_config' => [
        'table' => 'subscription_plans',
        'query_type' => 'select',
        'query_template' => 'SELECT name, description, monthly_price, yearly_price, token_cap_monthly, overage_price_per_100k, features FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order',
        'connection' => 'mysql'
    ],
    'params_template' => [],
    'required_params' => [],
    'optional_params' => [],
    'min_score_threshold' => 0.70,
    'cache_ttl' => 3600, // Cache for 1 hour
    'is_active' => true,
    'roles_allowed' => [],
    'response_template' => 'Here are our monthly subscription plans:

{{#each results}}
**{{name}}**
- Monthly Price: ${{monthly_price}}/month
{{#if yearly_price}}- Yearly Price: ${{yearly_price}}/year{{/if}}
- Monthly Token Cap: {{token_cap_monthly}} tokens
- Overage Rate: ${{overage_price_per_100k}} per 100k tokens
- Features: {{features}}

{{/each}}

These are recurring subscriptions. Tokens refresh every month and unused tokens do not roll over.',
    'output_format' => 'structured'
]);

echo "✅ Subscription Plans action created (ID: {$subscriptionAction->id})\n\n";

echo "=== Summary ===\n";
echo "Created 2 actions for organization '{$org->name}':\n";
echo "1. Credit Packages (ID: {$creditAction->id}) - For one-time purchases\n";
echo "2. Subscription Plans (ID: {$subscriptionAction->id}) - For recurring monthly/yearly plans\n\n";
echo "✅ Actions created successfully!\n\n";
echo "Next step: Sync these actions to Qdrant vector database.\n";
