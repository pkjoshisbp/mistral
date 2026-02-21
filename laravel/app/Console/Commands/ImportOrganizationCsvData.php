<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\OrganizationData;
use App\Models\OrganizationFaq;
use App\Services\AiAgentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportOrganizationCsvData extends Command
{
    protected $signature = 'orgdata:import-csv
        {organization : Organization ID or slug}
        {file : CSV file path (absolute or relative to laravel/)}
        {--dataset= : Logical dataset name (default: file name without extension)}
        {--type=info : Organization data type label (e.g. info, pricing, product, faq)}
        {--qdrant-type=info : Qdrant data type bucket (faq|info|service)}
        {--key-columns= : Comma-separated columns used as stable unique key}
        {--name-columns= : Comma-separated columns to build name/title}
        {--description-columns= : Comma-separated columns to build description}
        {--content-columns= : Comma-separated columns to build searchable content (default: all columns)}
        {--category-column= : Column to read category from}
        {--default-category=general : Fallback category when category-column is empty}
        {--qdrant-batch-size=50 : Qdrant upsert batch size}
        {--qdrant-timeout=180 : Qdrant upsert timeout (seconds) per batch}
        {--qdrant-retries=2 : Retry attempts per failed Qdrant batch}
        {--force-qdrant : Re-index unchanged rows to Qdrant for this dataset}
        {--skip-qdrant : Only sync DB, skip Qdrant upsert/delete}
        {--dry-run : Parse and diff only, no DB/Qdrant writes}';

    protected $description = 'Generic CSV import for any organization with deterministic upsert/delete + optional Qdrant sync';

    public function handle(): int
    {
        $transformVersion = 2;
        $organizationArg = (string) $this->argument('organization');
        $fileArg = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');
        $skipQdrant = (bool) $this->option('skip-qdrant');
        $forceQdrant = (bool) $this->option('force-qdrant');
        $qdrantBatchSize = max(1, (int) $this->option('qdrant-batch-size'));
        $qdrantTimeout = max(30, (int) $this->option('qdrant-timeout'));
        $qdrantRetries = max(0, (int) $this->option('qdrant-retries'));

        $organization = Organization::query()
            ->where('id', $organizationArg)
            ->orWhere('slug', $organizationArg)
            ->first();

        if (!$organization) {
            $this->error('Organization not found: ' . $organizationArg);
            return self::FAILURE;
        }

        $csvPath = $this->resolveCsvPath($fileArg);
        if (!$csvPath) {
            $this->error('CSV file not found: ' . $fileArg);
            return self::FAILURE;
        }

        $dataset = trim((string) ($this->option('dataset') ?: pathinfo($csvPath, PATHINFO_FILENAME)));
        $dataType = trim((string) $this->option('type')) ?: 'info';
        $qdrantType = trim((string) $this->option('qdrant-type')) ?: 'info';

        if (!in_array($qdrantType, ['faq', 'info', 'service'], true)) {
            $this->error('Invalid --qdrant-type. Allowed values: faq, info, service');
            return self::FAILURE;
        }

        $keyColumns = $this->parseCsvList((string) $this->option('key-columns'));
        $nameColumns = $this->parseCsvList((string) $this->option('name-columns'));
        $descriptionColumns = $this->parseCsvList((string) $this->option('description-columns'));
        $contentColumns = $this->parseCsvList((string) $this->option('content-columns'));
        $categoryColumn = $this->normalizeHeader((string) $this->option('category-column'));
        $defaultCategory = trim((string) $this->option('default-category')) ?: 'general';

        $rows = $this->readCsvRows($csvPath);
        if (empty($rows)) {
            $this->error('CSV contains no usable rows.');
            return self::FAILURE;
        }

        $headers = array_keys($rows[0]);

        if (empty($keyColumns)) {
            $keyColumns = $this->inferKeyColumns($headers);
        }

        if (empty($nameColumns)) {
            $nameColumns = $this->inferNameColumns($headers);
        }

        $incoming = [];
        foreach ($rows as $row) {
            $externalKey = $this->buildExternalKey($row, $keyColumns);
            if ($externalKey === '') {
                continue;
            }

            $name = $this->joinColumns($row, $nameColumns, ' | ');
            if ($name === '') {
                $name = 'CSV Row ' . substr($externalKey, 0, 8);
            }

            $description = $this->joinColumns($row, $descriptionColumns, ' | ');
            $content = $this->buildContent($row, $contentColumns);
            $category = $defaultCategory;
            if ($categoryColumn !== '' && !empty($row[$categoryColumn])) {
                $category = trim((string) $row[$categoryColumn]);
            }

            $sizeSignals = $this->extractSizeSignals($row, $name, $description, $content);
            if (!empty($sizeSignals['pairs'])) {
                $sizeLine = 'Size options (inches): ' . implode(', ', $sizeSignals['pairs']);
                if (!str_contains($content, $sizeLine)) {
                    $content = trim($content . "\n" . $sizeLine);
                }
                if (!empty($sizeSignals['primary'])) {
                    $primaryLine = 'Primary size (inches): ' . $sizeSignals['primary'];
                    if (!str_contains($content, $primaryLine)) {
                        $content = trim($content . "\n" . $primaryLine);
                    }
                }
                if (!empty($sizeSignals['orientation'])) {
                    $orientationLine = 'Orientation: ' . $sizeSignals['orientation'];
                    if (!str_contains($content, $orientationLine)) {
                        $content = trim($content . "\n" . $orientationLine);
                    }
                }
            }

            $rowHash = sha1(json_encode($row, JSON_UNESCAPED_UNICODE));

            $incoming[$externalKey] = [
                'external_key' => $externalKey,
                'name' => $name,
                'description' => $description,
                'content' => $content,
                'category' => $category,
                'metadata' => [
                    'source' => 'csv_import',
                    'dataset' => $dataset,
                    'type' => $dataType,
                    'qdrant_type' => $qdrantType,
                    'category' => $category,
                    'external_key' => $externalKey,
                    'source_file' => basename($csvPath),
                    'row_hash' => $rowHash,
                    'transform_version' => $transformVersion,
                    'key_columns' => $keyColumns,
                    'size_signals' => $sizeSignals,
                    'csv' => $row,
                ],
            ];
        }

        if (empty($incoming)) {
            $this->error('No valid rows found after parsing + key generation.');
            return self::FAILURE;
        }

        $existingRows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', $dataType)
            ->get();

        $existingMap = [];
        foreach ($existingRows as $existing) {
            $source = data_get($existing->metadata, 'source');
            $existingDataset = data_get($existing->metadata, 'dataset');
            $externalKey = data_get($existing->metadata, 'external_key');

            if ($source === 'csv_import' && $existingDataset === $dataset && is_string($externalKey) && $externalKey !== '') {
                $existingMap[$externalKey] = $existing;
            }
        }

        $toCreate = [];
        $toUpdate = [];
        $unchanged = 0;
        $unchangedRows = [];

        foreach ($incoming as $externalKey => $data) {
            $existing = $existingMap[$externalKey] ?? null;
            if (!$existing) {
                $toCreate[$externalKey] = $data;
                continue;
            }

            $existingHash = (string) data_get($existing->metadata, 'row_hash', '');
            $incomingHash = (string) data_get($data, 'metadata.row_hash', '');
            $existingTransformVersion = (int) data_get($existing->metadata, 'transform_version', 1);
            $incomingTransformVersion = (int) data_get($data, 'metadata.transform_version', 1);

            if ($existingHash !== '' && $existingHash === $incomingHash && $existingTransformVersion === $incomingTransformVersion) {
                $unchanged++;
                $unchangedRows[] = $existing;
                continue;
            }

            $toUpdate[$externalKey] = [$existing, $data];
        }

        $toDelete = [];
        foreach ($existingMap as $externalKey => $existing) {
            if (!isset($incoming[$externalKey])) {
                $toDelete[] = $existing;
            }
        }

        $this->info('CSV import diff summary for ' . $organization->slug . ' [' . $dataset . ' | type=' . $dataType . ']:');
        $this->line('Incoming rows: ' . count($incoming));
        $this->line('Create: ' . count($toCreate));
        $this->line('Update: ' . count($toUpdate));
        $this->line('Delete: ' . count($toDelete));
        $this->line('Unchanged: ' . $unchanged);

        if ($dryRun) {
            $this->warn('Dry run enabled. No DB or Qdrant changes were made.');
            return self::SUCCESS;
        }

        $upsertedRows = [];
        $deletePointIds = [];
        $faqMirrorResult = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($organization, $dataType, $qdrantType, $toCreate, $toUpdate, $toDelete, &$upsertedRows, &$deletePointIds): void {
            foreach ($toCreate as $data) {
                $row = OrganizationData::create([
                    'organization_id' => $organization->id,
                    'type' => $dataType,
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'content' => $data['content'],
                    'metadata' => $data['metadata'],
                    'is_synced' => false,
                    'last_synced_at' => null,
                ]);

                $upsertedRows[] = $row;
            }

            foreach ($toUpdate as [$existing, $data]) {
                $existing->update([
                    'name' => $data['name'],
                    'description' => $data['description'],
                    'content' => $data['content'],
                    'metadata' => $data['metadata'],
                    'is_synced' => false,
                    'last_synced_at' => null,
                ]);

                $upsertedRows[] = $existing->fresh();
            }

            foreach ($toDelete as $existing) {
                $deletePointIds[] = $qdrantType . '_' . $existing->id;
                $existing->delete();
            }
        });

        if ($dataType === 'faq') {
            $faqMirrorResult = $this->syncFaqMirror($organization->id, $incoming);
            $this->info('Customer FAQ mirror: created ' . $faqMirrorResult['created'] . ', updated ' . $faqMirrorResult['updated'] . ', skipped ' . $faqMirrorResult['skipped']);
        }

        if (!$skipQdrant) {
            $aiService = new AiAgentService();

            $rowsForQdrant = $upsertedRows;
            if ($forceQdrant && !empty($unchangedRows)) {
                $rowsForQdrant = array_merge($rowsForQdrant, $unchangedRows);
                $this->line('Force Qdrant enabled. Re-indexing unchanged rows: ' . count($unchangedRows));
            }

            if (!empty($rowsForQdrant)) {
                $items = array_map(function (OrganizationData $row) use ($defaultCategory): array {
                    $csvRow = data_get($row->metadata, 'csv', []);
                    $model = trim((string) data_get($csvRow, 'model', ''));
                    $variant = trim((string) data_get($csvRow, 'variant', ''));
                    $exShowroomPrice = trim((string) data_get($csvRow, 'ex_showroom_price_inr', ''));
                    $onRoadPrice = trim((string) data_get($csvRow, 'approx_on_road_price_inr', ''));

                    $metadata = [
                        'table_id' => $row->id,
                        'updated_at' => optional($row->updated_at)->toISOString(),
                        'source' => 'csv_import',
                        'dataset' => data_get($row->metadata, 'dataset'),
                        'external_key' => data_get($row->metadata, 'external_key'),
                        'type' => $row->type,
                    ];

                    if ($model !== '') {
                        $metadata['model'] = $model;
                    }
                    if ($variant !== '') {
                        $metadata['variant'] = $variant;
                    }
                    if ($exShowroomPrice !== '') {
                        $metadata['ex_showroom_price_inr'] = $exShowroomPrice;
                    }
                    if ($onRoadPrice !== '') {
                        $metadata['approx_on_road_price_inr'] = $onRoadPrice;
                    }
                    $sizeSignals = data_get($row->metadata, 'size_signals', []);
                    if (!empty($sizeSignals['pairs'])) {
                        $metadata['size_pairs'] = $sizeSignals['pairs'];
                        $metadata['size_primary'] = $sizeSignals['primary'] ?? $sizeSignals['pairs'][0];
                        if (!empty($sizeSignals['orientation'])) {
                            $metadata['size_orientation'] = $sizeSignals['orientation'];
                        }
                    }

                    return [
                        'id' => data_get($row->metadata, 'qdrant_type', 'info') . '_' . $row->id,
                        'title' => $row->name,
                        'content' => $row->content ?: ($row->description ?? ''),
                        'category' => data_get($row->metadata, 'category', $defaultCategory),
                        'metadata' => $metadata,
                    ];
                }, $rowsForQdrant);

                $chunks = array_chunk($items, $qdrantBatchSize);
                $qdrantTypeForRows = data_get($rowsForQdrant[0]->metadata, 'qdrant_type', 'info');
                $syncedRowIds = [];
                $totalSuccess = 0;
                $totalFailed = 0;

                foreach ($chunks as $index => $chunk) {
                    $attempt = 0;
                    $chunkSuccess = false;
                    $result = null;

                    while ($attempt <= $qdrantRetries && !$chunkSuccess) {
                        $attempt++;
                        $result = $aiService->updateDataToQdrant($organization->slug, $qdrantTypeForRows, $chunk, $qdrantTimeout);
                        $chunkSuccess = (bool) ($result['success'] ?? false);

                        if (!$chunkSuccess && $attempt <= $qdrantRetries) {
                            $this->warn('Qdrant batch ' . ($index + 1) . '/' . count($chunks) . ' failed (attempt ' . $attempt . '). Retrying...');
                        }
                    }

                    if ($chunkSuccess) {
                        $count = (int) ($result['successful_stores'] ?? count($chunk));
                        $totalSuccess += $count;
                        foreach ($chunk as $item) {
                            $pointId = (string) ($item['id'] ?? '');
                            if (preg_match('/^[a-z]+_(\d+)$/i', $pointId, $m)) {
                                $syncedRowIds[] = (int) $m[1];
                            }
                        }
                        $this->line('Qdrant batch ' . ($index + 1) . '/' . count($chunks) . ' synced (' . $count . ' items).');
                    } else {
                        $failedCount = count($chunk);
                        $totalFailed += $failedCount;
                        $this->error('Qdrant batch ' . ($index + 1) . '/' . count($chunks) . ' failed after retries (' . $failedCount . ' items).');
                    }
                }

                if (!empty($syncedRowIds)) {
                    OrganizationData::query()
                        ->whereIn('id', array_values(array_unique($syncedRowIds)))
                        ->update([
                            'is_synced' => true,
                            'last_synced_at' => now(),
                        ]);
                }

                $this->info('Qdrant upsert summary: success=' . $totalSuccess . ', failed=' . $totalFailed . ', total=' . count($items));
            }

            if (!empty($deletePointIds)) {
                $deleteResult = $aiService->deleteDataFromQdrant($organization->slug, $deletePointIds);
                if (!$deleteResult || !($deleteResult['success'] ?? false)) {
                    $this->error('Qdrant delete failed for one or more removed rows.');
                } else {
                    $this->info('Qdrant delete success for removed rows: ' . count($deletePointIds));
                }
            }
        }

        $this->info('CSV import completed successfully.');
        return self::SUCCESS;
    }

    private function syncFaqMirror(int $organizationId, array $incoming): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($incoming as $data) {
            $csvRow = data_get($data, 'metadata.csv', []);

            $question = trim((string) ($data['name'] ?? ''));
            if ($question === '') {
                $question = trim((string) data_get($csvRow, 'question', ''));
            }

            if ($question === '') {
                $skipped++;
                continue;
            }

            $answer = trim((string) ($data['description'] ?? ''));
            if ($answer === '') {
                $answer = trim((string) data_get($csvRow, 'answer', ''));
            }
            if ($answer === '') {
                $answer = trim((string) ($data['content'] ?? ''));
            }

            $category = trim((string) ($data['category'] ?? ''));
            $keywords = trim((string) data_get($csvRow, 'keywords', ''));
            $followUp = trim((string) data_get($csvRow, 'follow_up', ''));
            $sortOrderRaw = data_get($csvRow, 'sort_order', 0);
            $sortOrder = is_numeric($sortOrderRaw) ? (int) $sortOrderRaw : 0;
            $isActive = $this->toBoolean(data_get($csvRow, 'is_active', true));

            $faq = OrganizationFaq::query()
                ->where('organization_id', $organizationId)
                ->where('question', $question)
                ->first();

            if (!$faq) {
                OrganizationFaq::create([
                    'organization_id' => $organizationId,
                    'question' => $question,
                    'answer' => $answer,
                    'answer_markdown' => null,
                    'follow_up' => $followUp,
                    'category' => $category,
                    'keywords' => $keywords,
                    'sort_order' => $sortOrder,
                    'is_active' => $isActive,
                ]);
                $created++;
                continue;
            }

            $faq->update([
                'answer' => $answer,
                'answer_markdown' => null,
                'follow_up' => $followUp,
                'category' => $category,
                'keywords' => $keywords,
                'sort_order' => $sortOrder,
                'is_active' => $isActive,
            ]);
            $updated++;
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    private function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return false;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    private function resolveCsvPath(string $fileArg): ?string
    {
        if (is_file($fileArg)) {
            return realpath($fileArg) ?: $fileArg;
        }

        $relative = base_path($fileArg);
        if (is_file($relative)) {
            return realpath($relative) ?: $relative;
        }

        return null;
    }

    private function readCsvRows(string $csvPath): array
    {
        $handle = fopen($csvPath, 'r');
        if (!$handle) {
            return [];
        }

        $header = null;
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => $this->normalizeHeader((string) $h), $line);
                continue;
            }

            if (empty(array_filter($line, fn ($v) => trim((string) $v) !== ''))) {
                continue;
            }

            while (count($line) < count($header)) {
                $line[] = '';
            }

            $assoc = [];
            foreach ($header as $idx => $key) {
                $assoc[$key] = trim((string) ($line[$idx] ?? ''));
            }

            $rows[] = $assoc;
        }

        fclose($handle);
        return $rows;
    }

    private function normalizeHeader(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        return trim($value, '_');
    }

    private function parseCsvList(string $value): array
    {
        $items = array_filter(array_map('trim', explode(',', $value)));
        return array_values(array_map(fn ($v) => $this->normalizeHeader($v), $items));
    }

    private function inferKeyColumns(array $headers): array
    {
        $preferred = ['id', 'sku', 'code', 'model', 'variant', 'name', 'title'];
        $picked = array_values(array_intersect($preferred, $headers));
        if (!empty($picked)) {
            return $picked;
        }

        return array_slice($headers, 0, min(3, count($headers)));
    }

    private function inferNameColumns(array $headers): array
    {
        foreach (['name', 'title', 'model', 'variant'] as $candidate) {
            if (in_array($candidate, $headers, true)) {
                return [$candidate];
            }
        }

        return array_slice($headers, 0, 1);
    }

    private function buildExternalKey(array $row, array $keyColumns): string
    {
        $parts = [];
        foreach ($keyColumns as $column) {
            $parts[] = strtolower(trim((string) ($row[$column] ?? '')));
        }

        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));
        if (empty($parts)) {
            return '';
        }

        return sha1(implode('|', $parts));
    }

    private function joinColumns(array $row, array $columns, string $glue = ' | '): string
    {
        if (empty($columns)) {
            return '';
        }

        $parts = [];
        foreach ($columns as $column) {
            $val = trim((string) ($row[$column] ?? ''));
            if ($val !== '') {
                $parts[] = $val;
            }
        }

        return implode($glue, $parts);
    }

    private function buildContent(array $row, array $contentColumns): string
    {
        $lines = [];

        if (empty($contentColumns)) {
            foreach ($row as $key => $value) {
                $value = trim((string) $value);
                if ($value !== '') {
                    $lines[] = ucfirst(str_replace('_', ' ', $key)) . ': ' . $value;
                }
            }

            return implode("\n", $lines);
        }

        foreach ($contentColumns as $column) {
            $value = trim((string) ($row[$column] ?? ''));
            if ($value !== '') {
                $lines[] = ucfirst(str_replace('_', ' ', $column)) . ': ' . $value;
            }
        }

        return implode("\n", $lines);
    }

    private function extractSizeSignals(array $row, string ...$segments): array
    {
        $parts = [];

        foreach (['sku', 'name', 'title', 'short_description', 'description', 'additional_attributes'] as $key) {
            $value = trim((string) ($row[$key] ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment !== '') {
                $parts[] = $segment;
            }
        }

        if (empty($parts)) {
            return ['pairs' => [], 'primary' => null, 'orientation' => null];
        }

        $text = implode("\n", $parts);
        preg_match_all('/(?<!\d)(\d{1,3}(?:\.\d+)?)\s*(?:x|×)\s*(\d{1,3}(?:\.\d+)?)(?:\s*(?:inches|inch|in|\"))?/i', $text, $matches, PREG_SET_ORDER);

        $pairs = [];
        $primary = null;
        $orientation = null;

        foreach ($matches as $match) {
            $width = (float) $match[1];
            $height = (float) $match[2];

            if ($width < 4 || $width > 120 || $height < 4 || $height > 120) {
                continue;
            }

            $w = fmod($width, 1.0) === 0.0 ? (string) (int) $width : rtrim(rtrim(number_format($width, 1), '0'), '.');
            $h = fmod($height, 1.0) === 0.0 ? (string) (int) $height : rtrim(rtrim(number_format($height, 1), '0'), '.');
            $key = $w . 'x' . $h;

            if (!in_array($key, $pairs, true)) {
                $pairs[] = $key;
            }

            if ($primary === null) {
                $primary = $key;
                if (abs($width - $height) < 0.01) {
                    $orientation = 'square';
                } elseif ($width > $height) {
                    $orientation = 'landscape';
                } else {
                    $orientation = 'portrait';
                }
            }
        }

        return [
            'pairs' => $pairs,
            'primary' => $primary,
            'orientation' => $orientation,
        ];
    }
}
