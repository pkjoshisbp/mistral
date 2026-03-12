<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;
use Illuminate\Console\Command;

/**
 * Rebuilds the content string and cleans metadata for all product rows:
 *  - Strips junk additional_attributes (artist_price, tier_price_config_for_store, jd_trends_artist, prirority)
 *  - Keeps useful attributes: room, medium, most_purchased
 *  - Cleans categories to leaf names only (e.g. "Default Category/Paintings/Acrylic" → "Acrylic")
 *  - Strips HTML from short_description
 *  - Removes created_at / updated_at from content
 *  - Preserves price / special_price lines if already set
 *  - Marks rows as is_synced = false, then syncs to Qdrant in batches
 */
class CatalogCleanContent extends Command
{
    protected $signature = 'catalog:clean-content
                            {org_id : Organization ID}
                            {--dry-run : Preview first row output, exit without saving}
                            {--skip-qdrant : Update DB only, skip Qdrant re-sync}
                            {--only-qdrant : Skip DB cleanup, only sync unsynced rows to Qdrant}
                            {--batch=200 : DB chunk size}
                            {--qdrant-batch=20 : Records per Qdrant sync batch (keep low to avoid timeouts)}
                            {--qdrant-timeout=300 : Seconds per Qdrant batch request}';

    protected $description = 'Clean product content/metadata and re-sync to Qdrant (removes artist_price, full category paths, HTML, etc.)';

    // ── Keys to KEEP from additional_attributes ────────────────────────────────
    private const KEEP_AA_KEYS = ['room', 'medium', 'most_purchased', 'surface', 'style', 'size', 'orientation', 'color'];

    // ── Keys to ALWAYS DROP from additional_attributes ─────────────────────────
    private const DROP_AA_KEYS = [
        'artist_price', 'tier_price_config_for_store', 'jd_trends_artist',
        'prirority', 'priority',  // note: misspelling present in data
    ];

    private AiAgentService $aiService;

    public function handle(): int
    {
        $orgId      = $this->argument('org_id');
        $dryRun     = $this->option('dry-run');
        $skipQdrant = $this->option('skip-qdrant');
        $chunkSize     = (int) $this->option('batch');
        $qdrantBatch   = (int) $this->option('qdrant-batch');
        $qdrantTimeout = (int) $this->option('qdrant-timeout');
        $onlyQdrant    = $this->option('only-qdrant');

        $org = Organization::find($orgId);
        if (! $org) {
            $this->error("Organization $orgId not found.");
            return 1;
        }

        $this->aiService = new AiAgentService();

        // ── Mode: re-sync already-cleaned rows to Qdrant only ──────────────────
        if ($onlyQdrant) {
            $total = OrganizationData::where('organization_id', $orgId)
                ->where('type', 'product')->where('is_synced', false)->count();
            $this->info("Syncing {$total} unsynced products to Qdrant for {$org->name}…");

            $bar     = $this->output->createProgressBar($total);
            $syncBuf = [];

            OrganizationData::where('organization_id', $orgId)
                ->where('type', 'product')->where('is_synced', false)
                ->chunk($chunkSize, function ($rows) use (&$syncBuf, $bar, $qdrantBatch, $qdrantTimeout, $org) {
                    foreach ($rows as $row) {
                        $syncBuf[] = $row;
                        $bar->advance();
                        if (count($syncBuf) >= $qdrantBatch) {
                            $this->flushQdrant($org, $syncBuf, $qdrantTimeout);
                            $syncBuf = [];
                        }
                    }
                });

            if (count($syncBuf) > 0) {
                $this->flushQdrant($org, $syncBuf, $qdrantTimeout);
            }

            $bar->finish();
            $this->newLine();
            $this->info('✓ Qdrant sync complete.');
            return 0;
        }

        $total = OrganizationData::where('organization_id', $orgId)->where('type', 'product')->count();
        $this->info("Cleaning content for {$org->name} — {$total} products" . ($dryRun ? ' [DRY RUN]' : ''));

        if ($dryRun) {
            $sample = OrganizationData::where('organization_id', $orgId)->where('type', 'product')->first();
            if ($sample) {
                $this->line("\n=== BEFORE ===");
                $this->line($sample->content);
                $newContent = $this->buildContent($sample);
                $newMeta    = $this->cleanMetadataCsv($sample);
                $this->line("\n=== AFTER ===");
                $this->line($newContent);
                $this->line("\n=== CLEAN CSV KEYS ===");
                $this->line(implode(', ', array_keys($newMeta)));
            }
            $this->warn('Dry run — no changes saved.');
            return 0;
        }

        $bar     = $this->output->createProgressBar($total);
        $cleaned = 0;
        $syncBuf = [];

        OrganizationData::where('organization_id', $orgId)
            ->where('type', 'product')
            ->chunk($chunkSize, function ($rows) use (&$cleaned, &$syncBuf, $bar, $qdrantBatch, $qdrantTimeout, $skipQdrant, $org) {
                foreach ($rows as $row) {
                    $newContent = $this->buildContent($row);
                    $cleanCsv   = $this->cleanMetadataCsv($row);

                    $meta        = is_array($row->metadata) ? $row->metadata : [];
                    $meta['csv'] = $cleanCsv;

                    $row->content   = $newContent;
                    $row->metadata  = $meta;
                    $row->is_synced = false;
                    $row->save();

                    $cleaned++;
                    $syncBuf[] = $row;
                    $bar->advance();

                    if (! $skipQdrant && count($syncBuf) >= $qdrantBatch) {
                        $this->flushQdrant($org, $syncBuf, $qdrantTimeout);
                        $syncBuf = [];
                    }
                }
            });

        if (! $skipQdrant && count($syncBuf) > 0) {
            $this->flushQdrant($org, $syncBuf, $qdrantTimeout);
        }

        $bar->finish();
        $this->newLine();
        $this->info("✓ Cleaned $cleaned products" . ($skipQdrant ? ' (Qdrant sync skipped)' : ' + synced to Qdrant'));

        return 0;
    }

