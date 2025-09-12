<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DemoQdrantService;

class SyncDemoData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:sync {--industry= : Sync specific industry only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync demo organization data to Qdrant collections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting demo data synchronization...');
        
        $demoService = app(DemoQdrantService::class);
        
        try {
            if ($industry = $this->option('industry')) {
                $this->info("Syncing demo data for industry: {$industry}");
                $demo = \App\Models\DemoOrganization::where('industry', $industry)->where('is_active', true)->first();
                
                if (!$demo) {
                    $this->error("No active demo found for industry: {$industry}");
                    return 1;
                }
                
                $success = $demoService->syncDemoCollection($demo);
                
                if ($success) {
                    $this->info("✅ Successfully synced {$industry} demo data");
                } else {
                    $this->error("❌ Failed to sync {$industry} demo data");
                    return 1;
                }
            } else {
                $this->info('Syncing all demo collections...');
                $result = $demoService->syncAllDemoCollections();
                
                if ($result['success']) {
                    $this->info("✅ {$result['message']}");
                } else {
                    $this->error("❌ Failed to sync demo collections");
                    return 1;
                }
            }
            
            $this->info('🎉 Demo data synchronization completed successfully!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error("💥 Error: " . $e->getMessage());
            return 1;
        }
    }
}
