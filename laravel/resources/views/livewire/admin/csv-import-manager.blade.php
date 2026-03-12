<div>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-csv"></i> CSV Import</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">CSV Import</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            @if($uiMessage)
                <div class="alert alert-{{ $uiMessageType === 'error' ? 'danger' : 'success' }} alert-dismissible fade show">
                    {{ $uiMessage }}
                    <button type="button" class="close" wire:click="clearUiMessage" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif
            @if(session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if(session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                CSV-only workflow is active. Google Sheets import is not used here.
                <div class="mt-2 mb-0">
                    For model pricing, use dedicated CSV columns such as <strong>model</strong>, <strong>variant</strong>, <strong>ex_showroom_price_inr</strong>, and <strong>approx_on_road_price_inr</strong>.
                </div>
                <div class="mt-2 mb-0">
                    For subscription/credit plans, include columns like <strong>plan_name</strong>, <strong>plan_type</strong>, <strong>billing_period</strong>, <strong>usd_price</strong>, <strong>inr_price</strong>, <strong>token_limit</strong>, <strong>credit_validity_months</strong>, <strong>rollover</strong>, <strong>features</strong>, and <strong>keywords</strong>.
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Import CSV Data to Organization + Qdrant</h3>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="runImport">
                        @if($dryRun)
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                Dry Run is enabled. This will only preview changes and will not save any rows.
                            </div>
                        @endif

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Organization *</label>
                                <select class="form-control" wire:model="organizationId">
                                    <option value="">Select Organization</option>
                                    @foreach($this->organizations as $org)
                                        <option value="{{ $org->id }}">{{ $org->name }} ({{ $org->slug }})</option>
                                    @endforeach
                                </select>
                                @error('organizationId')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>CSV File *</label>
                                <input type="file" class="form-control" wire:model="csvFile" accept=".csv,text/csv">
                                @error('csvFile')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Dataset (optional)</label>
                                <input type="text" class="form-control" wire:model="dataset" placeholder="e.g. models_master">
                                <small class="text-muted d-block mt-1">Logical dataset name used to group imported rows.</small>
                                @error('dataset')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Data Type *</label>
                                <input type="text" class="form-control" wire:model="type" placeholder="e.g. pricing, faq, info, service, product">
                                <small class="text-muted">Hint: common values are pricing, faq, info, service, product.</small>
                                @error('type')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Qdrant Type *</label>
                                <select class="form-control" wire:model="qdrantType">
                                    <option value="info">info</option>
                                    <option value="faq">faq</option>
                                    <option value="service">service</option>
                                </select>
                                <small class="text-muted">Hint: choose info for catalog/pricing rows, faq for Q&A rows, service for service catalogs.</small>
                                @error('qdrantType')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Key Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="keyColumns" placeholder="model,variant">
                                <small class="text-muted">Hint: use stable unique columns to detect create/update/delete correctly.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Name Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="nameColumns" placeholder="model,variant">
                                <small class="text-muted d-block mt-1">Used to build title for each imported row.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Description Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="descriptionColumns" placeholder="design,comfort,safety">
                                <small class="text-muted d-block mt-1">Short descriptive fields for display and retrieval.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Content Columns (comma separated, optional)</label>
                                <input type="text" class="form-control" wire:model="contentColumns" placeholder="leave blank to use all columns">
                                <small class="text-muted d-block mt-1">Primary searchable content for embeddings; leave blank to include all columns.</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Category Column (optional)</label>
                                <input type="text" class="form-control" wire:model="categoryColumn" placeholder="category">
                                <small class="text-muted d-block mt-1">Category source column per row (optional).</small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Default Category *</label>
                                <input type="text" class="form-control" wire:model="defaultCategory" placeholder="general">
                                <small class="text-muted d-block mt-1">Used when category column is missing or empty.</small>
                                @error('defaultCategory')<small class="text-danger">{{ $message }}</small>@enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="dryRun" wire:model="dryRun">
                                    <label class="custom-control-label" for="dryRun">Dry Run (preview only)</label>
                                </div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <div class="custom-control custom-switch mt-2">
                                    <input type="checkbox" class="custom-control-input" id="skipQdrant" wire:model="skipQdrant">
                                    <label class="custom-control-label" for="skipQdrant">Skip Qdrant Sync</label>
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
                    @if(!$organizationId)
                        <p class="text-muted mb-0">Select an organization to view/edit imported datasets.</p>
                    @elseif($this->importedDatasets->isEmpty())
                        <p class="text-muted mb-0">No imported CSV datasets found yet for this organization.</p>
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
                                        <th style="width: 230px;">Actions</th>
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
                                                <button
                                                    type="button"
                                                    class="btn btn-sm btn-danger"
                                                    wire:click='deleteDataset(@json($dataset["dataset"]), @json($dataset["type"]))'
                                                    onclick="return confirm('Delete this full dataset and its vector records?')"
                                                >
                                                    <i class="fas fa-trash"></i> Delete
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

            <div class="card mt-3">
                <div class="card-header">
                    <h3 class="card-title mb-0">CSV Type Hints</h3>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>pricing/product</strong>: model catalogs, variants, prices, specs, availability</li>
                        <li><strong>pricing plans</strong>: use columns plan_name, plan_type, billing_period, usd_price, inr_price, token_limit, credit_validity_months, rollover, features</li>
                        <li><strong>faq</strong>: question-answer rows, policies, common support queries</li>
                        <li><strong>service</strong>: test catalog, package list, booking-related service details</li>
                        <li><strong>info</strong>: general business details, branch info, process descriptions</li>
                    </ul>
                </div>
            </div>

            @if($importOutput)
                <div class="card mt-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Import Output</h3>
                        <span class="badge {{ $importExitCode === 0 ? 'badge-success' : 'badge-danger' }}">
                            Exit: {{ $importExitCode }}
                        </span>
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
                                @if($uiMessage)
                                    <div class="p-2">
                                        <div class="alert alert-{{ $uiMessageType === 'error' ? 'danger' : 'success' }} mb-0">
                                            {{ $uiMessage }}
                                        </div>
                                    </div>
                                @endif
                                <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                    <small class="text-muted">You can edit existing rows, add new rows, and remove rows here. Save/Delete auto-syncs changes to Qdrant.</small>
                                    <button type="button" class="btn btn-sm btn-success" wire:click="addEditorRow">
                                        <i class="fas fa-plus"></i> Add Row
                                    </button>
                                </div>
                                {{-- Dataset-level AI instruction --}}
                                <div class="px-3 py-2 bg-light border-bottom">
                                    <label class="font-weight-bold mb-1" style="font-size:0.85rem;">
                                        <i class="fas fa-robot text-primary mr-1"></i> Custom AI Instruction for this Dataset
                                        <small class="text-muted font-weight-normal ml-1">(optional — scoped to this organization + dataset)</small>
                                    </label>
                                    <textarea class="form-control form-control-sm"
                                        rows="2"
                                        wire:model.lazy="datasetInstruction"
                                        placeholder="e.g. Keep responses concise and business-friendly. | e.g. Always mention pricing in INR first and USD in brackets."
                                    ></textarea>
                                    <small class="text-muted">This instruction will be included when AI answers using this dataset for the selected organization.</small>
                                </div>
                                <div class="table-responsive" style="max-height: 60vh; overflow: auto;">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead class="thead-light" style="position: sticky; top: 0; z-index: 2;">
                                            <tr>
                                                <th style="min-width: 80px;">ID</th>
                                                <th style="min-width: 210px;">External Key</th>
                                                <th style="min-width: 200px;">Name</th>
                                                <th style="min-width: 140px;">Category</th>
                                                <th style="min-width: 280px;">{{ $this->editorDescriptionLabel }}</th>
                                                <th style="min-width: 360px;">{{ $this->editorContentLabel }}</th>
                                                @if($this->showOperationalFields)
                                                    <th style="min-width: 180px;">Working Schedule</th>
                                                    <th style="min-width: 220px;">Leave / Absence Notes</th>
                                                @endif
                                                <th style="min-width: 220px;" title="Alternate phrases/terms to improve retrieval, e.g. USG, ultrasound">Keywords</th>
                                                <th style="min-width: 90px;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($editorRows as $index => $row)
                                                <tr wire:key="editor-row-{{ $index }}">
                                                    <td>
                                                        {{ $row['id'] ?: 'new' }}
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.id">
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.external_key">
                                                        <input type="hidden" wire:model="editorRows.{{ $index }}.qdrant_type">
                                                    </td>
                                                    <td>
                                                        <small>{{ $row['external_key'] }}</small>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.lazy="editorRows.{{ $index }}.name">
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control form-control-sm" wire:model.lazy="editorRows.{{ $index }}.category">
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="3" wire:model.lazy="editorRows.{{ $index }}.description" placeholder="{{ $this->editorDescriptionPlaceholder }}"></textarea>
                                                    </td>
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="4" wire:model.lazy="editorRows.{{ $index }}.content" placeholder="{{ $this->editorContentPlaceholder }}"></textarea>
                                                    </td>
                                                    @if($this->showOperationalFields)
                                                        <td>
                                                            <input type="text" class="form-control form-control-sm"
                                                                wire:model.lazy="editorRows.{{ $index }}.working_schedule"
                                                                placeholder="e.g. Mon-Fri, 9am-5pm">
                                                            <small class="text-muted">Mon-Fri / Mon-Sun / All Days</small>
                                                        </td>
                                                        <td>
                                                            <textarea class="form-control form-control-sm" rows="2"
                                                                wire:model.lazy="editorRows.{{ $index }}.leave_notes"
                                                                placeholder="e.g. 7th Mar – 11th Mar on leave"></textarea>
                                                        </td>
                                                    @endif
                                                    <td>
                                                        <textarea class="form-control form-control-sm" rows="2"
                                                            wire:model.lazy="editorRows.{{ $index }}.search_keywords"
                                                            placeholder="e.g. free trial, starter free, no cost"></textarea>
                                                        <small class="text-muted" style="font-size:0.72rem;">Comma-separated alternate phrases used in user queries.</small>
                                                    </td>
                                                    <td>
                                                        <button
                                                            type="button"
                                                            class="btn btn-sm btn-outline-danger"
                                                            wire:click="removeEditorRow({{ $index }})"
                                                            onclick="return confirm('Delete this row?')"
                                                        >
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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