    // ── Build clean content string ─────────────────────────────────────────────

    private function buildContent(OrganizationData $row): string
    {
        $csv  = $row->metadata['csv'] ?? [];
        $name = $row->name;

        // --- Price (preserve if already set, e.g. by catalog:import-prices) ---
        $price        = trim($csv['price'] ?? '');
        $specialPrice = trim($csv['special_price'] ?? '');
        $priceLine    = '';
        if ($price !== '' && (float)$price > 0) {
            $priceLine = 'Price: ₹' . number_format((float)$price, 0, '.', ',');
            if ($specialPrice !== '' && (float)$specialPrice > 0) {
                $priceLine .= ' (Sale: ₹' . number_format((float)$specialPrice, 0, '.', ',') . ')';
            }
        }

        // --- Short description (strip HTML, collapse whitespace) ---
        $shortDesc = $this->cleanText($csv['short_description'] ?? '');

        // --- Description ---
        $desc = $this->cleanText($csv['description'] ?? '');

        // --- In stock ---
        $inStock = ($csv['is_in_stock'] ?? '1') === '1' ? 'Yes' : 'No';

        // --- Categories (leaf names only, deduplicated) ---
        $categories = $this->cleanCategories($csv['categories'] ?? '');

        // --- Additional attributes (useful keys only) ---
        $usefulAttrs = $this->parseUsefulAttributes($csv['additional_attributes'] ?? '');

        // --- Build lines ---
        $lines = ["Name: {$name}"];
        if ($priceLine)  $lines[] = $priceLine;
        if ($shortDesc)  $lines[] = "Short Description: {$shortDesc}";
        if ($desc)       $lines[] = "Description: {$desc}";
        if ($categories) $lines[] = "Categories: {$categories}";

        foreach ($usefulAttrs as $key => $val) {
            $label   = ucwords(str_replace('_', ' ', $key));
            $lines[] = $label . ': ' . $val;
        }

        $lines[] = "In Stock: {$inStock}";

        return implode("\n", $lines);
    }

    // ── Clean metadata csv array ───────────────────────────────────────────────

