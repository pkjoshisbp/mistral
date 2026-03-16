<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-tags me-2"></i>Catalog Price Manager</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <select wire:model.live="organizationId" class="form-select d-inline-block w-auto">
                        <option value="">— Select Organization —</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">

            {{-- Alert --}}
            @if($uiMessage)
                <div class="alert alert-{{ $uiMessageType }} alert-dismissible fade show" role="alert">
                    {!! $uiMessage !!}
                    <button type="button" class="btn-close" wire:click="$set('uiMessage','')"></button>
                </div>
            @endif

            {{-- Stats bar --}}
            @if($organizationId)
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="info-box bg-light">
                        <span class="info-box-icon bg-primary"><i class="fas fa-box"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Total Products</span>
                            <span class="info-box-number">{{ number_format($totalProducts) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-light">
                        <span class="info-box-icon {{ $pricedProducts < $totalProducts ? 'bg-warning' : 'bg-success' }}">
                            <i class="fas fa-rupee-sign"></i>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">With Retail Price</span>
                            <span class="info-box-number">{{ number_format($pricedProducts) }}
                                <small class="text-muted">/ {{ number_format($totalProducts) }}</small>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="info-box bg-light">
                        <span class="info-box-icon bg-danger"><i class="fas fa-exclamation-triangle"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Missing Price</span>
                            <span class="info-box-number">{{ number_format($totalProducts - $pricedProducts) }}</span>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 d-flex align-items-center">
                    <button class="btn btn-outline-primary btn-sm w-100" wire:click="syncUnsyncedToQdrant"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="syncUnsyncedToQdrant">
                            <i class="fas fa-sync-alt me-1"></i> Sync All to Qdrant
                        </span>
                        <span wire:loading wire:target="syncUnsyncedToQdrant">
                            <i class="fas fa-spinner fa-spin me-1"></i> Syncing…
                        </span>
                    </button>
                </div>
            </div>
            @endif

            {{-- Tabs --}}
            <div class="card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'browse' ? 'active' : '' }}"
                               wire:click.prevent="$set('activeTab','browse')" href="#">
                               <i class="fas fa-list me-1"></i> Browse &amp; Edit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'upload' ? 'active' : '' }}"
                               wire:click.prevent="$set('activeTab','upload')" href="#">
                               <i class="fas fa-upload me-1"></i> Bulk Price Upload
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab === 'tools' ? 'active' : '' }}"
                               wire:click.prevent="$set('activeTab','tools')" href="#">
                               <i class="fas fa-tools me-1"></i> Tools
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body">

                    {{-- ══════════════════ TAB: Browse & Edit ══════════════════ --}}
                    @if($activeTab === 'browse')
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" wire:model.live.debounce.400ms="search"
                                   class="form-control" placeholder="Search by name or SKU…">
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="priceFilter" class="form-select">
                                <option value="all">All products</option>
                                <option value="missing">Missing price only</option>
                                <option value="has_price">Has price</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="perPage" class="form-select">
                                <option value="25">25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="100">100 per page</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:40px">#</th>
                                    <th>Name / SKU</th>
                                    <th style="width:160px">Retail Price (₹)</th>
                                    <th style="width:160px">Sale Price (₹)</th>
                                    <th style="width:80px">Synced</th>
                                    <th style="width:120px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($products as $prod)
                                @php
                                    $csv   = $prod->metadata['csv'] ?? [];
                                    $price = $csv['price'] ?? '';
                                    $sp    = $csv['special_price'] ?? '';
                                    $sku   = $csv['sku'] ?? $prod->name;
                                    $editing = isset($editingRows[$prod->id]);
                                @endphp
                                <tr class="{{ ($price === '' || $price === '0') ? 'table-warning' : '' }}">
                                    <td class="text-muted small">{{ $prod->id }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $prod->name }}</div>
                                        @if($sku !== $prod->name)
                                            <small class="text-muted">SKU: {{ $sku }}</small>
                                        @endif
                                    </td>

                                    @if($editing)
                                        <td>
                                            <input type="number" min="0" step="1"
                                                   wire:model.defer="editingRows.{{ $prod->id }}.price"
                                                   class="form-control form-control-sm"
                                                   placeholder="e.g. 26000">
                                        </td>
                                        <td>
                                            <input type="number" min="0" step="1"
                                                   wire:model.defer="editingRows.{{ $prod->id }}.special_price"
                                                   class="form-control form-control-sm"
                                                   placeholder="Sale price">
                                        </td>
                                    @else
                                        <td>
                                            @if($price && $price !== '0')
                                                <span class="badge bg-success">₹{{ number_format((float)$price, 0, '.', ',') }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">Not set</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($sp && $sp !== '0')
                                                <span class="text-danger small">₹{{ number_format((float)$sp, 0, '.', ',') }}</span>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="text-center">
                                        <i class="fas fa-circle small {{ $prod->is_synced ? 'text-success' : 'text-warning' }}"
                                           title="{{ $prod->is_synced ? 'Synced' : 'Pending sync' }}"></i>
                                    </td>
                                    <td>
                                        @if($editing)
                                            <button class="btn btn-success btn-sm me-1" wire:click="saveRow({{ $prod->id }})">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button class="btn btn-secondary btn-sm" wire:click="cancelEdit({{ $prod->id }})">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        @else
                                            <button class="btn btn-outline-primary btn-sm" wire:click="startEdit({{ $prod->id }})">
                                                <i class="fas fa-pencil-alt"></i> Edit
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        No products found for the current filter.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination --}}
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">Page {{ $page }} of {{ $totalPages }}</div>
                        <div>
                            <button class="btn btn-sm btn-outline-secondary me-1" wire:click="prevPage"
                                    @if($page <= 1) disabled @endif>
                                <i class="fas fa-chevron-left"></i> Prev
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" wire:click="nextPage"
                                    @if($page >= $totalPages) disabled @endif>
                                Next <i class="fas fa-chevron-right"></i>
                            </button>
                        </div>
                    </div>
                    @endif

                    {{-- ══════════════════ TAB: Bulk Upload ══════════════════ --}}
                    @if($activeTab === 'upload')
                    <div class="row">
                        <div class="col-lg-7">

                            <div class="alert alert-info">
                                <strong><i class="fas fa-info-circle me-1"></i>How it works:</strong>
                                <ol class="mb-0 mt-1">
                                    <li>Go to <strong>Tools</strong> tab → <em>Generate Price Template</em> → download the CSV</li>
                                    <li>Open the CSV in Excel / Google Sheets</li>
                                    <li>Fill in the <code>price</code> column with retail prices (in INR, numbers only)</li>
                                    <li>Save as CSV and upload here</li>
                                </ol>
                            </div>

                            <div class="alert alert-warning small">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>Upload size limit:</strong> Your server's current PHP web upload limit may be set to 2 MB.
                                If your price CSV is larger, either raise <code>upload_max_filesize</code> in ISPConfig,
                                or use the <strong>Server Path</strong> option below.
                                The price template is typically &lt;500 KB, so it should upload fine.
                            </div>

                            <h6 class="mt-3">Option A — Upload price CSV file</h6>
                            <form wire:submit.prevent="uploadAndImport">
                                <div class="mb-3">
                                    <label class="form-label">Price CSV file</label>
                                    <input type="file" wire:model="priceFile" class="form-control" accept=".csv,.txt">
                                    @error('priceFile') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="dryRun"
                                           wire:model="dryRun" value="1">
                                    <label class="form-check-label" for="dryRun">
                                        Dry run (preview changes, don't save)
                                    </label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="skipQdrant"
                                           wire:model="skipQdrant" value="1">
                                    <label class="form-check-label" for="skipQdrant">
                                        Skip Qdrant sync (update DB only)
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="uploadAndImport">
                                        <i class="fas fa-upload me-1"></i>
                                        {{ $dryRun ? 'Run Dry Preview' : 'Upload & Import Prices' }}
                                    </span>
                                    <span wire:loading wire:target="uploadAndImport">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Processing…
                                    </span>
                                </button>
                            </form>

                            <hr>

                            <h6>Option B — CSV already on server</h6>
                            <p class="text-muted small">If the price CSV is already on the server (e.g. uploaded via SFTP), enter the full path.</p>
                            <div class="input-group mb-3">
                                <input type="text" wire:model="serverFilePath" class="form-control"
                                       placeholder="/var/www/…/storage/app/price_templates/org-slug_prices.csv">
                                <button class="btn btn-outline-primary" wire:click="importFromServerPath"
                                        wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="importFromServerPath">Import</span>
                                    <span wire:loading wire:target="importFromServerPath"><i class="fas fa-spinner fa-spin"></i></span>
                                </button>
                            </div>
                        </div>

                        <div class="col-lg-5">
                            @if($importOutput)
                            <h6>Import output</h6>
                            <pre class="bg-dark text-white p-3 rounded small"
                                 style="max-height:400px;overflow-y:auto">{{ $importOutput }}</pre>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- ══════════════════ TAB: Tools ══════════════════ --}}
                    @if($activeTab === 'tools')
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><strong><i class="fas fa-file-csv me-2"></i>Generate Price Template</strong></div>
                                <div class="card-body">
                                    <p class="text-muted small">
                                        Creates a CSV with columns: <code>db_id, sku, name, price, special_price</code>.
                                        The <code>price</code> and <code>special_price</code> columns will be empty — fill them in and re-upload.
                                    </p>
                                    <button class="btn btn-primary" wire:click="generateTemplate"
                                            wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="generateTemplate">
                                            <i class="fas fa-download me-1"></i> Generate &amp; Download
                                        </span>
                                        <span wire:loading wire:target="generateTemplate">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Generating…
                                        </span>
                                    </button>
                                    <p class="text-muted small mt-2">File will be saved to
                                        <code>storage/app/price_templates/</code> and a download link will appear above.</p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header"><strong><i class="fas fa-compress-alt me-2"></i>Slim Catalog CSV</strong></div>
                                <div class="card-body">
                                    <p class="text-muted small">
                                        Strips the original catalog CSV from 87 columns down to ~16 essential columns
                                        (name, description, price, SKU, stock, etc.). Reduces file size so it can
                                        be opened and edited in Excel or uploaded via admin.
                                    </p>
                                    <button class="btn btn-secondary" wire:click="generateSlimCsv"
                                            wire:loading.attr="disabled">
                                        <span wire:loading.remove wire:target="generateSlimCsv">
                                            <i class="fas fa-compress-alt me-1"></i> Create Slim CSV
                                        </span>
                                        <span wire:loading wire:target="generateSlimCsv">
                                            <i class="fas fa-spinner fa-spin me-1"></i> Processing…
                                        </span>
                                    </button>
                                    <p class="text-muted small mt-2">
                                        Also runnable manually: <code>python3 scripts/slim_catalog_csv.py</code>
                                    </p>
                                    @if($importOutput && $activeTab === 'tools')
                                    <pre class="bg-dark text-white p-2 rounded small mt-2"
                                         style="max-height:200px;overflow-y:auto">{{ $importOutput }}</pre>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-header"><strong><i class="fas fa-terminal me-2"></i>Terminal Commands</strong></div>
                                <div class="card-body">
                                    <p class="text-muted small mb-2">You can also run these directly via SSH / terminal:</p>
                                    <pre class="bg-light p-3 rounded small">
# 1. Generate price template for an org
php artisan catalog:generate-price-template 19

# 2. Dry-run: preview what would change
php artisan catalog:import-prices /path/to/prices_filled.csv 19 --dry-run

# 3. Import prices (update DB + sync Qdrant)
php artisan catalog:import-prices /path/to/prices_filled.csv 19

# 4. Import prices (DB only, sync Qdrant later)
php artisan catalog:import-prices /path/to/prices_filled.csv 19 --skip-qdrant

# 5. Clean product content (remove legacy internal fields, junk, full category paths, HTML)
php artisan catalog:clean-content 19 --dry-run   # preview first
php artisan catalog:clean-content 19 --skip-qdrant  # DB only
php artisan catalog:clean-content 19              # clean + sync Qdrant

# 6. Re-sync already-cleaned rows to Qdrant (if sync was interrupted)
php artisan catalog:clean-content 19 --only-qdrant --qdrant-batch=20 --qdrant-timeout=300

# 7. Slim down the catalog CSV
python3 scripts/slim_catalog_csv.py \
    --input laravel/catalog_product_20260221_073104.csv \
    --output laravel/catalog_slim.csv</pre>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                </div>{{-- card-body --}}
            </div>{{-- card --}}

        </div>
    </section>
</div>
