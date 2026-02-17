<?php

namespace App\Livewire\Admin;

use App\Models\Organization;
use Illuminate\Support\Facades\Artisan;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CsvImportManager extends Component
{
    use WithFileUploads;

    public $organizationId = '';
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

    protected $rules = [
        'organizationId' => 'required|exists:organizations,id',
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

    public function getOrganizationsProperty()
    {
        return Organization::query()->orderBy('name')->get();
    }

    public function runImport(): void
    {
        $this->validate();

        $storedPath = $this->csvFile->storeAs(
            'csv-imports',
            now()->format('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', $this->csvFile->getClientOriginalName())
        );

        $arguments = [
            'organization' => (string) $this->organizationId,
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
        return view('livewire.admin.csv-import-manager')
            ->layout('layouts.admin');
    }
}
