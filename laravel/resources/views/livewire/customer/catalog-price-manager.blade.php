<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-tags me-2"></i>Catalog Price Manager</h1>
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
                    <button class="btn btn-outline-primary btn-sm w-100" wire:click="syncToQdrant"
                            wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="syncToQdrant">
                            <i class="fas fa-sync-alt me-1"></i> Sync to AI
                        </span>
                        <span wire:loading wire:target="syncToQdrant">
                            <i class="fas fa-spinner fa-spin me-1"></i> Syncing…
                        </span>
                    </button>
                </div>
            </div>

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
                                    <th style="width:170px">Retail Price (₹)</th>
                                    <th style="width:170px">Sale Price (₹)</th>
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
                                           title="{{ $prod->is_synced ? 'Synced to AI' : 'Pending sync' }}"></i>
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
                                <strong><i class="fas fa-info-circle me-1"></i>How to bulk-update prices:</strong>
                                <ol class="mb-0 mt-1">
                                    <li>Click <strong>Generate Price Template</strong> and download the CSV</li>
                                    <li>Open in Excel or Google Sheets and fill in the <code>price</code> column (INR numbers only)</li>
                                    <li>Save as CSV and upload below</li>
                                    <li>Click <strong>Sync to AI</strong> to apply the changes to the AI assistant</li>
                                </ol>
                            </div>

                            {{-- Generate template --}}
                            <div class="mb-4">
                                <button class="btn btn-outline-secondary" wire:click="generateTemplate"
                                        wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="generateTemplate">
                                        <i class="fas fa-download me-1"></i> Generate Price Template
                                    </span>
                                    <span wire:loading wire:target="generateTemplate">
                                        <i class="fas fa-spinner fa-spin me-1"></i> Generating…
                                    </span>
                                </button>
                                <small class="text-muted ms-2">Creates a CSV with your products and empty price columns to fill in.</small>
                            </div>

                            <div class="alert alert-warning small">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                <strong>File size note:</strong> Max upload is limited by your server's PHP settings.
                                The price template is typically &lt;500 KB so it should upload fine.
                                If you get an upload error, contact your administrator.
                            </div>

                            <form wire:submit.prevent="uploadAndImport">
                                <div class="mb-3">
                                    <label class="form-label fw-semibold">Upload filled price CSV</label>
                                    <input type="file" wire:model="priceFile" class="form-control" accept=".csv,.txt">
                                    @error('priceFile') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="dryRun"
                                           wire:model="dryRun" value="1">
                                    <label class="form-check-label" for="dryRun">
                                        Dry run — preview changes without saving
                                    </label>
                                </div>
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="skipQdrant"
                                           wire:model="skipQdrant" value="1">
                                    <label class="form-check-label" for="skipQdrant">
                                        Skip AI sync now (I'll sync manually later)
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

                </div>{{-- card-body --}}
            </div>{{-- card --}}

        </div>
    </section>
</div>
