<?php

namespace App\Livewire\Admin;

use App\Models\Organization;
use App\Models\OrganizationData;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CatalogPriceManager extends Component
{
    use WithFileUploads;

    // ── Org selection ──────────────────────────────────────────────────────────
    public string $organizationId = '';

    // ── Active tab ─────────────────────────────────────────────────────────────
    public string $activeTab = 'browse';

    // ── Browse / inline-edit tab ───────────────────────────────────────────────
    public string $search      = '';
    public string $priceFilter = 'all';  // all | missing | has_price
    public int    $perPage     = 50;
    public int    $page        = 1;
    public array  $editingRows = [];  // [id => ['price' => ..., 'special_price' => ...]]

    // ── Bulk upload tab ────────────────────────────────────────────────────────
    public $priceFile;
    public bool $dryRun     = true;
    public bool $skipQdrant = false;

    // ── Server-path tab ────────────────────────────────────────────────────────
    public string $serverFilePath = '';

    // ── UI feedback ────────────────────────────────────────────────────────────
    public string $uiMessage     = '';
    public string $uiMessageType = 'success';
    public string $importOutput  = '';

    // ── Stats ──────────────────────────────────────────────────────────────────
    public int $totalProducts  = 0;
    public int $pricedProducts = 0;

    protected function rules(): array
    {
        return [
            'organizationId' => 'required|exists:organizations,id',
            'priceFile'      => 'nullable|file|mimes:csv,txt|max:5120',  // 5 MB
            'serverFilePath' => 'nullable|string',
        ];
    }

    public function mount(): void
    {
        // Default to first org
        $first = Organization::orderBy('name')->first();
        if ($first) {
            $this->organizationId = (string) $first->id;
            $this->loadStats();
        }
    }

    public function updatedOrganizationId(): void
    {
        $this->resetPage();
        $this->editingRows = [];
        $this->loadStats();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPriceFilter(): void
    {
        $this->resetPage();
    }

    private function resetPage(): void
    {
        $this->page = 1;
    }

    private function loadStats(): void
    {
        if (! $this->organizationId) return;

        $q = OrganizationData::where('organization_id', $this->organizationId)
            ->where('type', 'product');

        $this->totalProducts = $q->count();

        // Count rows where metadata->csv->price is non-empty and non-zero
        $this->pricedProducts = OrganizationData::where('organization_id', $this->organizationId)
            ->where('type', 'product')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) NOT IN ('', '0', '0.0000', 'null') 
                        AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NOT NULL")
            ->count();
    }

    // ── Browse table ───────────────────────────────────────────────────────────

    public function getProductsProperty()
    {
        if (! $this->organizationId) return collect();

        $q = OrganizationData::where('organization_id', $this->organizationId)
            ->where('type', 'product')
            ->select(['id', 'name', 'metadata', 'is_synced', 'updated_at']);

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.sku')) LIKE ?", [$term]);
            });
        }

        if ($this->priceFilter === 'missing') {
            $q->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NULL
                          OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IN ('', '0', '0.0000', 'null'))");
        } elseif ($this->priceFilter === 'has_price') {
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) NOT IN ('', '0', '0.0000', 'null')
                          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NOT NULL");
        }

        $offset = ($this->page - 1) * $this->perPage;
        return $q->orderBy('name')->offset($offset)->limit($this->perPage)->get();
    }

    public function getTotalPagesProperty(): int
    {
        if (! $this->organizationId) return 1;

        $q = OrganizationData::where('organization_id', $this->organizationId)
            ->where('type', 'product');

        if ($this->search) {
            $term = '%' . $this->search . '%';
            $q->where(function ($sub) use ($term) {
                $sub->where('name', 'like', $term)
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.sku')) LIKE ?", [$term]);
            });
        }

        if ($this->priceFilter === 'missing') {
            $q->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NULL
                          OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IN ('', '0', '0.0000', 'null'))");
        } elseif ($this->priceFilter === 'has_price') {
            $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) NOT IN ('', '0', '0.0000', 'null')
                          AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NOT NULL");
        }

        return max(1, (int) ceil($q->count() / $this->perPage));
    }

    public function nextPage(): void
    {
        if ($this->page < $this->totalPages) $this->page++;
    }

    public function prevPage(): void
    {
        if ($this->page > 1) $this->page--;
    }

    public function startEdit(int $id): void
    {
        $row  = OrganizationData::find($id);
        $csv  = $row->metadata['csv'] ?? [];
        $this->editingRows[$id] = [
            'price'         => $csv['price'] ?? '',
            'special_price' => $csv['special_price'] ?? '',
        ];
    }

    public function cancelEdit(int $id): void
    {
        unset($this->editingRows[$id]);
    }

    public function saveRow(int $id): void
    {
        if (! isset($this->editingRows[$id])) return;

        $price        = preg_replace('/[^\d\.]/', '', $this->editingRows[$id]['price'] ?? '');
        $specialPrice = preg_replace('/[^\d\.]/', '', $this->editingRows[$id]['special_price'] ?? '');

        $record = OrganizationData::find($id);
        if (! $record) return;

        $meta = is_array($record->metadata) ? $record->metadata : [];
        if (! isset($meta['csv'])) $meta['csv'] = [];
        $meta['csv']['price']         = $price;
        $meta['csv']['special_price'] = $specialPrice;

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

        unset($this->editingRows[$id]);
        $this->loadStats();
        $this->uiMessageType = 'success';
        $this->uiMessage = "Price saved for «{$record->name}». Sync to Qdrant when ready.";
    }

    // ── Generate price template ────────────────────────────────────────────────

    public function generateTemplate(): void
    {
        if (! $this->organizationId) {
            $this->setError('Please select an organization first.');
            return;
        }

        $org = Organization::find($this->organizationId);
        $dir  = storage_path('app/price_templates');
        if (! is_dir($dir)) mkdir($dir, 0775, true);

        $outputPath = $dir . '/' . $org->slug . '_prices.csv';

        // Run artisan command synchronously
        $exitCode = Artisan::call('catalog:generate-price-template', [
            'org_id'   => $this->organizationId,
            '--output' => $outputPath,
        ]);

        if ($exitCode === 0 && file_exists($outputPath)) {
            // Copy to public storage so it's downloadable
            $publicName = 'price_templates/' . $org->slug . '_prices.csv';
            Storage::disk('public')->put(
                $publicName,
                file_get_contents($outputPath)
            );

            $this->uiMessageType = 'success';
            $this->uiMessage = 'Template generated! '
                . count(file($outputPath)) . ' rows. '
                . '<a href="' . Storage::disk("public")->url($publicName) . '" '
                . 'class="fw-bold" download>Download template CSV</a>';
        } else {
            $this->setError('Template generation failed. Check logs.');
        }
    }

    // ── Bulk upload ────────────────────────────────────────────────────────────

    public function uploadAndImport(): void
    {
        $this->validate([
            'organizationId' => 'required|exists:organizations,id',
            'priceFile'      => 'required|file|mimes:csv,txt|max:5120',
        ]);

        // Store uploaded file
        $stored = $this->priceFile->store('price_imports', 'local');
        $path   = storage_path('app/' . $stored);

        $this->runImport($path);
    }

    public function importFromServerPath(): void
    {
        $this->validate([
            'organizationId' => 'required|exists:organizations,id',
            'serverFilePath' => 'required|string',
        ]);

        $path = $this->serverFilePath;
        if (! file_exists($path)) {
            $this->setError("File not found on server: $path");
            return;
        }

        $this->runImport($path);
    }

    private function runImport(string $path): void
    {
        $args = [
            'file'           => $path,
            'org_id'         => $this->organizationId,
            '--skip-qdrant'  => $this->skipQdrant,
        ];
        if ($this->dryRun) {
            $args['--dry-run'] = true;
        }

        $exitCode = Artisan::call('catalog:import-prices', $args);
        $output   = Artisan::output();

        $this->importOutput  = $output;
        $this->uiMessageType = $exitCode === 0 ? 'success' : 'danger';
        $this->uiMessage     = $exitCode === 0
            ? ($this->dryRun ? 'Dry run complete. Review output, then run without dry-run.' : 'Import complete!')
            : 'Import finished with errors. See output below.';

        $this->loadStats();
    }

    // ── Qdrant sync ────────────────────────────────────────────────────────────

    public function syncUnsyncedToQdrant(): void
    {
        if (! $this->organizationId) return;

        $exitCode = Artisan::call('sync:organization-data', [
            'organization_id' => $this->organizationId,
            '--type'          => 'all',
        ]);
        $output = Artisan::output();

        $this->importOutput  = $output;
        $this->uiMessageType = $exitCode === 0 ? 'success' : 'danger';
        $this->uiMessage     = $exitCode === 0 ? 'Qdrant sync complete!' : 'Sync had errors. See output.';
    }

    // ── Slim CSV ───────────────────────────────────────────────────────────────

    public function generateSlimCsv(): void
    {
        // Find the original catalog CSV
        $candidates = [
            base_path('../catalog_product_20260221_073104.csv'),
            base_path('catalog_product_20260221_073104.csv'),
        ];

        // Also look in laravel/
        foreach (glob(base_path('../catalog_product_*.csv')) as $f) {
            $candidates[] = $f;
        }

        $inputPath = null;
        foreach ($candidates as $c) {
            if (file_exists($c)) { $inputPath = $c; break; }
        }

        if (! $inputPath) {
            $this->setError('Original catalog CSV not found. Use the terminal: python3 scripts/slim_catalog_csv.py --input PATH');
            return;
        }

        $outputPath = dirname($inputPath) . '/catalog_slim.csv';
        $script     = base_path('../scripts/slim_catalog_csv.py');

        if (! file_exists($script)) {
            $this->setError("slim_catalog_csv.py not found at: $script");
            return;
        }

        exec("python3 " . escapeshellarg($script)
            . " --input " . escapeshellarg($inputPath)
            . " --output " . escapeshellarg($outputPath)
            . " 2>&1", $lines, $exitCode);

        $this->importOutput = implode("\n", $lines);
        if ($exitCode === 0) {
            $sizeMb = round(filesize($outputPath) / 1024 / 1024, 1);
            $this->uiMessageType = 'success';
            $this->uiMessage = "Slim CSV created: {$outputPath} ({$sizeMb} MB)";
        } else {
            $this->setError('Slim CSV generation failed. See output below.');
        }
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function setError(string $msg): void
    {
        $this->uiMessageType = 'danger';
        $this->uiMessage     = $msg;
    }

    public function render()
    {
        return view('livewire.admin.catalog-price-manager', [
            'organizations' => Organization::orderBy('name')->get(['id', 'name']),
            'products'      => $this->products,
            'totalPages'    => $this->totalPages,
        ])->layout('layouts.admin');
    }
}
