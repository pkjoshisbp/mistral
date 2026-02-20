<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-csv"></i> CSV Import</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">CSV Import</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if(session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if(session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                Import data using CSV only. This updates, inserts, and deletes rows based on key columns.
                <div class="mt-2 mb-0">
                    For model pricing, keep dedicated columns like <strong>model</strong>, <strong>variant</strong>, <strong>ex_showroom_price_inr</strong>, and <strong>approx_on_road_price_inr</strong>.
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Import CSV to Your Organization</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="font-weight-bold">Organization</label>
                        <div class="form-control bg-light">{{ $this->organization?->name }} ({{ $this->organization?->slug }})</div>
                    </div>

                    <form wire:submit.prevent="runImport">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>CSV File *</label>
                                <input type="file" class="form-control" wire:model="csvFile" accept=".csv,text/csv">
                                @error('csvFile')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Dataset (optional)</label>
                                <input type="text" class="form-control" wire:model="dataset" placeholder="e.g. models_master">
                                <small class="text-muted d-block mt-1">Logical name used to group imported rows (example: <strong>odyssey_locations_contacts</strong>).</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Data Type *</label>
                                <input type="text" class="form-control" wire:model="type" placeholder="pricing, faq, info, service, product">
                                <small class="text-muted">Hint: use pricing for model/price rows, faq for Q&A, service for service catalogs.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Qdrant Type *</label>
                                <select class="form-control" wire:model="qdrantType">
                                    <option value="info">info</option>
                                    <option value="faq">faq</option>
                                    <option value="service">service</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Key Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="keyColumns" placeholder="model,variant">
                                <small class="text-muted d-block mt-1">These columns must uniquely identify each row for correct create/update/delete sync.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Name Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="nameColumns" placeholder="model,variant">
                                <small class="text-muted d-block mt-1">Used to generate row title shown in AI context.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Description Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="descriptionColumns" placeholder="design,comfort,safety">
                                <small class="text-muted d-block mt-1">Short summary fields for each row.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Content Columns (optional)</label>
                                <input type="text" class="form-control" wire:model="contentColumns" placeholder="leave blank to use all columns">
                                <small class="text-muted d-block mt-1">Main searchable text; leave blank to include all CSV columns.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Category Column (optional)</label>
                                <input type="text" class="form-control" wire:model="categoryColumn" placeholder="category">
                                <small class="text-muted d-block mt-1">If provided, category is read from this CSV column per row.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Default Category *</label>
                                <input type="text" class="form-control" wire:model="defaultCategory" placeholder="general">
                                <small class="text-muted d-block mt-1">Used when category column is empty or not configured.</small>
                                @error('defaultCategory')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="dryRunCustomer" wire:model="dryRun">
                                    <label class="custom-control-label" for="dryRunCustomer">Dry Run (preview only)</label>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="skipQdrantCustomer" wire:model="skipQdrant">
                                    <label class="custom-control-label" for="skipQdrantCustomer">Skip Qdrant Sync</label>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Run CSV Import
                        </button>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">Already Imported CSV Datasets</h3>
                </div>
                <div class="card-body">
                    @if($this->importedDatasets->isEmpty())
                        <p class="text-muted mb-0">No imported CSV datasets found yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Dataset</th>
                                        <th>Type</th>
                                        <th>Qdrant Type</th>
                                        <th>Rows</th>
                                        <th>Source File</th>
                                        <th>Last Updated</th>
                                        <th style="width: 180px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($this->importedDatasets as $dataset)
                                        <tr>
                                            <td>{{ $dataset['dataset'] }}</td>
                                            <td>{{ $dataset['type'] }}</td>
                                            <td>{{ $dataset['qdrant_type'] }}</td>
                                            <td>{{ $dataset['row_count'] }}</td>
                                            <td>{{ $dataset['source_file'] ?: '-' }}</td>
                                            <td>{{ $dataset['last_updated_at'] ?: '-' }}</td>
                                            <td>
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-primary"
                                                    wire:click='openDatasetEditor(@json($dataset["dataset"]), @json($dataset["type"]))'
                                                >
                                                    <i class="fas fa-edit"></i> View / Edit
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            @if($importOutput)
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Import Output</h3>
                        <span class="badge {{ $importExitCode === 0 ? 'badge-success' : 'badge-danger' }}">Exit: {{ $importExitCode }}</span>
                    </div>
                    <div class="card-body">
                        <pre class="mb-0" style="white-space: pre-wrap;">{{ $importOutput }}</pre>
                    </div>
                </div>
            @endif

            @if($showEditorModal)
                <div class="modal fade show d-block" tabindex="-1" role="dialog" style="background: rgba(0,0,0,0.5);">
                    <div class="modal-dialog modal-xl" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    Edit Imported CSV Data
                                    <small class="text-muted d-block">
                                        Dataset: {{ $selectedDataset }} | Type: {{ $selectedType }} | Source: {{ $selectedSourceFile ?: '-' }}
                                    </small>
                                </h5>
                                <button type="button" class="close" wire:click="closeEditorModal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body p-0">
                                <div class="table-responsive" style="max-height: 70vh; overflow: auto;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-light" style="position: sticky; top: 0; z-index: 2;">
                                            <tr>
                                                <th style="min-width: 80px;">ID</th>
                                                <th style="min-width: 210px;">External Key</th>
                                                <th style="min-width: 200px;">Name</th>
                                                <th style="min-width: 140px;">Category</th>
                                                <th style="min-width: 280px;">Description</th>
                                                <th style="min-width: 360px;">Content</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($editorRows as $index => $row)
                                                <tr>
                                                    <td>
                                                        {{ $row['id'] }}
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.id">
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.external_key">
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.qdrant_type">
                                                    </td>
                                                    <td>
                                                        <small>{{ $row['external_key'] }}</small>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.defer="editorRows.{{ $index }}.name">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.defer="editorRows.{{ $index }}.category">
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="3" wire:model.defer="editorRows.{{ $index }}.description"></textarea>
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="4" wire:model.defer="editorRows.{{ $index }}.content"></textarea>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" wire:click="closeEditorModal">Close</button>
                                <button type="button" class="btn btn-primary" wire:click="saveEditedRows">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </section>
</div>
