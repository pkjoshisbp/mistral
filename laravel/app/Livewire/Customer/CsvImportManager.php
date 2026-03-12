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

    public $dryRun = false;
    public $skipQdrant = false;

    public $importOutput = '';
    public $importExitCode = null;
    public $uiMessage = '';
    public $uiMessageType = 'success';

    public $showEditorModal = false;
    public $selectedDataset = '';
    public $selectedType = '';
    public $selectedSourceFile = '';
    public $editorRows = [];
    public $editorDeletedRowIds = [];

    /** Custom AI instruction applied to all responses referencing this dataset */
    public $datasetInstruction = '';

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
            ->whereNull('metadata->is_config')   // exclude dataset_config rows
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

    public function getShowOperationalFieldsProperty(): bool
    {
        return !$this->isPricingLikeType;
    }

    public function getIsPricingLikeTypeProperty(): bool
    {
        $type = strtolower(trim((string) $this->selectedType));

        return in_array($type, ['pricing', 'price', 'plan', 'plans', 'credit', 'credits', 'subscription'], true);
    }

    public function getEditorDescriptionLabelProperty(): string
    {
        return $this->isPricingLikeType ? 'Features' : 'Description';
    }

    public function getEditorContentLabelProperty(): string
    {
        return $this->isPricingLikeType ? 'Plan Details' : 'Content';
    }

    public function getEditorDescriptionPlaceholderProperty(): string
    {
        return $this->isPricingLikeType
            ? 'e.g. Dashboard access | Email support | Basic analytics | Up to 500K tokens/month'
            : 'Enter a short summary for this row';
    }

    public function getEditorContentPlaceholderProperty(): string
    {
        return $this->isPricingLikeType
            ? "e.g. Plan name: Basic\nPlan type: subscription\nBilling period: monthly\nUsd price: 19"
            : 'Enter full searchable content for this row';
    }

    public function openDatasetEditor(string $dataset, string $type): void
    {
        $organization = $this->organization;
        if (!$organization) {
            $this->pushUiMessage('error', 'No organization found for this account.');
            return;
        }

        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', $type)
            ->where('metadata->source', 'csv_import')
            ->where('metadata->dataset', $dataset)
            ->whereNull('metadata->is_config')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->pushUiMessage('error', 'No imported rows found for selected dataset.');
            return;
        }

        // Load dataset-level AI instruction from config row
        $configRow = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'dataset_config')
            ->where('metadata->source', 'csv_import')
            ->where('metadata->dataset', $dataset)
            ->where('metadata->is_config', true)
            ->first();
        $this->datasetInstruction = $configRow ? trim((string) data_get($configRow->metadata, 'instruction', '')) : '';

        $this->selectedDataset = $dataset;
        $this->selectedType = $type;
        $this->selectedSourceFile = (string) data_get($rows->first()->metadata, 'source_file', '');
        $this->editorDeletedRowIds = [];

        $this->editorRows = $rows->map(function (OrganizationData $row): array {
            $metadata = is_array($row->metadata) ? $row->metadata : [];
            $rowContent = (string) ($row->content ?? '');
            return [
                'id' => $row->id,
                'external_key' => (string) data_get($metadata, 'external_key', ''),
                'qdrant_type' => (string) data_get($metadata, 'qdrant_type', 'info'),
                'name' => (string) $row->name,
                'description' => (string) ($row->description ?? ''),
                'content' => $rowContent,
                'category' => (string) data_get($metadata, 'category', 'general'),
                'working_schedule' => (string) (
                    data_get($metadata, 'working_schedule')
                    ?? $this->extractFirstLabeledLine($rowContent, ['Working Schedule', 'Timing'])
                ),
                'leave_notes' => (string) (
                    data_get($metadata, 'leave_notes')
                    ?? $this->extractFirstLabeledLine($rowContent, ['Leave / Absence'])
                ),
                'search_keywords' => (string) (
                    data_get($metadata, 'search_keywords')
                    ?? data_get($metadata, 'keywords')
                    ?? data_get($metadata, 'csv.keywords')
                    ?? $this->extractFirstLabeledLine($rowContent, ['Also known as'])
                ),
                'is_synced' => (bool) $row->is_synced,
            ];
        })->toArray();

        $this->showEditorModal = true;
    }

    public function addEditorRow(): void
    {
        if (!$this->showEditorModal) {
            return;
        }

        $this->editorRows[] = [
            'id' => null,
            'external_key' => 'manual_' . now()->format('YmdHis') . '_' . substr((string) microtime(true), -4),
            'qdrant_type' => $this->qdrantType ?: 'info',
            'name' => '',
            'description' => '',
            'content' => '',
            'category' => 'general',
            'working_schedule' => '',
            'leave_notes'      => '',
            'search_keywords'  => '',
            'is_synced'        => false,
        ];
    }

    public function removeEditorRow(int $index): void
    {
        if (!isset($this->editorRows[$index])) {
            return;
        }

        $rowId = (int) ($this->editorRows[$index]['id'] ?? 0);
        if ($rowId > 0 && !in_array($rowId, $this->editorDeletedRowIds, true)) {
            $this->editorDeletedRowIds[] = $rowId;
        }

        unset($this->editorRows[$index]);
        $this->editorRows = array_values($this->editorRows);
    }

    public function closeEditorModal(): void
    {
        $this->showEditorModal = false;
        $this->selectedDataset = '';
        $this->selectedType = '';
        $this->selectedSourceFile = '';
        $this->editorRows = [];
        $this->editorDeletedRowIds = [];
        $this->datasetInstruction = '';
    }

    public function deleteDataset(string $dataset, string $type): void
    {
        $organization = $this->organization;
        if (!$organization) {
            $this->pushUiMessage('error', 'No organization found for this account.');
            return;
        }

        $rows = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', $type)
            ->where('metadata->source', 'csv_import')
            ->where('metadata->dataset', $dataset)
            ->get();

        if ($rows->isEmpty()) {
            $this->pushUiMessage('error', 'No rows found for selected dataset.');
            return;
        }

        $qdrantIds = $rows->map(function (OrganizationData $row): string {
            $qdrantType = (string) data_get($row->metadata, 'qdrant_type', 'info');
            if (!in_array($qdrantType, ['faq', 'info', 'service'], true)) {
                $qdrantType = 'info';
            }
            return $qdrantType . '_' . $row->id;
        })->values()->all();

        OrganizationData::query()->whereIn('id', $rows->pluck('id')->all())->delete();

        // Also delete dataset_config row
        OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'dataset_config')
            ->where('metadata->dataset', $dataset)
            ->delete();

        if (!empty($qdrantIds)) {
            $aiService = new AiAgentService();
            $aiService->deleteDataFromQdrant($organization->slug, $qdrantIds);
        }

        $this->pushUiMessage('success', 'Dataset deleted successfully and synced to Qdrant.');

        if ($this->showEditorModal && $this->selectedDataset === $dataset && $this->selectedType === $type) {
            $this->closeEditorModal();
        }
    }

    public function saveEditedRows(): void
    {
        $organization = $this->organization;
        if (!$organization) {
            $this->pushUiMessage('error', 'No organization found for this account.');
            return;
        }

        if (empty($this->editorRows) && empty($this->editorDeletedRowIds)) {
            $this->pushUiMessage('error', 'No changes to save.');
            return;
        }

        $this->validate([
            'editorRows.*.name' => 'required|string|max:255',
            'editorRows.*.description' => 'nullable|string',
            'editorRows.*.content' => 'nullable|string',
            'editorRows.*.category' => 'required|string|max:100',
            'editorRows.*.working_schedule' => 'nullable|string|max:255',
            'editorRows.*.leave_notes'      => 'nullable|string|max:1000',
            'editorRows.*.search_keywords'  => 'nullable|string|max:500',
        ]);

        // Save the dataset-level AI instruction
        $this->saveDatasetConfigRow($organization);

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
            $isExisting = $id > 0 && $existingRows->has($id);

            if ($isExisting) {
                $row = $existingRows->get($id);
                $metadata = is_array($row->metadata) ? $row->metadata : [];
            } else {
                $metadata = [];
            }
            $metadata['category'] = trim((string) ($edited['category'] ?? 'general'));
            $metadata['source'] = 'csv_import';
            $metadata['dataset'] = $this->selectedDataset;
            $metadata['source_file'] = $this->selectedSourceFile;
            $metadata['external_key'] = trim((string) ($edited['external_key'] ?? data_get($metadata, 'external_key', '')));

            // Store schedule and leave notes in metadata
            $workingSchedule = trim((string) ($edited['working_schedule'] ?? ''));
            $leaveNotes      = trim((string) ($edited['leave_notes'] ?? ''));
            $searchKeywords  = trim((string) ($edited['search_keywords'] ?? ''));
            if ($workingSchedule !== '') {
                $metadata['working_schedule'] = $workingSchedule;
            } else {
                unset($metadata['working_schedule']);
            }
            if ($leaveNotes !== '') {
                $metadata['leave_notes'] = $leaveNotes;
            } else {
                unset($metadata['leave_notes']);
            }
            if ($searchKeywords !== '') {
                $metadata['search_keywords'] = $searchKeywords;
                $metadata['keywords'] = $searchKeywords;
            } else {
                unset($metadata['search_keywords']);
                unset($metadata['keywords']);
            }

            // Build enriched Qdrant content: base content + schedule/leave appended
            $baseContent = trim((string) ($edited['content'] ?? ''));
            $enrichedContent = $this->buildEnrichedContent($baseContent, $workingSchedule, $leaveNotes, $searchKeywords);

            if ($isExisting) {
                $row->update([
                    'name' => trim((string) ($edited['name'] ?? '')),
                    'description' => trim((string) ($edited['description'] ?? '')),
                    'content' => $enrichedContent,
                    'metadata' => $metadata,
                    'is_synced' => false,
                    'last_synced_at' => null,
                ]);
            } else {
                $row = OrganizationData::create([
                    'organization_id' => $organization->id,
                    'type' => $this->selectedType,
                    'name' => trim((string) ($edited['name'] ?? '')),
                    'description' => trim((string) ($edited['description'] ?? '')),
                    'content' => $enrichedContent,
                    'metadata' => $metadata,
                    'is_synced' => false,
                    'last_synced_at' => null,
                ]);
            }

            $qdrantType = trim((string) ($edited['qdrant_type'] ?? data_get($metadata, 'qdrant_type', 'info')));
            if (!in_array($qdrantType, ['faq', 'info', 'service'], true)) {
                $qdrantType = 'info';
            }
            $metadata['qdrant_type'] = $qdrantType;
            $row->update(['metadata' => $metadata]);

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
                'content' => $enrichedContent ?: ($row->description ?? ''),
                'category' => data_get($row->metadata, 'category', 'general'),
                'metadata' => $qdrantMetadata,
            ];

            $updatedIds[] = $row->id;
        }

        if (!empty($this->editorDeletedRowIds)) {
            $rowsToDelete = OrganizationData::query()
                ->where('organization_id', $organization->id)
                ->whereIn('id', $this->editorDeletedRowIds)
                ->get();

            $qdrantDeleteIds = $rowsToDelete->map(function (OrganizationData $row): string {
                $qdrantType = (string) data_get($row->metadata, 'qdrant_type', 'info');
                if (!in_array($qdrantType, ['faq', 'info', 'service'], true)) {
                    $qdrantType = 'info';
                }
                return $qdrantType . '_' . $row->id;
            })->values()->all();

            OrganizationData::query()
                ->where('organization_id', $organization->id)
                ->whereIn('id', $this->editorDeletedRowIds)
                ->delete();

            if (!empty($qdrantDeleteIds)) {
                $aiService = new AiAgentService();
                $deleteResult = $aiService->deleteDataFromQdrant($organization->slug, $qdrantDeleteIds);
                if (!$deleteResult) {
                    $this->pushUiMessage('error', 'Rows were deleted in DB, but Qdrant delete failed.');
                    return;
                }
            }
        }

        $aiService = new AiAgentService();
        foreach ($qdrantItemsByType as $qdrantType => $items) {
            if (empty($items)) {
                continue;
            }

            $result = $aiService->updateDataToQdrant($organization->slug, $qdrantType, $items);
            if (!$result || !($result['success'] ?? false)) {
                $this->pushUiMessage('error', 'Rows saved, but Qdrant sync failed for type: ' . $qdrantType);
                return;
            }
        }

        OrganizationData::query()
            ->whereIn('id', $updatedIds)
            ->update([
                'is_synced' => true,
                'last_synced_at' => now(),
            ]);

        $this->pushUiMessage('success', 'Changes saved successfully and synced to Qdrant.');
        $this->openDatasetEditor($this->selectedDataset, $this->selectedType);
    }

    /**
     * Append working schedule and leave notes to content for AI embedding.
     */
    private function buildEnrichedContent(string $baseContent, string $workingSchedule, string $leaveNotes, string $searchKeywords = ''): string
    {
        $parts = [];
        $cleanBaseContent = $this->stripManagedEnrichmentLines($baseContent);
        if ($cleanBaseContent !== '') {
            $parts[] = $cleanBaseContent;
        }
        if ($workingSchedule !== '') {
            $parts[] = 'Working Schedule: ' . $workingSchedule;
        }
        if ($leaveNotes !== '') {
            $parts[] = 'Leave / Absence: ' . $leaveNotes;
        }
        // Search keywords / synonyms are appended so they influence the embedding
        // vector without appearing in display content.  Example:
        // "USG, ultrasound, sonography, usg abdomen" for a Sonography service.
        if ($searchKeywords !== '') {
            $parts[] = 'Also known as: ' . $searchKeywords;
        }
        return implode("\n", $parts);
    }

    private function stripManagedEnrichmentLines(string $content): string
    {
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\r?\n/', $content) ?: [];
        $filtered = [];
        foreach ($lines as $line) {
            $trimmed = trim((string) $line);
            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^(working\s*schedule|timing|leave\s*\/\s*absence|also\s+known\s+as)\s*:/i', $trimmed)) {
                continue;
            }

            $filtered[] = $trimmed;
        }

        return implode("\n", $filtered);
    }

    private function extractFirstLabeledLine(string $content, array $labels): string
    {
        if ($content === '' || empty($labels)) {
            return '';
        }

        foreach ($labels as $label) {
            $pattern = '/^\s*' . preg_quote($label, '/') . '\s*:\s*(.+)$/im';
            if (preg_match($pattern, $content, $match)) {
                return trim((string) ($match[1] ?? ''));
            }
        }

        return '';
    }

    /**
     * Upsert the dataset-level config row that stores the custom AI instruction.
     */
    private function saveDatasetConfigRow($organization): void
    {
        $instruction = trim((string) $this->datasetInstruction);

        $configRow = OrganizationData::query()
            ->where('organization_id', $organization->id)
            ->where('type', 'dataset_config')
            ->where('metadata->source', 'csv_import')
            ->where('metadata->dataset', $this->selectedDataset)
            ->first();

        if ($instruction === '') {
            if ($configRow) {
                $configRow->delete();
            }
            return;
        }

        $metadata = [
            'source' => 'csv_import',
            'dataset' => $this->selectedDataset,
            'is_config' => true,
            'instruction' => $instruction,
        ];

        if ($configRow) {
            $configRow->update([
                'name' => 'Dataset config: ' . $this->selectedDataset,
                'metadata' => $metadata,
            ]);
        } else {
            OrganizationData::create([
                'organization_id' => $organization->id,
                'type' => 'dataset_config',
                'name' => 'Dataset config: ' . $this->selectedDataset,
                'content' => '',
                'metadata' => $metadata,
                'is_synced' => true,
            ]);
        }
    }

    public function runImport(): void
    {
        $this->validate();

        $organization = $this->organization;
        if (!$organization) {
            $this->pushUiMessage('error', 'No organization found for this account.');
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
            if ($this->dryRun) {
                $this->pushUiMessage('success', 'Dry run completed successfully. No data was saved because preview mode is enabled.');
            } else {
                if ($this->skipQdrant) {
                    $this->pushUiMessage('success', 'CSV import completed successfully. Data was saved; Qdrant sync was skipped.');
                } else {
                    $this->pushUiMessage('success', 'CSV import completed successfully and synced to Qdrant.');
                }
            }
        } else {
            $this->pushUiMessage('error', 'CSV import command failed. Check output below.');
        }

        $this->reset('csvFile');
    }

    public function clearUiMessage(): void
    {
        $this->uiMessage = '';
        $this->uiMessageType = 'success';
    }

    private function pushUiMessage(string $type, string $message): void
    {
        $normalizedType = $type === 'error' ? 'error' : 'success';
        $this->uiMessageType = $normalizedType;
        $this->uiMessage = $message;
        session()->flash($normalizedType === 'error' ? 'error' : 'message', $message);
    }

    public function render()
    {
        return view('livewire.customer.csv-import-manager')
            ->layout('layouts.customer');
    }
}