    private function cleanMetadataCsv(OrganizationData $row): array
    {
        $csv = $row->metadata['csv'] ?? [];

        // Strip junk from additional_attributes
        $cleanAA = $this->rebuildCleanAdditionalAttributes($csv['additional_attributes'] ?? '');

        return array_filter([
            'sku'                   => $csv['sku'] ?? '',
            'categories'            => $this->cleanCategories($csv['categories'] ?? ''),
            'short_description'     => $this->cleanText($csv['short_description'] ?? ''),
            'price'                 => $csv['price'] ?? '',
            'special_price'         => $csv['special_price'] ?? '',
            'is_in_stock'           => $csv['is_in_stock'] ?? '1',
            'additional_attributes' => $cleanAA,
            // created_at / updated_at / description intentionally omitted
        ], fn($v) => $v !== null && $v !== '');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Strip HTML, decode entities, collapse whitespace.
     */
    private function cleanText(string $text): string
    {
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Convert full category paths to deduplicated leaf names.
     * "Default Category/Paintings/Medium/Acrylic" → "Acrylic"
     * Returns comma-separated string.
     */
    private function cleanCategories(string $raw): string
    {
        if (! $raw) return '';

        $paths  = explode(',', $raw);
        $leaves = [];

        foreach ($paths as $path) {
            $segments = explode('/', trim($path));
            // Skip "Default Category" root-only entries
            $segments = array_filter($segments, fn($s) => trim($s) !== '' && strtolower(trim($s)) !== 'default category');
            if (empty($segments)) continue;

            $leaf = trim(end($segments));
            if ($leaf && ! in_array($leaf, $leaves, true)) {
                $leaves[] = $leaf;
            }
        }

        return implode(', ', $leaves);
    }

    /**
     * Parse additional_attributes string, return only KEEP_AA_KEYS as [key => value].
     */
    private function parseUsefulAttributes(string $aa): array
    {
        $result = [];
        if (! $aa) return $result;

        // Format: key="value",key2="value2"
        preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $aa, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $key = strtolower($m[1]);
            $val = trim($m[2]);

            if (in_array($key, self::DROP_AA_KEYS, true)) continue;
            if (! in_array($key, self::KEEP_AA_KEYS, true)) continue;
            if ($val === '' || $val === '0') continue;

            $result[$key] = $val;
        }

        return $result;
    }

    /**
     * Return a cleaned additional_attributes string (junk keys removed).
     */
    private function rebuildCleanAdditionalAttributes(string $aa): string
    {
        if (! $aa) return '';

        $useful = $this->parseUsefulAttributes($aa);
        if (empty($useful)) return '';

        $parts = [];
        foreach ($useful as $k => $v) {
            $parts[] = $k . '="' . $v . '"';
        }

        return implode(',', $parts);
    }

    // ── Qdrant sync (mirrors ImportOrganizationCsvData pattern) ───────────────

    private function buildQdrantItem(OrganizationData $row): array
    {
        $meta = is_array($row->metadata) ? $row->metadata : [];
        $csv  = $meta['csv'] ?? [];

        $price        = $csv['price'] ?? '';
        $specialPrice = $csv['special_price'] ?? '';

        $qdrantMeta = [
            'table_id'     => $row->id,
            'updated_at'   => optional($row->updated_at)->toISOString(),
            'source'       => 'csv_import',
            'dataset'      => $meta['dataset'] ?? null,
            'external_key' => $meta['external_key'] ?? null,
            'type'         => $row->type,
        ];

        if ($price !== '' && (float)$price > 0) {
            $qdrantMeta['price']     = $price;
            $qdrantMeta['price_inr'] = (int)$price;
        }
        if ($specialPrice !== '' && (float)$specialPrice > 0) {
            $qdrantMeta['special_price'] = $specialPrice;
        }

        $sizeSignals = $meta['size_signals'] ?? [];
        if (! empty($sizeSignals['pairs'])) {
            $qdrantMeta['size_pairs']    = $sizeSignals['pairs'];
            $qdrantMeta['size_primary']  = $sizeSignals['primary'] ?? $sizeSignals['pairs'][0];
            if (! empty($sizeSignals['orientation'])) {
                $qdrantMeta['size_orientation'] = $sizeSignals['orientation'];
            }
        }

        return [
            'id'       => ($meta['qdrant_type'] ?? 'info') . '_' . $row->id,
            'title'    => $row->name,
            'content'  => $row->content,
            'category' => $meta['category'] ?? 'general',
            'metadata' => $qdrantMeta,
        ];
    }

    private function flushQdrant(Organization $org, array $rows, int $timeout = 300): void
    {
        $items      = array_map(fn($r) => $this->buildQdrantItem($r), $rows);
        $qdrantType = data_get($rows[0]->metadata, 'qdrant_type', 'info');

        try {
            $result  = $this->aiService->updateDataToQdrant($org->slug, $qdrantType, $items, $timeout);
            // FastAPI returns {"successful_stores": N, "failed_stores": [...]} — not a "success" key
            $success = is_array($result) && ($result['successful_stores'] ?? 0) > 0;

            if ($success) {
                $ids = array_map(fn($r) => $r->id, $rows);
                OrganizationData::whereIn('id', $ids)->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);
            } else {
                $this->warn('  ⚠ Qdrant batch failed.');
            }
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Qdrant error: ' . $e->getMessage());
        }
    }
}
