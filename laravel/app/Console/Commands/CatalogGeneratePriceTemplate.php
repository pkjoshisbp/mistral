<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CatalogGeneratePriceTemplate extends Command
{
    protected $signature = 'catalog:generate-price-template
                            {org_id : Organization ID}
                            {--output= : Output file path (default: storage/app/price_templates/{slug}_prices.csv)}
                            {--only-missing : Only include products with no price set}';

    protected $description = 'Generate a price template CSV (SKU, name, current price) for bulk price entry';

    public function handle(): int
    {
        $orgId = $this->argument('org_id');
        $org   = Organization::find($orgId);

        if (! $org) {
            $this->error("Organization $orgId not found.");
            return 1;
        }

        $onlyMissing = $this->option('only-missing');

        $this->info("Generating price template for: {$org->name}");

        // Build output path
        $outputOption = $this->option('output');
        if ($outputOption) {
            $outputPath = $outputOption;
        } else {
            $dir = storage_path('app/price_templates');
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $outputPath = $dir . '/' . $org->slug . '_prices.csv';
        }

        $query = OrganizationData::where('organization_id', $orgId)
            ->where('type', 'product')
            ->orderBy('name');

        $total   = $query->count();
        $written = 0;
        $skipped = 0;

        $fh = fopen($outputPath, 'w');
        if (! $fh) {
            $this->error("Cannot write to: $outputPath");
            return 1;
        }

        // Header row
        fputcsv($fh, [
            'db_id',            // Internal DB id — use when re-importing (fastest lookup)
            'sku',              // SKU from original catalog
            'name',             // Product name
            'price',            // FILL THIS: Retail price in INR (e.g. 26000)
            'special_price',    // FILL THIS: Sale price (leave empty if no sale)
        ]);

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(200, function ($rows) use ($fh, $onlyMissing, &$written, &$skipped, $bar) {
            foreach ($rows as $row) {
                $meta = is_array($row->metadata) ? $row->metadata : [];
                $csv  = $meta['csv'] ?? [];

                $sku          = $csv['sku'] ?? '';
                $currentPrice = $csv['price'] ?? '';
                $specialPrice = $csv['special_price'] ?? '';

                // Skip if already has price and --only-missing is set
                if ($onlyMissing && $currentPrice !== '' && $currentPrice !== '0' && $currentPrice !== '0.0000') {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                fputcsv($fh, [
                    $row->id,
                    $sku,
                    $row->name,
                    $currentPrice,  // empty for most rows
                    $specialPrice,
                ]);
                $written++;
                $bar->advance();
            }
        });

        $bar->finish();
        fclose($fh);

        $this->newLine();
        $this->info("✓ Written $written products" . ($skipped ? " (skipped $skipped already-priced)" : ''));
        $this->info("  Output: $outputPath");
        $this->line('');
        $this->line('Next steps:');
        $this->line('  1. Open the CSV and fill in the <price> column with retail prices in INR');
        $this->line('  2. Leave <special_price> empty unless the product is on sale');
        $this->line('  3. Do NOT modify <db_id>, <sku>, or <name> columns');
        $this->line('  4. Run: php artisan catalog:import-prices ' . $outputPath . ' ' . $this->argument('org_id'));

        return 0;
    }
}
