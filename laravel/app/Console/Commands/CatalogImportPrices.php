<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Services\AiAgentService;
use Illuminate\Console\Command;

class CatalogImportPrices extends Command
{
    protected $signature = 'catalog:import-prices
                            {file : Path to the price CSV file}
                            {org_id : Organization ID}
                            {--dry-run : Preview changes without saving}
                            {--skip-qdrant : Update DB only, skip Qdrant re-sync}
                            {--batch=100 : Records per Qdrant sync batch}';

    protected $description = 'Import prices from a price CSV and sync updated products to Qdrant';

    private AiAgentService $aiService;

    public function handle(): int
    {
        $file       = $this->argument('file');
        $orgId      = $this->argument('org_id');
        $dryRun     = $this->option('dry-run');
        $skipQdrant = $this->option('skip-qdrant');
        $batchSize  = (int) $this->option('batch');

        $org = Organization::find($orgId);
        if (! $org) {
            $this->error("Organization $orgId not found.");
            return 1;
        }

        if (! file_exists($file)) {
            $this->error("File not found: $file");
            return 1;
        }

        $this->aiService = new AiAgentService();

        $this->info("Importing prices for: {$org->name}" . ($dryRun ? ' [DRY RUN]' : ''));

        $fh = fopen($file, 'r');
        $headerRow = fgetcsv($fh);
        if (! $headerRow) {
            $this->error("Empty or invalid CSV file.");
            fclose($fh);
            return 1;
        }

        $headers  = array_map(fn($h) => strtolower(trim($h)), $headerRow);
        $dbIdIdx  = array_search('db_id', $headers);
        $skuIdx   = array_search('sku', $headers);
        $priceIdx = array_search('price', $headers);
        $spIdx    = array_search('special_price', $headers);

        if ($priceIdx === false) {
            $this->error("CSV must have a 'price' column.");
            fclose($fh);
            return 1;
        }
        if ($dbIdIdx === false && $skuIdx === false) {
            $this->error("CSV must have either a 'db_id' or 'sku' column for matching.");
            fclose($fh);
            return 1;
        }

        $updated     = 0;
        $skipped     = 0;
        $notFound    = 0;
        $updatedRows = [];

        while (($row = fgetcsv($fh)) !== false) {
            if (empty(array_filter($row))) continue;

            $dbId         = ($dbIdIdx !== false) ? ($row[$dbIdIdx] ?? '') : null;
            $sku          = ($skuIdx  !== false) ? ($row[$skuIdx]  ?? '') : null;
            $price        = preg_replace('/[^\d\.]/', '', trim($row[$priceIdx] ?? ''));
            $specialPrice = ($spIdx !== false) ? preg_replace('/[^\d\.]/', '', trim($row[$spIdx] ?? '')) : '';

            if ($price === '' || (float)$price === 0.0) {
                $skipped++;
                continue;
            }

            $record = null;
            if ($dbId && is_numeric($dbId)) {
                $record = OrganizationData::where('organization_id', $orgId)
                    ->where('id', (int) $dbId)->first();
            }
            if (! $record && $sku) {
                $record = OrganizationData::where('organization_id', $orgId)
                    ->where('type', 'product')
                    ->where(function ($q) use ($sku) {
                        $q->where('name', $sku)
                          ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.sku')) = ?", [$sku]);
                    })->first();
            }

            if (! $record) {
                $notFound++;
                $this->line("  <comment>NOT FOUND:</comment> db_id=$dbId sku=$sku");
                continue;
            }

            if ($dryRun) {
                $cur = $record->metadata['csv']['price'] ?? '(empty)';
                $this->line("  [DRY] #{$record->id} {$record->name} | was:₹{$cur} → ₹{$price}" . ($specialPrice ? " sale:₹{$specialPrice}" : ''));
                $updated++;
                continue;
            }

            // Update metadata
            $meta = is_array($record->metadata) ? $record->metadata : [];
            if (! isset($meta['csv'])) $meta['csv'] = [];
            $meta['csv']['price']         = $price;
            $meta['csv']['special_price'] = $specialPrice;

            // Update content string
            $content   = $record->content ?? '';
            $priceText = "Price: ₹" . number_format((float) $price, 0, '.', ',');
            if ($specialPrice) {
                $priceText .= " (Sale: ₹" . number_format((float) $specialPrice, 0, '.', ',') . ")";
            }
            if (preg_match('/^Price:.*$/m', $content)) {
                $content = preg_replace('/^Price:.*$/m', $priceText, $content);
            } else {
                $lines = explode("\n", $content);
                array_splice($lines, 1, 0, [$priceText]);
                $content = implode("\n", $lines);
            }

            $record->metadata  = $meta;
            $record->content   = $content;
            $record->is_synced = false;
            $record->save();

            $updated++;
            $updatedRows[] = $record;

            if (! $skipQdrant && count($updatedRows) >= $batchSize) {
                $this->flushToQdrant($org, $updatedRows);
                $updatedRows = [];
            }
        }

        fclose($fh);

        if (! $skipQdrant && ! $dryRun && count($updatedRows) > 0) {
            $this->flushToQdrant($org, $updatedRows);
        }

        $this->newLine();
        $this->info("Done!");
        $this->table(['Status', 'Count'], [
            ['Updated',            $updated],
            ['Skipped (no price)', $skipped],
            ['Not found in DB',    $notFound],
        ]);

        if ($dryRun) {
            $this->warn('Dry run — no changes were saved.');
        } elseif ($skipQdrant) {
            $this->warn("Qdrant sync skipped. Run: php artisan sync:organization-data $orgId --type=all");
        }

        return 0;
    }

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

        if ($price !== '' && (float) $price > 0) {
            $qdrantMeta['price']     = $price;
            $qdrantMeta['price_inr'] = (int) $price;
        }
        if ($specialPrice !== '' && (float) $specialPrice > 0) {
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
            'content'  => $row->content ?: ($row->description ?? ''),
            'category' => $meta['category'] ?? 'general',
            'metadata' => $qdrantMeta,
        ];
    }

    private function flushToQdrant(Organization $org, array $rows): void
    {
        $this->line("  Syncing " . count($rows) . " products to Qdrant…");
        $items      = array_map(fn($r) => $this->buildQdrantItem($r), $rows);
        $qdrantType = data_get($rows[0]->metadata, 'qdrant_type', 'info');

        try {
            $result  = $this->aiService->updateDataToQdrant($org->slug, $qdrantType, $items);
            $success = (bool) ($result['success'] ?? false);
            $count   = (int)  ($result['successful_stores'] ?? count($items));

            if ($success) {
                $this->line("    ✓ Qdrant: {$count} synced");
                $ids = array_map(fn($r) => $r->id, $rows);
                OrganizationData::whereIn('id', $ids)->update([
                    'is_synced'      => true,
                    'last_synced_at' => now(),
                ]);
            } else {
                $this->warn("    ⚠ Qdrant sync reported failure for this batch.");
            }
        } catch (\Throwable $e) {
            $this->warn("    ⚠ Qdrant error: " . $e->getMessage());
        }
    }
}
