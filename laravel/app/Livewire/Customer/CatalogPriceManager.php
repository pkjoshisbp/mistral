<?php

namespace App\Livewire\Customer;

use App\Models\OrganizationData;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

class CatalogPriceManager extends Component
{
    use WithFileUploads;

    // ── Active tab ─────────────────────────────────────────────────────────────
    public string $activeTab = 'browse';

    // ── Browse / inline-edit tab ───────────────────────────────────────────────
    public string $search      = '';
    public string $priceFilter = 'all';
    public int    $perPage     = 50;
    public int    $page        = 1;
    public array  $editingRows = [];

    // ── Bulk upload tab ────────────────────────────────────────────────────────
    public $priceFile;
    public bool $dryRun     = true;
    public bool $skipQdrant = false;

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
            'priceFile' => 'nullable|file|mimes:csv,txt|max:5120',
        ];
    }

    public function getOrganizationProperty()
    {
        return auth()->user()?->organizations()?->first();
    }

    public function mount(): void
    {
        if ($this->organization) {
            $this->loadStats();
        }
    }

    public function updatedSearch(): void  { $this->page = 1; }
    public function updatedPriceFilter(): void { $this->page = 1; }

    private function loadStats(): void
    {
        $org = $this->organization;
        if (! $org) return;

        $this->totalProducts = OrganizationData::where('organization_id', $org->id)
            ->where('type', 'product')->count();

        $this->pricedProducts = OrganizationData::where('organization_id', $org->id)
            ->where('type', 'product')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) NOT IN ('', '0', '0.0000', 'null')
                        AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.csv.price')) IS NOT NULL")
            ->count();
    }

    // ── Browse ─────────────────────────────────────────────────────────────────

    public function getProductsProperty()
    {
        $org = $this->organization;
        if (! $org) return collect();

        $q = OrganizationData::where('organization_id', $org->id)
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
        $org = $this->organization;
        if (! $org) return 1;

        $q = OrganizationData::where('organization_id', $org->id)->where('type', 'product');

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

    public function nextPage(): void { if ($this->page < $this->totalPages) $this->page++; }
    public function prevPage(): void { if ($this->page > 1) $this->page--; }

    public function startEdit(int $id): void
    {
        $this->abortIfNotOwner($id);
        $row = OrganizationData::find($id);
        $csv = $row->metadata['csv'] ?? [];
        $this->editingRows[$id] = [
            'price'         => $csv['price'] ?? '',
            'special_price' => $csv['special_price'] ?? '',
        ];
    }

    public function cancelEdit(int $id): void { unset($this->editingRows[$id]); }

    public function saveRow(int $id): void
    {
        $this->abortIfNotOwner($id);
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
        $this->uiMessage     = "Price saved for \u{AB}{$record->name}\u{BB}. Use 'Sync to AI' when ready.";
    }

    // ── Generate price template ────────────────────────────────────────────────

    public function generateTemplate(): void
    {
        $org = $this->organization;
        if (! $org) { $this->setError('No organization found.'); return; }

        $dir = storage_path('app/price_templates');
        if (! is_dir($dir)) mkdir($dir, 0775, true);
        $outputPath = $dir . '/' . $org->slug . '_prices.csv';

        $exitCode = Artisan::call('catalog:generate-price-template', [
            'org_id'   => $org->id,
            '--output' => $outputPath,
        ]);

        if ($exitCode === 0 && file_exists($outputPath)) {
            Storage::disk('public')->put(
                'price_templates/' . $org->slug . '_prices.csv',
                file_get_contents($outputPath)
            );
            $url = Storage::disk('public')->url('price_templates/' . $org->slug . '_prices.csv');
            $this->uiMessageType = 'success';
            $this->uiMessage = 'Template ready! <a href="' . $url . '" class="fw-bold" download>Download price template CSV</a>';
        } else {
            $this->setError('Template generation failed. Please contact support.');
        }
    }

    // ── Bulk upload ────────────────────────────────────────────────────────────

    public function uploadAndImport(): void
    {
        $org = $this->organization;
        if (! $org) { $this->setError('No organization found.'); return; }

        $this->validate(['priceFile' => 'required|file|mimes:csv,txt|max:5120']);

        $stored = $this->priceFile->store('price_imports', 'local');
        $path   = storage_path('app/' . $stored);

        $args = [
            'file'          => $path,
            'org_id'        => $org->id,
            '--skip-qdrant' => $this->skipQdrant,
        ];
        if ($this->dryRun) $args['--dry-run'] = true;

        $exitCode = Artisan::call('catalog:import-prices', $args);
        $output   = Artisan::output();

        $this->importOutput  = $output;
        $this->uiMessageType = $exitCode === 0 ? 'success' : 'danger';
        $this->uiMessage     = $exitCode === 0
            ? ($this->dryRun ? 'Dry run complete — review output, then re-upload without dry run.' : 'Import complete!')
            : 'Import finished with errors. See output below.';

        $this->loadStats();
    }

    // ── Qdrant sync ────────────────────────────────────────────────────────────

    public function syncToQdrant(): void
    {
        $org = $this->organization;
        if (! $org) return;

        $exitCode = Artisan::call('sync:organization-data', [
            'organization_id' => $org->id,
            '--type'          => 'all',
        ]);
        $output = Artisan::output();

        $this->importOutput  = $output;
        $this->uiMessageType = $exitCode === 0 ? 'success' : 'danger';
        $this->uiMessage     = $exitCode === 0 ? 'Qdrant sync complete!' : 'Sync had errors. See output.';
    }

    // ── Security helper ────────────────────────────────────────────────────────

    private function abortIfNotOwner(int $id): void
    {
        $org = $this->organization;
        if (! $org) abort(403);

        $exists = OrganizationData::where('id', $id)
            ->where('organization_id', $org->id)
            ->exists();

        if (! $exists) abort(403);
    }

    private function setError(string $msg): void
    {
        $this->uiMessageType = 'danger';
        $this->uiMessage     = $msg;
    }

    public function render()
    {
        return view('livewire.customer.catalog-price-manager', [
            'products'   => $this->products,
            'totalPages' => $this->totalPages,
        ])->layout('layouts.customer');
    }
}
