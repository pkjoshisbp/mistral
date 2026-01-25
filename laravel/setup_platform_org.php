<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Organization;
use App\Models\OrganizationData;

$admin = User::where('email', 'info@ai-chat.support')->first();
$platformOrg = Organization::where('slug', 'platform')->first();

if ($admin && $platformOrg) {
    if (!$platformOrg->users()->where('user_id', $admin->id)->exists()) {
        $platformOrg->users()->attach($admin->id);
        echo "✓ Associated admin with platform organization\n";
    } else {
        echo "✓ Admin already associated with platform org\n";
    }
    
    // Add sample FAQ
    $faq = OrganizationData::create([
        'organization_id' => $platformOrg->id,
        'type' => 'faq',
        'name' => 'What is AI Chat Support?',
        'data_type' => 'faq',
        'title' => 'What is AI Chat Support?',
        'content' => 'AI Chat Support is a multi-organization AI support agent system powered by Llama 3.2. It provides intelligent chat support for websites, e-commerce stores, and businesses. We support Shopify integration, custom websites, and more.',
        'category' => 'General',
        'keywords' => 'about, platform, AI, chat support, features, llama'
    ]);
    
    echo "✓ Created FAQ ID: {$faq->id}\n";
    
    // Sync to Qdrant
    echo "\n→ Syncing to Qdrant...\n";
    $syncService = app(\App\Services\UnifiedSyncService::class);
    $syncService->syncOrganizationData($platformOrg->id);
    
    echo "✓ Platform organization setup complete!\n\n";
    echo "Organization Details:\n";
    echo "  - ID: {$platformOrg->id}\n";
    echo "  - Name: {$platformOrg->name}\n";
    echo "  - Slug: {$platformOrg->slug}\n";
    echo "  - Website: {$platformOrg->website}\n";
    echo "  - Data entries: " . $platformOrg->organizationData()->count() . "\n";
}
