<div class="card">
    <div class="card-header">
        <h3 class="card-title">Organization Management</h3>
        <div class="card-tools">
            <button wire:click="toggleCreateForm" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Add Organization
            </button>
        </div>
    </div>

    <div class="card-body">
        @if (session()->has('message'))
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                {{ session('message') }}
            </div>
        @endif

        @if ($showCreateForm)
            <div class="card card-secondary">
                <div class="card-header">
                    <h3 class="card-title">Create New Organization</h3>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="createOrganization">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Name</label>
                                    <input type="text" wire:model="name" class="form-control" id="name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="slug">Slug</label>
                                    <input type="text" wire:model="slug" class="form-control" id="slug">
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea wire:model="description" class="form-control" id="description" rows="3"></textarea>
                        </div>

                        <div class="card card-outline card-info">
                            <div class="card-header">
                                <h3 class="card-title">Database Configuration (Optional)</h3>
                                <small class="text-muted">Configure client's MySQL database for data synchronization</small>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="database_name">Database Name</label>
                                            <input type="text" wire:model="database_name" class="form-control" id="database_name" placeholder="e.g., gupta_diagnostics_db">
                                            <small class="text-muted">Name of the client's MySQL database</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="database_host">Database Host</label>
                                            <input type="text" wire:model="database_host" class="form-control" id="database_host" placeholder="localhost">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="database_username">Username</label>
                                            <input type="text" wire:model="database_username" class="form-control" id="database_username">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="database_password">Password</label>
                                            <input type="password" wire:model="database_password" class="form-control" id="database_password">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="database_port">Port</label>
                                            <input type="text" wire:model="database_port" class="form-control" id="database_port" placeholder="3306">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn btn-success">Create</button>
                            <button type="button" wire:click="toggleCreateForm" class="btn btn-secondary">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Database</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $org)
                        <tr>
                            <td>{{ $org->id }}</td>
                            <td>{{ $org->name }}</td>
                            <td>{{ $org->slug }}</td>
                            <td>{{ $org->database_name ?? 'N/A' }}</td>
                            <td>
                                <span class="badge badge-{{ $org->is_active ? 'success' : 'danger' }}">
                                    {{ $org->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>{{ $org->created_at->format('Y-m-d H:i') }}</td>
                            <td>
                                <button wire:click="selectOrganization({{ $org->id }})" class="btn btn-info btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No organizations found</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
