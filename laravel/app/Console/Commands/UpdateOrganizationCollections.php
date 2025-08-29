<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Services\AiAgentService;

class UpdateOrganizationCollections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'organizations:update-collections
                          {--recreate : Recreate all Qdrant collections}
                          {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update organization collection names and optionally recreate Qdrant collections';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $recreate = $this->option('recreate');
        $dryRun = $this->option('dry-run');
        
        $aiService = app(AiAgentService::class);
        
        $this->info('Updating organization collection names...');
        
        $organizations = Organization::all();
        
        foreach ($organizations as $org) {
            $oldCollectionName = $org->getOriginal('collection_name') ?? "org_{$org->id}_data";
            $newCollectionName = Organization::generateUniqueCollectionName($org->name, $org->id);
            
            // Validate the generated collection name
            if (!Organization::validateCollectionName($newCollectionName)) {
                $this->error("Invalid collection name generated for '{$org->name}': {$newCollectionName}");
                continue;
            }
            
            $this->line("Organization: {$org->name}");
            $this->line("  Current collection: {$oldCollectionName}");
            $this->line("  New collection: {$newCollectionName}");
            
            if (!$dryRun) {
                // Update the organization record
                $org->collection_name = $newCollectionName;
                $org->save();
                
                if ($recreate) {
                    try {
                        // Delete old collection if it exists
                        $this->line("  Deleting old collection: {$oldCollectionName}");
                        $aiService->deleteCollection($oldCollectionName);
                    } catch (\Exception $e) {
                        $this->warn("  Could not delete old collection: " . $e->getMessage());
                    }
                    
                    try {
                        // Create new collection
                        $this->line("  Creating new collection: {$newCollectionName}");
                        $aiService->createCollection($newCollectionName);
                        $this->info("  ✓ Collection created successfully");
                    } catch (\Exception $e) {
                        $this->error("  ✗ Failed to create collection: " . $e->getMessage());
                    }
                }
            }
            
            $this->line('');
        }
        
        if ($dryRun) {
            $this->info('Dry run completed. Use --recreate to actually recreate collections.');
        } else {
            $this->info('Organization collection names updated successfully!');
            
            if ($recreate) {
                $this->info('Qdrant collections have been recreated. You will need to re-import your data.');
            }
        }
        
        return 0;
    }
}
