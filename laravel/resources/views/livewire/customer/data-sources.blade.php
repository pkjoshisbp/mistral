<div>
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">Data Sources</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item active">Data Sources</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            @if (session()->has('message'))
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('message') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    {{ session('error') }}
                </div>
            @endif

            <!-- Data Sources List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Your Data Sources</h3>
                    <div class="card-tools">
                        <button type="button" class="btn btn-primary" wire:click="toggleCreateForm">
                            <i class="fas fa-plus"></i> Add Data Source
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    @if($dataSources && $dataSources->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Last Sync</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($dataSources as $source)
                                        <tr>
                                            <td>
                                                <strong>{{ $source->name }}</strong>
                                                @if($source->description)
                                                    <br><small class="text-muted">{{ $source->description }}</small>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $this->getTypeBadgeColor($source->type) }}">
                                                    {{ ucfirst(str_replace('_', ' ', $source->type)) }}
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-{{ $this->getStatusBadgeColor($source->status) }}">
                                                    {{ ucfirst($source->status) }}
                                                </span>
                                            </td>
                                            <td>
                                                {{ $source->last_sync_at ? $source->last_sync_at->format('M d, Y H:i') : 'Never' }}
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-info" wire:click="editDataSource({{ $source->id }})">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-success" wire:click="syncDataSource({{ $source->id }})" 
                                                            {{ $source->status === 'syncing' ? 'disabled' : '' }}>
                                                        <i class="fas fa-sync"></i>
                                                    </button>
                                                    <button class="btn btn-danger" 
                                                            onclick="confirm('Are you sure?') || event.stopImmediatePropagation()" 
                                                            wire:click="deleteDataSource({{ $source->id }})">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-database fa-3x text-muted mb-3"></i>
                            <h5>No Data Sources</h5>
                            <p class="text-muted">Start by adding your first data source to sync content for your AI agent.</p>
                            <button type="button" class="btn btn-primary" wire:click="toggleCreateForm">
                                <i class="fas fa-plus"></i> Add Your First Data Source
                            </button>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Create/Edit Form -->
            @if($showCreateForm)
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            {{ $editingId ? 'Edit' : 'Create' }} Data Source
                        </h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-tool" wire:click="resetForm">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form wire:submit.prevent="{{ $editingId ? 'updateDataSource' : 'createDataSource' }}">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Data Source Type</label>
                                        <select class="form-control" wire:model="type">
                                            <option value="">Select Type</option>
                                            <option value="crawler">Website Crawler</option>
                                            <option value="file_upload">File Upload</option>
                                            <option value="google_sheets">Google Sheets</option>
                                            <option value="database">Database Connection</option>
                                            <option value="api_push">API Push</option>
                                        </select>
                                        @error('type') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Name</label>
                                        <input type="text" class="form-control" wire:model="name" placeholder="Enter data source name">
                                        @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Description</label>
                                <textarea class="form-control" wire:model="description" rows="3" placeholder="Optional description"></textarea>
                                @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                            </div>

                            <!-- Dynamic Configuration Based on Type -->
                            @if($type)
                                <div class="card">
                                    <div class="card-header">
                                        <h4 class="card-title">Configuration</h4>
                                    </div>
                                    <div class="card-body">
                                        @include('livewire.customer.data-source-configs.' . str_replace('_', '-', $type))
                                    </div>
                                </div>
                            @endif

                            <div class="form-group mt-3">
                                <button type="submit" class="btn btn-primary">
                                    {{ $editingId ? 'Update' : 'Create' }} Data Source
                                </button>
                                <button type="button" class="btn btn-secondary ml-2" wire:click="resetForm">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('livewire:load', function () {
            Livewire.hook('message.processed', (message, component) => {
                // Reinitialize any JavaScript components if needed
            });
        });
    </script>
</div>

@push('scripts')
<script>
    // Helper functions for the component
    function getTypeBadgeColor(type) {
        const colors = {
            'crawler': 'info',
            'file_upload': 'success',
            'google_sheets': 'warning',
            'database': 'primary',
            'api_push': 'secondary'
        };
        return colors[type] || 'secondary';
    }

    function getStatusBadgeColor(status) {
        const colors = {
            'active': 'success',
            'inactive': 'secondary',
            'syncing': 'warning',
            'error': 'danger'
        };
        return colors[status] || 'secondary';
    }
</script>
@endpush
