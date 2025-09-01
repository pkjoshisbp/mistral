<div>
    @if (session()->has('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-building mr-2"></i>
                {{ $organization ? 'Edit Organization' : 'Create Organization' }}
            </h3>
        </div>
        <div class="card-body">
            <form wire:submit.prevent="save">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" wire:model.live="name" placeholder="Organization Name">
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Slug *</label>
                            <input type="text" class="form-control @error('slug') is-invalid @enderror" wire:model="slug" {{ $organization ? 'readonly' : '' }}>
                            @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <small class="text-muted">Used for API endpoints</small>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Website</label>
                            <input type="url" class="form-control @error('website') is-invalid @enderror" wire:model="website" placeholder="https://example.com">
                            @error('website') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label class="form-label">Timezone *</label>
                            <select class="form-control @error('timezone') is-invalid @enderror" wire:model="timezone">
                                <option value="UTC">UTC</option>
                                <option value="America/New_York">America/New_York</option>
                                <option value="Europe/London">Europe/London</option>
                                <option value="Asia/Kolkata">Asia/Kolkata</option>
                                <option value="Asia/Singapore">Asia/Singapore</option>
                            </select>
                            @error('timezone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Description</label>
                    <textarea class="form-control @error('description') is-invalid @enderror" rows="3" wire:model="description" placeholder="Short description"></textarea>
                    @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        @if($organization)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-secondary">Not Created</span>
                        @endif
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> {{ $organization ? 'Update' : 'Create' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if(!$organization)
        <div class="alert alert-info mt-3">
            <i class="fas fa-info-circle"></i> You can create one organization which will be linked to your account.
        </div>
    @endif
</div>
