<?php

namespace App\Livewire\Customer;

use App\Models\OrganizationData;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CsvImportManager extends Component
{
    use WithFileUploads;

    public $csvFile;

    public $dataset = '';
    public $type = 'pricing';
    public $qdrantType = 'info';

    public $keyColumns = '';
    public $nameColumns = '';
    public $descriptionColumns = '';
    public $contentColumns = '';
    public $categoryColumn = '';
    public $defaultCategory = 'general';

    public $dryRun = true;
    public $skipQdrant = false;

    public $importOutput = '';
    public $importExitCode = null;

    public $showEditorModal = false;
    public $selectedDataset = '';
    public $selectedType = '';
    public $selectedSourceFile = '';
    public $editorRows = [];

    protected $rules = [
        'csvFile' => 'required|file|mimes:csv,txt|max:10240',
        'dataset' => 'nullable|string|max:100',
        'type' => 'required|string|max:50',
        'qdrantType' => 'required|in:faq,info,service',
        'keyColumns' => 'nullable|string|max:255',
        'nameColumns' => 'nullable|string|max:255',
        'descriptionColumns' => 'nullable|string|max:255',
        'contentColumns' => 'nullable|string|max:255',
        'categoryColumn' => 'nullable|string|max:100',
        'defaultCategory' => 'required|string|max:100',
        'dryRun' => 'boolean',
        'skipQdrant' => 'boolean',
    ];

    public function getOrganizationProperty()
    {
        return auth()->user()?->organizations()?->first();
    }

    public function getImportedDatasetsProperty()
    {
        $organization = $this->organization;
        if (!$organization) {
            return collect();
        }

        return OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('metadata->source', 'csv_import')
            ->get()
            ->groupBy(function (OrganizationData $row): string {
                return (string) data_get($row->metadata, 'dataset', 'unknown');
            })
            ->map(function ($rows, $dataset) {
                $first = $rows->first();

                return [
                    'dataset' => (string) $dataset,
                    'type' => (string) ($first->type ?? 'info'),
                    'qdrant_type' => (string) data_get($first->metadata, 'qdrant_type', 'info'),
                    'source_file' => (string) data_get($first->metadata, 'source_file', ''),
                    'row_count' => (int) $rows->count(),
                    'last_updated_at' => optional($rows->max('updated_at'))->format('Y-m-d H:i'),
                ];
            })
            ->sortByDesc('last_updated_at')
            ->values();
    }

    public function openDatasetEditor(string $dataset, string $type): void
    {
        $organization = $this->organization;
        if (!$organization) {
            session()->flash('error', 'No organization found for this account.');
            return;
        }

        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', $type)
            ->where('metadata->source', 'csv_import')
            ->where('metadata->dataset', $dataset)
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            session()->flash('error', 'No imported rows found for selected dataset.');
            return;
        }

        $this->selectedDataset = $dataset;
        $this->selectedType = $type;
        $this->selectedSourceFile = (string) data_get($rows->first()->metadata, 'source_file', '');

        $this->editorRows = $rows->map(function (OrganizationData $row): array {
            return [
                'id' => $row->id,
                'external_key' => (string) data_get($row->metadata, 'external_key', ''),
                'qdrant_type' => (string) data_get($row->metadata, 'qdrant_type', 'info'),
                'name' => (string) $row->name,
                'description' => (string) ($row->description ?? ''),
                'content' => (string) ($row->content ?? ''),
                'category' => (string) data_get($row->metadata, 'category', 'general'),
                'is_synced' => (bool) $row->is_synced,
            ];
        })->toArray();

        $this->showEditorModal = true;
    }

    public function closeEditorModal(): void
    {
        $this->showEditorModal = false;
        $this->selectedDataset = '';
        $this->selectedType = '';
        $this->selectedSourceFile = '';
        $this->editorRows = [];
    }

    public function saveEditedRows(): void
    {
        $organization = $this->organization;
        if (!$organization) {
            session()->flash('error', 'No organization found for this account.');
            return;
        }

        if (empty($this->editorRows)) {
            session()->flash('error', 'No rows to save.');
            return;
        }

        $this->validate([
            'editorRows.*.name' => 'required|string|max:255',
            'editorRows.*.description' => 'nullable|string',
            'editorRows.*.content' => 'nullable|string',
            'editorRows.*.category' => 'required|string|max:100',
        ]);

        $rowIds = array_values(array_filter(array_map(fn ($row) => (int) ($row['id'] ?? 0), $this->editorRows)));

        $existingRows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->whereIn('id', $rowIds)
            ->get()
            ->keyBy('id');

        $qdrantItemsByType = [];
        $updatedIds = [];

        foreach ($this->editorRows as $edited) {
            $id = (int) ($edited['id'] ?? 0);
            if ($id <= 0 || !$existingRows->has($id)) {
                continue;
            }

            $row = $existingRows->get($id);
            $metadata = is_array($row->metadata) ? $row->metadata : [];
            $metadata['category'] = trim((string) ($edited['category'] ?? 'general'));

            $row->update([
                'name' => trim((string) ($edited['name'] ?? '')),
                'description' => trim((string) ($edited['description'] ?? '')),
                'content' => trim((string) ($edited['content'] ?? '')),
                'metadata' => $metadata,
                'is_synced' => false,
                'last_synced_at' => null,
            ]);

            $qdrantType = trim((string) ($edited['qdrant_type'] ?? data_get($metadata, 'qdrant_type', 'info')));
            if (!in_array($qdrantType, ['faq', 'info', 'service'], true)) {
                $qdrantType = 'info';
            }

            $csvRow = data_get($row->metadata, 'csv', []);
            $model = trim((string) data_get($csvRow, 'model', ''));
            $variant = trim((string) data_get($csvRow, 'variant', ''));
            $exShowroomPrice = trim((string) data_get($csvRow, 'ex_showroom_price_inr', ''));
            $onRoadPrice = trim((string) data_get($csvRow, 'approx_on_road_price_inr', ''));

            $qdrantMetadata = [
                'table_id' => $row->id,
                'updated_at' => optional($row->updated_at)->toISOString(),
                'source' => 'csv_import',
                'dataset' => data_get($row->metadata, 'dataset'),
                'external_key' => data_get($row->metadata, 'external_key'),
                'type' => $row->type,
            ];

            if ($model !== '') {
                $qdrantMetadata['model'] = $model;
            }
            if ($variant !== '') {
                $qdrantMetadata['variant'] = $variant;
            }
            if ($exShowroomPrice !== '') {
                $qdrantMetadata['ex_showroom_price_inr'] = $exShowroomPrice;
            }
            if ($onRoadPrice !== '') {
                $qdrantMetadata['approx_on_road_price_inr'] = $onRoadPrice;
            }

            $qdrantItemsByType[$qdrantType][] = [
                'id' => $qdrantType . '_' . $row->id,
                'title' => $row->name,
                'content' => $row->content ?: ($row->description ?? ''),
                'category' => data_get($row->metadata, 'category', 'general'),
                'metadata' => $qdrantMetadata,
            ];

            $updatedIds[] = $row->id;
        }

        if (empty($updatedIds)) {
            session()->flash('error', 'No valid rows found to update.');
            return;
        }

        $aiService = new AiAgentService();
        foreach ($qdrantItemsByType as $qdrantType => $items) {
            if (empty($items)) {
                continue;
            }

            $result = $aiService->updateDataToQdrant($organization->slug, $qdrantType, $items);
            if (!$result || !($result['success'] ?? false)) {
                session()->flash('error', 'Rows saved, but Qdrant sync failed for type: ' . $qdrantType);
                return;
            }
        }

        OrganizationData::query()
            ->whereIn('id', $updatedIds)
            ->update([
                'is_synced' => true,
                'last_synced_at' => now(),
            ]);

        session()->flash('message', 'CSV rows updated and synced successfully.');
        $this->openDatasetEditor($this->selectedDataset, $this->selectedType);
    }

    public function runImport(): void
    {
        $this->validate();

        $organization = $this->organization;
        if (!$organization) {
            session()->flash('error', 'No organization found for this account.');
            return;
        }

        $storedPath = $this->csvFile->storeAs(
            'csv-imports',
            now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $this->csvFile->getClientOriginalName())
        );

        $arguments = [
            'organization' => (string) $organization->id,
            'file' => 'storage/app/' . $storedPath,
            '--type' => trim((string) $this->type),
            '--qdrant-type' => trim((string) $this->qdrantType),
            '--default-category' => trim((string) $this->defaultCategory),
        ];

        if (trim((string) $this->dataset) !== '') {
            $arguments['--dataset'] = trim((string) $this->dataset);
        }

        if (trim((string) $this->keyColumns) !== '') {
            $arguments['--key-columns'] = trim((string) $this->keyColumns);
        }

        if (trim((string) $this->nameColumns) !== '') {
            $arguments['--name-columns'] = trim((string) $this->nameColumns);
        }

        if (trim((string) $this->descriptionColumns) !== '') {
            $arguments['--description-columns'] = trim((string) $this->descriptionColumns);
        }

        if (trim((string) $this->contentColumns) !== '') {
            $arguments['--content-columns'] = trim((string) $this->contentColumns);
        }

        if (trim((string) $this->categoryColumn) !== '') {
            $arguments['--category-column'] = trim((string) $this->categoryColumn);
        }

        if ($this->dryRun) {
            $arguments['--dry-run'] = true;
        }

        if ($this->skipQdrant) {
            $arguments['--skip-qdrant'] = true;
        }

        $this->importExitCode = Artisan::call('orgdata:import-csv', $arguments);
        $this->importOutput = Artisan::output();

        if ($this->importExitCode === 0) {
            session()->flash('message', 'CSV import command completed successfully.');
        } else {
            session()->flash('error', 'CSV import command failed. Check output below.');
        }

        $this->reset('csvFile');
    }

    public function render()
    {
        return view('livewire.customer.csv-import-manager')
            ->layout('layouts.customer');
    }
}
