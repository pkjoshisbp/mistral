<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\QdrantCanonicalizationService;

class CanonicalizeQdrantPoints extends Command
{
    protected $signature = 'qdrant:canonicalize-points
                            {collection? : Optional Qdrant collection name}
                            {--apply : Upsert canonical ids and delete stale points}
                            {--per-page=500 : Scroll page size for Qdrant scans}';

    protected $description = 'Canonicalize Qdrant point ids across collections, dedupe duplicate item_ids, and keep the newest payload by updated_at.';

    public function handle(): int
    {
        $collectionFilter = $this->argument('collection');
        $apply = (bool) $this->option('apply');
        $perPage = max(50, (int) $this->option('per-page'));

        $result = app(QdrantCanonicalizationService::class)->run($collectionFilter, $apply, $perPage);
        $collections = $result['collections'] ?? [];
        $summary = $result['summary'] ?? [];

        if ($collections === []) {
            $this->warn('No Qdrant collections found to process.');

            return self::SUCCESS;
        }

        $this->info($apply
            ? 'Applying canonical point-id migration and duplicate cleanup.'
            : 'Dry run only. Use --apply to write canonical ids and delete stale points.');

        foreach ($collections as $collection) {
            $this->newLine();
            $this->info("Collection: {$collection['name']}");
            $this->line("  points: {$collection['points']} | item groups: {$collection['groups']} | duplicate groups: {$collection['duplicate_groups']} | duplicate points: {$collection['duplicate_points']} | noncanonical singletons: {$collection['noncanonical_singletons']} | skipped points: {$collection['skipped_points']}");

            foreach ($collection['issues'] as $issue) {
                $pointIds = implode(', ', $issue['point_ids']);
                $this->line("  - {$issue['item_id']}: ids=[{$pointIds}] => canonical={$issue['canonical_id']}, source={$issue['source_id']}, updated_at={$issue['updated_at']}");

                if ($apply && $issue['skip_reason']) {
                    $this->warn("    skipped: {$issue['skip_reason']}");
                }
            }
        }

        $this->newLine();
        $this->info('Summary');
        $this->line("  collections: {$summary['collections']}");
        $this->line("  points scanned: {$summary['points']}");
        $this->line("  issue groups: {$summary['issue_groups']}");
        $this->line("  duplicate groups: {$summary['duplicate_groups']}");
        $this->line("  duplicate points: {$summary['duplicate_points']}");
        $this->line("  noncanonical singletons: {$summary['noncanonical_singletons']}");

        if ($apply) {
            $this->line("  migrated groups: {$summary['migrated_groups']}");
            $this->line("  deleted stale points: {$summary['deleted_points']}");
            $this->line("  skipped groups: {$summary['skipped_groups']}");
        }

        return self::SUCCESS;
    }
}