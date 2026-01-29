<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\OrganizationAction;
use Illuminate\Database\Seeder;

class StandardActionsSeeder extends Seeder
{
    /**
     * Seed standard actions that can be used by any organization
     * Run: php artisan db:seed --class=StandardActionsSeeder
     */
    public function run(): void
    {
        $this->command->info('This seeder creates EXAMPLE actions for demonstration.');
        $this->command->info('Use the Admin Panel → Live Data Actions to create actions for specific organizations.');
        $this->command->newLine();
        
        // You can add default action templates here if needed
        // But the recommended approach is to use the Admin Panel for each organization
        
        $this->command->info('✅ For production use:');
        $this->command->info('   1. Go to Admin Panel → Live Data Actions');
        $this->command->info('   2. Click "+ Add New Action"');
        $this->command->info('   3. Configure action for specific organization');
        $this->command->info('   4. Test using the "Play" button');
    }
}
