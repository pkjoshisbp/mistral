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
            @if(session()->has('message'))
                <div class="alert alert-success">{{ session('message') }}</div>
            @endif
            @if(session()->has('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                CSV-only workflow is active. Google Sheets import is not used here.
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title mb-0">Import CSV Data to Organization + Qdrant</h3>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="runImport">
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
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Description Columns (comma separated)</label>
                                <input type="text" class="form-control" wire:model="descriptionColumns" placeholder="design,comfort,safety">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Content Columns (comma separated, optional)</label>
                                <input type="text" class="form-control" wire:model="contentColumns" placeholder="leave blank to use all columns">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Category Column (optional)</label>
                                <input type="text" class="form-control" wire:model="categoryColumn" placeholder="category">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Default Category *</label>
                                <input type="text" class="form-control" wire:model="defaultCategory" placeholder="general">
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
                    <h3 class="card-title mb-0">CSV Type Hints</h3>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>pricing/product</strong>: model catalogs, variants, prices, specs, availability</li>
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
        </div>
    </section>
</div>
