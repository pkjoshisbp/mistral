<div class="content-wrapper">
    <!-- Content Header -->
    <section class="content-header">
        <div class="container-fluid">
                        <div class="col-md-4">
                <div class="col-sm-6">
                    <h1>Live Data Actions</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Home</a></li>
                        <li class="breadcrumb-item active">Live Data Actions</li>
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

            <!-- Info Box -->
            <div class="alert alert-info">
                <h5><i class="icon fas fa-info"></i> Live Data Actions</h5>
                Connect your AI chat bot with live data sources like APIs, CSV files, Excel sheets, databases, or Google Sheets. 
                When customers ask related questions, the bot will fetch real-time data and provide up-to-date answers.
            </div>

            <!-- Action Templates -->
            <div class="card mb-3">
                <div class="card-header">
                    <h3 class="card-title">Action Templates</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <select wire:model="templateType" class="form-control">
                                <option value="">Select Template Type</option>
                                <option value="clinic">Clinic</option>
                                <option value="hospital">Hospital</option>
                                <option value="ecommerce">E-commerce</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="real_estate">Real Estate</option>
                                <option value="real_estate_rental">Real Estate (Rentals)</option>
                                <option value="automobile_dealer">Automobile Dealer</option>
                                <option value="school">School</option>
                                <option value="college">College</option>
                                <option value="ngo">NGO</option>
                                <option value="travel">Travel</option>
                                <option value="fitness">Fitness/Gym</option>
                                <option value="logistics">Logistics</option>
                                <option value="fintech">Fintech</option>
                                <option value="other">Other</option>
                            </select>
                            <small class="text-muted">Templates are created as inactive. Configure and activate when ready.</small>
                        </div>
                        <div class="col-md-4">
                            <select wire:model="templatePlatform" class="form-control">
                                <option value="">Platform (all / generic)</option>
                                <option value="woocommerce">WooCommerce</option>
                                <option value="magento">Magento</option>
                                <option value="shopify">Shopify</option>
                                <option value="laravel">Laravel</option>
                            </select>
                            <small class="text-muted">Platform templates include API endpoints; add headers/keys in each action.</small>
                        </div>
                        <div class="col-md-4">
                            <button wire:click="applyActionTemplate" class="btn btn-outline-primary w-100">
                                <i class="fas fa-magic"></i> Apply Action Templates
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Live Data Actions</h3>
                    <div class="card-tools">
                        <button wire:click="openModal" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add New Action
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Search -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" wire:model.live.debounce.300ms="search" 
                                   class="form-control" placeholder="Search actions...">
                        </div>
                    </div>

                    <!-- Actions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Keywords</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($actions as $action)
                                    <tr>
                                        <td>
                                            <strong>{{ $action->name }}</strong>
                                            <br>
                                            <small class="text-muted">{{ Str::limit($action->description, 50) }}</small>
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ ucfirst($action->action_type) }}</span>
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
                                        <td>
                                            @if($action->keywords && count($action->keywords) > 0)
                                                @foreach(array_slice($action->keywords, 0, 3) as $keyword)
                                                    <span class="badge badge-secondary">{{ $keyword }}</span>
                                                @endforeach
                                                @if(count($action->keywords) > 3)
                                                    <span class="badge badge-light">+{{ count($action->keywords) - 3 }} more</span>
                                                @endif
                                            @else
                                                <span class="text-muted">No keywords</span>
                                            @endif
                                        </td>
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
                                        <td colspan="6" class="text-center">
                                            <div class="py-4">
                                                <i class="fas fa-cogs fa-3x text-muted mb-3"></i>
                                                <h5>No Live Data Actions Yet</h5>
                                                <p class="text-muted">Create your first action to connect live data sources with your AI chat bot.</p>
                                                <button wire:click="openModal" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Your First Action
                                                </button>
                                            </div>
                                        </td>
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
            <div class="modal-dialog modal-lg" role="document">
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
                                        <label>Name *</label>
                                        <input type="text" wire:model="name" class="form-control @error('name') is-invalid @enderror" 
                                               placeholder="e.g. Pricing Information">
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
                                        <textarea wire:model="description" class="form-control @error('description') is-invalid @enderror" 
                                                  rows="3" placeholder="Describe what this action does and when it should be triggered"></textarea>
                                        @error('description')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                    </div>
                                </div>

                                <!-- Source Configuration -->
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Data Source *</label>
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
                                            <input type="url" wire:model="api_url" class="form-control" 
                                                   placeholder="https://api.example.com/pricing">
                                            <small class="form-text text-muted">The API endpoint to fetch data from</small>
                                        </div>
                                    @endif

                                    @if($source_type === 'csv')
                                        <div class="form-group">
                                            <label>CSV File Path</label>
                                            <input type="text" wire:model="csv_file_path" class="form-control" 
                                                   placeholder="/path/to/file.csv or storage/app/data.csv">
                                            <small class="form-text text-muted">Path to your CSV file on the server</small>
                                        </div>
                                    @endif

                                    @if($source_type === 'excel')
                                        <div class="form-group">
                                            <label>Excel File Path</label>
                                            <input type="text" wire:model="excel_file_path" class="form-control" 
                                                   placeholder="/path/to/file.xlsx">
                                            <small class="form-text text-muted">Path to your Excel file on the server</small>
                                        </div>
                                    @endif

                                    @if($source_type === 'database')
                                        <div class="form-group">
                                            <label>Table Name</label>
                                            <input type="text" wire:model="db_table" class="form-control" 
                                                   placeholder="pricing_plans">
                                            <small class="form-text text-muted">Name of the database table to query</small>
                                        </div>
                                    @endif

                                    @if($source_type === 'google_sheets')
                                        <div class="form-group">
                                            <label>Spreadsheet ID</label>
                                            <input type="text" wire:model="sheets_spreadsheet_id" class="form-control" 
                                                   placeholder="1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms">
                                            <small class="form-text text-muted">Google Sheets spreadsheet ID (from the URL)</small>
                                        </div>
                                        <div class="form-group">
                                            <label>Range</label>
                                            <input type="text" wire:model="sheets_range" class="form-control" 
                                                   placeholder="A:Z">
                                            <small class="form-text text-muted">Range of cells to read (e.g., A:Z, Sheet1!A1:C100)</small>
                                        </div>
                                    @endif

                                    <div class="form-check">
                                        <input type="checkbox" wire:model="is_active" class="form-check-input" id="is_active">
                                        <label class="form-check-label" for="is_active">Active</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Keywords Section -->
                            <div class="row mt-3">
                                <div class="col-12">
                                    <div class="card card-outline card-info">
                                        <div class="card-header">
                                            <h3 class="card-title">Keywords (When should this action trigger?)</h3>
                                        </div>
                                        <div class="card-body">
                                            <div class="form-group">
                                                <label>Keywords</label>
                                                <div class="input-group">
                                                    <input type="text" wire:model="keywordInput" 
                                                           wire:keydown.enter.prevent="addKeyword" 
                                                           class="form-control" 
                                                           placeholder="Add keywords that trigger this action (e.g., price, cost, pricing)">
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
                                                <small class="form-text text-muted">
                                                    When customers mention these keywords, the AI will check if this action should be triggered.
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
