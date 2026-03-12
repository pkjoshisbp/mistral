<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Models\OrganizationInfo;
use App\Models\OrganizationData;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

            // Sync Products
            if ($type === 'all' || $type === 'product') {
                $this->syncProducts($organization, $aiService);
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
                'keywords' => $faq->keywords,
                'search_keywords' => $faq->keywords,
                'metadata' => [
                    'table_id' => $faq->id,
                    'keywords' => $faq->keywords,
                    'search_keywords' => $faq->keywords,
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
            $infos = collect();

            if (
                Schema::hasTable('organization_infos')
                && Schema::hasColumn('organization_infos', 'organization_id')
                && Schema::hasColumn('organization_infos', 'title')
            ) {
                $query = OrganizationInfo::where('organization_id', $organization->id);
                if (Schema::hasColumn('organization_infos', 'is_active')) {
                    $query->where('is_active', true);
                }
                $infos = $query->get();
            }

            if ($infos->isEmpty()) {
                $infos = OrganizationData::where('organization_id', $organization->id)
                    ->where('type', 'info')
                    ->get();
            }
                
            if ($infos->isEmpty()) {
                $this->warn("No active infos found for {$organization->name}");
                return;
            }
            
            $items = $infos->map(function ($info) {
                $metadata = is_array($info->metadata ?? null) ? $info->metadata : [];
                $title = $info->title ?? $info->name ?? 'Info';
                $content = $info->content ?? $info->description ?? '';
                $category = $info->category ?? ($metadata['category'] ?? 'general');

                return [
                    'id' => "info_{$info->id}",
                    'title' => $title,
                    'content' => $content,
                    'category' => $category,
                    'keywords' => $metadata['keywords'] ?? $metadata['search_keywords'] ?? null,
                    'search_keywords' => $metadata['search_keywords'] ?? $metadata['keywords'] ?? null,
                    'metadata' => [
                        'table_id' => $info->id,
                        'keywords' => $metadata['keywords'] ?? null,
                        'search_keywords' => $metadata['search_keywords'] ?? null,
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
            $services = OrganizationData::where('organization_id', $organization->id)
                ->where('type', 'service')
                ->get();
                
            if ($services->isEmpty()) {
                $this->warn("No active services found for {$organization->name}");
                return;
            }
            
            $items = $services->map(function ($service) {
                $metadata = is_array($service->metadata ?? null) ? $service->metadata : [];
                $title = $service->name ?? $service->title ?? 'Service';
                $content = $service->description ?? $service->content ?? '';
                $category = $service->category ?? ($metadata['category'] ?? 'general');

                return [
                    'id' => "service_{$service->id}",
                    'title' => $title,
                    'content' => $content,
                    'category' => $category,
                    'keywords' => $metadata['keywords'] ?? $metadata['search_keywords'] ?? null,
                    'search_keywords' => $metadata['search_keywords'] ?? $metadata['keywords'] ?? null,
                    'metadata' => [
                        'table_id' => $service->id,
                        'price' => $service->price ?? ($metadata['price'] ?? null),
                        'keywords' => $metadata['keywords'] ?? null,
                        'search_keywords' => $metadata['search_keywords'] ?? null,
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

    private function syncProducts($organization, $aiService)
    {
        try {
            $products = OrganizationData::where('organization_id', $organization->id)
                ->where('type', 'product')
                ->get();

            if ($products->isEmpty()) {
                $this->warn("No products found for {$organization->name}");
                return;
            }

            $items = $products->map(function ($product) {
                $metadata = is_array($product->metadata ?? null) ? $product->metadata : 
                    (is_string($product->metadata) ? json_decode($product->metadata, true) ?? [] : []);

                $title   = $product->name ?? $metadata['name'] ?? 'Product';
                // Use the full stored content (name, price, artist, size, etc.)
                $content = $product->content ?? $product->description ?? '';
                $category = $product->category ?? ($metadata['category'] ?? '');

                // Build keyword string from metadata fields
                $keywordParts = array_filter([
                    strtolower($title),
                    isset($metadata['price'])  ? 'price ' . $metadata['price']  : null,
                    isset($metadata['artist']) ? $metadata['artist']             : null,
                    isset($metadata['medium']) ? $metadata['medium']             : null,
                    isset($metadata['style'])  ? $metadata['style']              : null,
                    isset($metadata['size'])   ? 'size ' . $metadata['size']     : null,
                    isset($metadata['color'])  ? $metadata['color']              : null,
                ]);
                $keywords = $metadata['keywords'] ?? $metadata['search_keywords'] ?? implode(', ', $keywordParts);

                return [
                    'id'              => $product->id,
                    'title'           => $title,
                    'content'         => $content,
                    'category'        => $category,
                    'keywords'        => $keywords,
                    'search_keywords' => $keywords,
                    'metadata'        => array_merge($metadata, [
                        'table_id'    => $product->id,
                        'updated_at'  => $product->updated_at->toISOString(),
                    ]),
                ];
            })->toArray();

            $this->info("Syncing " . count($items) . " products...");

            // Send in batches of 50 to avoid memory/timeout issues
            $chunks = array_chunk($items, 50);
            $totalSuccess = 0;
            $totalFailed  = 0;

            foreach ($chunks as $chunk) {
                $result = $aiService->storeDataToQdrant($organization->slug, 'product', $chunk);
                if ($result && ($result['success'] ?? false)) {
                    $totalSuccess += $result['successful_stores'] ?? 0;
                    $totalFailed  += $result['failed_stores']    ?? 0;
                    // Mark synced in DB
                    $ids = array_column($chunk, 'id');
                    OrganizationData::whereIn('id', $ids)->update([
                        'is_synced'      => true,
                        'last_synced_at' => now(),
                    ]);
                } else {
                    $totalFailed += count($chunk);
                    $this->warn("Chunk sync failed: " . json_encode($result));
                }
            }

            $this->info("✅ Products: {$totalSuccess}/" . count($items) . " successful");
            if ($totalFailed > 0) {
                $this->warn("⚠️ {$totalFailed} failed");
            }
        } catch (\Exception $e) {
            $this->error("❌ Product sync failed: " . $e->getMessage());
        }
    }
}
