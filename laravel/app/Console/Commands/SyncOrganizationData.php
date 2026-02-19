<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Models\OrganizationInfo;
use App\Models\OrganizationService;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;

class SyncOrganizationData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:organization-data {organization_id?} {--type=all}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync organization data (FAQs, Infos, Services) to Qdrant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $organizationId = $this->argument('organization_id');
        $type = $this->option('type');
        
        $query = Organization::query();
        if ($organizationId) {
            $query->where('id', $organizationId);
        }
        
        $organizations = $query->get();
        $aiService = new AiAgentService();
        
        foreach ($organizations as $organization) {
            $this->info("Syncing data for: {$organization->name} (slug: {$organization->slug})");
            
            // Sync FAQs
            if ($type === 'all' || $type === 'faq') {
                $this->syncFaqs($organization, $aiService);
            }
            
            // Sync Infos
            if ($type === 'all' || $type === 'info') {
                $this->syncInfos($organization, $aiService);
            }
            
            // Sync Services
            if ($type === 'all' || $type === 'service') {
                $this->syncServices($organization, $aiService);
            }
        }
        
        $this->info('Sync completed!');
    }
    
    private function syncFaqs($organization, $aiService)
    {
        $faqs = OrganizationFaq::where('organization_id', $organization->id)
            ->where('is_active', true)
            ->get();
            
        if ($faqs->isEmpty()) {
            $this->warn("No active FAQs found for {$organization->name}");
            return;
        }
        
        $items = $faqs->map(function ($faq) {
            return [
                'id' => "faq_{$faq->id}",
                'title' => $faq->question,
                'content' => $faq->answer,
                'category' => $faq->category ?? 'general',
                'follow_up' => $faq->follow_up ?? null,
                'metadata' => [
                    'table_id' => $faq->id,
                    'updated_at' => $faq->updated_at->toISOString()
                ]
            ];
        })->toArray();
        
        $this->info("Syncing " . count($items) . " FAQs...");
        
        $result = $aiService->storeDataToQdrant($organization->slug, 'faq', $items);
        
        if ($result && $result['success']) {
            $this->info("✅ FAQs: {$result['successful_stores']}/{$result['total_items']} successful");
            if ($result['failed_stores'] > 0) {
                $this->warn("⚠️ {$result['failed_stores']} failed");
            }
        } else {
            $this->error("❌ FAQ sync failed");
        }
    }
    
    private function syncInfos($organization, $aiService)
    {
        try {
            $infos = OrganizationInfo::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();
                
            if ($infos->isEmpty()) {
                $this->warn("No active infos found for {$organization->name}");
                return;
            }
            
            $items = $infos->map(function ($info) {
                return [
                    'id' => "info_{$info->id}",
                    'title' => $info->title,
                    'content' => $info->content,
                    'category' => $info->category ?? 'general',
                    'metadata' => [
                        'table_id' => $info->id,
                        'updated_at' => $info->updated_at->toISOString()
                    ]
                ];
            })->toArray();
            
            $this->info("Syncing " . count($items) . " infos...");
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'info', $items);
            
            if ($result && $result['success']) {
                $this->info("✅ Infos: {$result['successful_stores']}/{$result['total_items']} successful");
                if ($result['failed_stores'] > 0) {
                    $this->warn("⚠️ {$result['failed_stores']} failed");
                }
            } else {
                $this->error("❌ Info sync failed");
            }
        } catch (\Exception $e) {
            $this->warn("Infos table might not exist: " . $e->getMessage());
        }
    }
    
    private function syncServices($organization, $aiService)
    {
        try {
            $services = OrganizationService::where('organization_id', $organization->id)
                ->where('is_active', true)
                ->get();
                
            if ($services->isEmpty()) {
                $this->warn("No active services found for {$organization->name}");
                return;
            }
            
            $items = $services->map(function ($service) {
                return [
                    'id' => "service_{$service->id}",
                    'title' => $service->name,
                    'content' => $service->description,
                    'category' => $service->category ?? 'general',
                    'metadata' => [
                        'table_id' => $service->id,
                        'price' => $service->price ?? null,
                        'updated_at' => $service->updated_at->toISOString()
                    ]
                ];
            })->toArray();
            
            $this->info("Syncing " . count($items) . " services...");
            
            $result = $aiService->storeDataToQdrant($organization->slug, 'service', $items);
            
            if ($result && $result['success']) {
                $this->info("✅ Services: {$result['successful_stores']}/{$result['total_items']} successful");
                if ($result['failed_stores'] > 0) {
                    $this->warn("⚠️ {$result['failed_stores']} failed");
                }
            } else {
                $this->error("❌ Service sync failed");
            }
        } catch (\Exception $e) {
            $this->warn("Services table might not exist: " . $e->getMessage());
        }
    }
}
