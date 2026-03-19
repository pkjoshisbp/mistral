<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Organization;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;
use App\Services\QdrantCanonicalizationService;
use Illuminate\Support\Facades\Log;

class ResyncFaqsToQdrant extends Command
{
    protected $signature = 'faq:resync {organization? : Organization slug or ID} {--dry-run : Only log actions, do not write} {--skip-canonicalize : Skip canonical point-id cleanup after sync}';
    protected $description = 'Re-index FAQs to Qdrant with safe fallbacks; skips empty content. Optionally scope to one organization.';

    public function handle()
    {
        $orgArg = $this->argument('organization');
        $dryRun = (bool) $this->option('dry-run');
        $skipCanonicalize = (bool) $this->option('skip-canonicalize');
        $ai = new AiAgentService();
        $canonicalizer = app(QdrantCanonicalizationService::class);

        $orgs = Organization::query();
        if ($orgArg) {
            $orgs->where(function($q) use ($orgArg) {
                $q->where('id', $orgArg)->orWhere('slug', $orgArg);
            });
        }

        $countOrgs = 0; $countFaqs = 0; $countSynced = 0; $countSkipped = 0;
        foreach ($orgs->get() as $org) {
            $countOrgs++;
            $this->info("Processing organization: {$org->slug} ({$org->id})");

            $faqs = OrganizationFaq::where('organization_id', $org->id)->orderBy('id')->get();
            foreach ($faqs as $faq) {
                $countFaqs++;
                $content = trim((string) $faq->plain_text_with_links);
                if ($content === '') {
                    $countSkipped++;
                    $this->warn("- Skipping faq_{$faq->id} (empty content)");
                    continue;
                }

                $items = [[
                    'id' => "faq_{$faq->id}",
                    'title' => $faq->question,
                    'content' => $content,
                    'category' => $faq->category ?? 'general',
                    'follow_up' => $faq->follow_up ?? null,
                    'metadata' => [
                        'table_id' => $faq->id,
                        'updated_at' => $faq->updated_at ? $faq->updated_at->toISOString() : now()->toISOString(),
                        'keywords' => $faq->keywords,
                        'links' => method_exists($faq, 'getLinksAttribute') ? $faq->links : []
                    ]
                ]];

                if ($dryRun) {
                    $this->line("- Would upsert faq_{$faq->id}: " . substr($content, 0, 60) . '...');
                    continue;
                }

                $result = $ai->storeDataToQdrant($org->slug, 'faq', $items);
                if ($result && ($result['successful_stores'] ?? 0) > 0) {
                    $countSynced++;
                    $this->info("- Upserted faq_{$faq->id}");
                } else {
                    $this->error("- Failed to upsert faq_{$faq->id}");
                    Log::warning('Resync FAQ upsert failed', [
                        'org' => $org->slug,
                        'faq_id' => $faq->id,
                        'result' => $result
                    ]);
                }
            }

            if (! $dryRun && ! $skipCanonicalize) {
                $cleanup = $canonicalizer->run($org->slug, true);
                $cleanupSummary = $cleanup['summary'] ?? [];
                $this->info("Canonicalized {$org->slug}: migrated {$cleanupSummary['migrated_groups']} groups, deleted {$cleanupSummary['deleted_points']} stale point ids, skipped {$cleanupSummary['skipped_groups']} groups.");
            }
        }

        $this->info("Done. Orgs: {$countOrgs}, FAQs scanned: {$countFaqs}, synced: {$countSynced}, skipped (empty): {$countSkipped}");
        return self::SUCCESS;
    }
}
