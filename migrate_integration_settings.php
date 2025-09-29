<?php
/**
 * Migrate Integration Settings to Organization Settings
 * 
 * This script moves all widget/display settings from Integration.settings 
 * to Organization.settings to create a single source of truth.
 */

require_once '/var/www/clients/client1/web64/web/laravel/vendor/autoload.php';

$app = require_once '/var/www/clients/client1/web64/web/laravel/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Integration;
use App\Models\Organization;
use Illuminate\Support\Facades\Log;

echo "Starting Integration Settings Migration...\n";

$migrations = 0;
$errors = 0;

// Get all integrations with settings
$integrations = Integration::whereNotNull('settings')->get();

echo "Found " . $integrations->count() . " integrations with settings.\n";

foreach ($integrations as $integration) {
    try {
        $organization = $integration->organization;
        
        if (!$organization) {
            echo "Warning: Integration {$integration->id} has no organization\n";
            continue;
        }

        // Get current organization settings (if any)
        $orgSettings = $organization->settings ?? [];
        
        // Get integration settings
        $integrationSettings = $integration->settings ?? [];
        
        // Define which settings should be moved to organization
        $widgetSettings = [
            'widget_position',
            'primary_color', 
            'secondary_color',
            'text_color',
            'welcome_message',
            'widget_offset_x',
            'widget_offset_y',
            'widget_theme',
            'border_radius',
            'require_contact_for_guests',
            'branding_enabled',
            'branding_follow',
            'branding_badge'
        ];
        
        // Move widget settings from integration to organization
        foreach ($widgetSettings as $setting) {
            if (isset($integrationSettings[$setting])) {
                $orgSettings[$setting] = $integrationSettings[$setting];
                echo "  Moving {$setting}: {$integrationSettings[$setting]}\n";
            }
        }
        
        // Update organization settings
        $organization->settings = $orgSettings;
        $organization->save();
        
        // Keep only provider-specific settings in integration
        $providerSettings = [];
        foreach ($integrationSettings as $key => $value) {
            if (!in_array($key, $widgetSettings)) {
                $providerSettings[$key] = $value;
            }
        }
        
        // Update integration with only provider-specific settings
        $integration->settings = $providerSettings;
        $integration->save();
        
        echo "Migrated settings for Organization: {$organization->name} (ID: {$organization->id})\n";
        $migrations++;
        
    } catch (Exception $e) {
        echo "Error migrating integration {$integration->id}: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\nMigration Complete!\n";
echo "Successfully migrated: {$migrations} organizations\n";
echo "Errors: {$errors}\n";

// Verify the migration for organization 9
echo "\nVerification for Organization 9:\n";
$org9 = Organization::find(9);
if ($org9) {
    echo "Organization 9 settings after migration:\n";
    echo json_encode($org9->settings, JSON_PRETTY_PRINT) . "\n";
}

$int9 = Integration::where('organization_id', 9)->first();
if ($int9) {
    echo "Integration settings after migration:\n";
    echo json_encode($int9->settings, JSON_PRETTY_PRINT) . "\n";
}