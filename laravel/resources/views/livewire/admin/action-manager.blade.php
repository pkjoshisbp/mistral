<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Action Manager</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Action Manager</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="container-fluid">
            <!-- Flash Messages -->
            @if (session()->has('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            @endif

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Live Data Actions Configuration</h3>
                    <div class="card-tools">
                        <button wire:click="openModal" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Action
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Search and Filters -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" wire:model.live.debounce.300ms="search" 
                                   class="form-control" placeholder="Search actions...">
                        </div>
                        <div class="col-md-3">
                            <select wire:model.live="selectedOrganization" class="form-control">
                                <option value="">All Organizations</option>
                                @foreach($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Actions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Organization</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Score Threshold</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($actions as $action)
                                    <tr>
                                        <td>{{ $action->organization->name }}</td>
                                        <td>
                                            <strong>{{ $action->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($action->description, 50) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ $action->action_type }}</span>
                                        </td>
                                        <td>
                                            @switch($action->source_type)
                                                @case('api')
                                                    <span class="badge badge-primary">API</span>
                                                    @break
                                                @case('csv')
                                                    <span class="badge badge-success">CSV</span>
                                                    @break
                                                @case('excel')
                                                    <span class="badge badge-warning">Excel</span>
                                                    @break
                                                @case('database')
                                                    <span class="badge badge-secondary">Database</span>
                                                    @break
                                                @case('google_sheets')
                                                    <span class="badge badge-info">Google Sheets</span>
                                                    @break
                                            @endswitch
                                        </td>
                                        <td>
                                            <button wire:click="toggleStatus({{ $action->id }})" 
                                                    class="btn btn-sm {{ $action->is_active ? 'btn-success' : 'btn-danger' }}">
                                                {{ $action->is_active ? 'Active' : 'Inactive' }}
                                            </button>
                                        </td>
                                        <td>{{ $action->min_score_threshold }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button wire:click="testAction({{ $action->id }})" 
                                                        class="btn btn-sm btn-info" title="Test Action">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <button wire:click="openModal({{ $action->id }})" 
                                                        class="btn btn-sm btn-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button wire:click="delete({{ $action->id }})" 
                                                        class="btn btn-sm btn-danger" 
                                                        onclick="return confirm('Are you sure you want to delete this action?')" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center">No actions found</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    {{ $actions->links() }}
                </div>
            </div>
        </div>
    </section>

    <!-- Action Modal -->
    @if($showModal)
        <div class="modal fade show" style="display: block; background-color: rgba(0,0,0,0.5);" 
             tabindex="-1" role="dialog">
            <div class="modal-dialog modal-xl" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">
                            {{ $editingAction ? 'Edit Action' : 'Create New Action' }}
                        </h4>
                        <button type="button" class="close" wire:click="closeModal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>

                    <form wire:submit.prevent="save">
                        <div class="modal-body">
                            <div class="row">
                                <!-- Basic Information -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Organization *</label>
                                        <select wire:model="organization_id" class="form-control @error('organization_id') is-invalid @enderror">
                                            <option value="">Select Organization</option>
                                            @foreach($organizations as $org)
                                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('organization_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Name *</label>
                                        <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror">
                                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Action Type *</label>
                                        <select wire:model="action_type" class="form-control @error('action_type') is-invalid @enderror">
                                            <option value="">Select Type</option>
                                            <option value="pricing">Pricing Information</option>
                                            <option value="availability">Availability Check</option>
                                            <option value="booking">Booking System</option>
                                            <option value="inventory">Inventory Check</option>
                                            <option value="status">Status Check</option>
                                            <option value="search">Search Query</option>
                                            <option value="custom">Custom Action</option>
                                        </select>
                                        @error('action_type')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label>Description *</label>
                                        <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" rows="3"></textarea>
                                        @error('description')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Source Configuration -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Source Type *</label>
                                        <select wire:model.live="source_type" class="form-control @error('source_type') is-invalid @enderror">
                                            <option value="">Select Source</option>
                                            <option value="api">API Endpoint</option>
                                            <option value="csv">CSV File</option>
                                            <option value="excel">Excel File</option>
                                            <option value="database">Database Query</option>
                                            <option value="google_sheets">Google Sheets</option>
                                        </select>
                                        @error('source_type')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>

                                    <!-- Source Type Specific Fields -->
                                    @if($source_type === 'api')
                                        <div class="form-group">
                                            <label>API URL</label>
                                            <input type="url" wire:model="api_url" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>HTTP Method</label>
                                            <select wire:model="api_method" class="form-control">
                                                <option value="GET">GET</option>
                                                <option value="POST">POST</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Timeout (seconds)</label>
                                            <input type="number" wire:model="api_timeout" class="form-control" min="1" max="300">
                                        </div>
                                    @endif

                                    @if($source_type === 'csv')
                                        <div class="form-group">
                                            <label>CSV File Path</label>
                                            <input type="text" wire:model="csv_file_path" class="form-control" 
                                                   placeholder="/path/to/file.csv">
                                        </div>
                                        <div class="form-group">
                                            <label>Delimiter</label>
                                            <input type="text" wire:model="csv_delimiter" class="form-control" maxlength="1">
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" wire:model="csv_has_header" class="form-check-input" id="csv_has_header">
                                            <label class="form-check-label" for="csv_has_header">Has Header Row</label>
                                        </div>
                                    @endif

                                    @if($source_type === 'excel')
                                        <div class="form-group">
                                            <label>Excel File Path</label>
                                            <input type="text" wire:model="excel_file_path" class="form-control" 
                                                   placeholder="/path/to/file.xlsx">
                                        </div>
                                        <div class="form-group">
                                            <label>Sheet Name (optional)</label>
                                            <input type="text" wire:model="excel_sheet_name" class="form-control">
                                        </div>
                                    @endif

                                    @if($source_type === 'database')
                                        <div class="form-group">
                                            <label>Table Name</label>
                                            <input type="text" wire:model="db_table" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Database Connection</label>
                                            <select wire:model="db_connection" class="form-control">
                                                <option value="mysql">MySQL</option>
                                                <option value="pgsql">PostgreSQL</option>
                                                <option value="sqlite">SQLite</option>
                                            </select>
                                        </div>
                                    @endif

                                    @if($source_type === 'google_sheets')
                                        <div class="form-group">
                                            <label>Spreadsheet ID</label>
                                            <input type="text" wire:model="sheets_spreadsheet_id" class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label>Range</label>
                                            <input type="text" wire:model="sheets_range" class="form-control" 
                                                   placeholder="A:Z or Sheet1!A1:Z100">
                                        </div>
                                    @endif

                                    <div class="row">
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Score Threshold</label>
                                                <input type="number" wire:model="min_score_threshold" 
                                                       class="form-control" min="0" max="1" step="0.01">
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="form-group">
                                                <label>Cache TTL (seconds)</label>
                                                <input type="number" wire:model="cache_ttl" class="form-control" min="0">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-check">
                                        <input type="checkbox" wire:model="is_active" class="form-check-input" id="is_active">
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Advanced Configuration -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="card card-outline card-info">
                                        <div class="card-header">
                                            <h3 class="card-title">Advanced Configuration</h3>
                                            <div class="card-tools">
                                                <button type="button" class="btn btn-tool" data-card-widget="collapse">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <!-- Aliases -->
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Aliases (Alternative names)</label>
                                                        <div class="input-group">
                                                            <input type="text" wire:model="aliasInput" 
                                                                   wire:keydown.enter.prevent="addAlias" 
                                                                   class="form-control" placeholder="Add alias">
                                                            <div class="input-group-append">
                                                                <button type="button" wire:click="addAlias" class="btn btn-outline-secondary">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        @if(count($aliases) > 0)
                                                            <div class="mt-2">
                                                                @foreach($aliases as $index => $alias)
                                                                    <span class="badge badge-secondary mr-1">
                                                                        {{ $alias }}
                                                                        <button type="button" wire:click="removeAlias({{ $index }})" 
                                                                                class="btn-close-small">&times;</button>
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <!-- Keywords -->
                                                    <div class="form-group">
                                                        <label>Keywords (For intent detection)</label>
                                                        <div class="input-group">
                                                            <input type="text" wire:model="keywordInput" 
                                                                   wire:keydown.enter.prevent="addKeyword" 
                                                                   class="form-control" placeholder="Add keyword">
                                                            <div class="input-group-append">
                                                                <button type="button" wire:click="addKeyword" class="btn btn-outline-secondary">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        @if(count($keywords) > 0)
                                                            <div class="mt-2">
                                                                @foreach($keywords as $index => $keyword)
                                                                    <span class="badge badge-info mr-1">
                                                                        {{ $keyword }}
                                                                        <button type="button" wire:click="removeKeyword({{ $index }})" 
                                                                                class="btn-close-small">&times;</button>
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Parameters -->
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Required Parameters</label>
                                                        <div class="input-group">
                                                            <input type="text" wire:model="requiredParamInput" 
                                                                   wire:keydown.enter.prevent="addRequiredParam" 
                                                                   class="form-control" placeholder="Add required parameter">
                                                            <div class="input-group-append">
                                                                <button type="button" wire:click="addRequiredParam" class="btn btn-outline-secondary">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        @if(count($required_params) > 0)
                                                            <div class="mt-2">
                                                                @foreach($required_params as $index => $param)
                                                                    <span class="badge badge-danger mr-1">
                                                                        {{ $param }}
                                                                        <button type="button" wire:click="removeRequiredParam({{ $index }})" 
                                                                                class="btn-close-small">&times;</button>
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="form-group">
                                                        <label>Optional Parameters</label>
                                                        <div class="input-group">
                                                            <input type="text" wire:model="optionalParamInput" 
                                                                   wire:keydown.enter.prevent="addOptionalParam" 
                                                                   class="form-control" placeholder="Add optional parameter">
                                                            <div class="input-group-append">
                                                                <button type="button" wire:click="addOptionalParam" class="btn btn-outline-secondary">
                                                                    <i class="fas fa-plus"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        @if(count($optional_params) > 0)
                                                            <div class="mt-2">
                                                                @foreach($optional_params as $index => $param)
                                                                    <span class="badge badge-success mr-1">
                                                                        {{ $param }}
                                                                        <button type="button" wire:click="removeOptionalParam({{ $index }})" 
                                                                                class="btn-close-small">&times;</button>
                                                                    </span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Response Template -->
                                            <div class="form-group">
                                                <label>Response Template (Optional)</label>
                                                <textarea wire:model="response_template" class="form-control" rows="3" 
                                                          placeholder="Custom response template using {field} placeholders"></textarea>
                                                <small class="form-text text-muted">
                                                    Use placeholders like {name}, {price}, {description} to format the response
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" wire:click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                {{ $editingAction ? 'Update Action' : 'Create Action' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
    <style>
.btn-close-small {
    background: none;
    border: none;
    color: #fff;
    font-weight: bold;
    padding: 0 5px;
    margin-left: 5px;
}
.btn-close-small:hover {
    color: #ccc;
}
</style>
</div>

