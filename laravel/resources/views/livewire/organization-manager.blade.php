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
                    <div class="card-tools">
                        <button wire:click="toggleCreateForm" class="btn btn-tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="createOrganization">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Organization Name *</label>
                                    <input type="text" wire:model="name" class="form-control" id="name" placeholder="e.g., Gupta Diagnostics">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="slug">Slug *</label>
                                    <input type="text" wire:model="slug" class="form-control" id="slug" placeholder="e.g., gupta-diagnostics">
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                    <small class="text-muted">Used for API endpoints and collections</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea wire:model="description" class="form-control" id="description" rows="3" placeholder="Brief description of the organization"></textarea>
                            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="website_url">Website URL</label>
                            <input type="url" wire:model="website_url" class="form-control" id="website_url" placeholder="https://example.com">
                            @error('website_url') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Organization
                            </button>
                            <button type="button" wire:click="toggleCreateForm" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @if ($showEditForm)
            <div class="card card-warning">
                <div class="card-header">
                    <h3 class="card-title">Edit Organization</h3>
                    <div class="card-tools">
                        <button wire:click="cancelEdit" class="btn btn-tool">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="updateOrganization">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_name">Organization Name *</label>
                                    <input type="text" wire:model="name" class="form-control" id="edit_name">
                                    @error('name') <span class="text-danger">{{ $message }}</span> @enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="edit_slug">Slug *</label>
                                    <input type="text" wire:model="slug" class="form-control" id="edit_slug">
                                    @error('slug') <span class="text-danger">{{ $message }}</span> @enderror
                                    <small class="text-muted">Changing this will update Qdrant collections</small>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="edit_description">Description</label>
                            <textarea wire:model="description" class="form-control" id="edit_description" rows="3"></textarea>
                            @error('description') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="form-group">
                            <label for="edit_website_url">Website URL</label>
                            <input type="url" wire:model="website_url" class="form-control" id="edit_website_url">
                            @error('website_url') <span class="text-danger">{{ $message }}</span> @enderror
                        </div>

                        <div class="card-footer">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update Organization
                            </button>
                            <button type="button" wire:click="cancelEdit" class="btn btn-secondary ml-2">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Organizations List -->
        @if(count($organizations) > 0)
            <div class="row">
                @foreach($organizations as $org)
                    <div class="col-md-6 mb-3">
                        <div class="card card-outline card-primary">
                            <div class="card-header">
                                <h5 class="card-title">{{ $org->name }}</h5>
                                <div class="card-tools">
                                    <button wire:click="editOrganization({{ $org->id }})" class="btn btn-tool text-warning">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">{{ $org->description ?: 'No description provided' }}</p>
                                
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Slug:</strong> <code>{{ $org->slug }}</code>
                                    </div>
                                    <div class="col-6">
                                        <strong>Users:</strong> {{ $org->users->count() }}
                                    </div>
                                </div>
                                
                                @if($org->website_url)
                                    <div class="mt-2">
                                        <strong>Website:</strong> 
                                        <a href="{{ $org->website_url }}" target="_blank" class="text-primary">
                                            {{ $org->website_url }}
                                        </a>
                                    </div>
                                @endif
                                
                                <div class="mt-2">
                                    <strong>Collection:</strong> <code>{{ $org->collection_name }}</code>
                                </div>
                                
                                <div class="mt-2">
                                    <strong>Status:</strong> 
                                    @if($org->is_active)
                                        <span class="badge badge-success">Active</span>
                                    @else
                                        <span class="badge badge-danger">Inactive</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-4">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No organizations created yet.</h5>
                <p class="text-muted">Click "Add Organization" to get started.</p>
            </div>
        @endif
    </div>
</div>
