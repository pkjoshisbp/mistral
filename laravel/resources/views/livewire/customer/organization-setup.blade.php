<div>
    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tab === 'create' ? 'active' : '' }}" 
                    wire:click="$set('tab', 'create')" 
                    type="button">
                <i class="fas fa-plus-circle"></i>
                Create Organization
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ $tab === 'join' ? 'active' : '' }}" 
                    wire:click="$set('tab', 'join')" 
                    type="button">
                <i class="fas fa-users"></i>
                Join Organization
            </button>
        </li>
    </ul>

    <!-- Create Organization Tab -->
    @if($tab === 'create')
        <div class="tab-pane fade show active">
            <form wire:submit.prevent="createOrganization">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="name" class="form-label">
                                <i class="fas fa-building"></i>
                                Organization Name *
                            </label>
                            <input type="text" 
                                   class="form-control @error('name') is-invalid @enderror" 
                                   id="name"
                                   wire:model.live="name" 
                                   placeholder="Enter organization name">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label for="slug" class="form-label">
                                <i class="fas fa-link"></i>
                                Organization Slug *
                            </label>
                            <input type="text" 
                                   class="form-control @error('slug') is-invalid @enderror" 
                                   id="slug"
                                   wire:model="slug" 
                                   placeholder="organization-slug">
                            @error('slug')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">Used for API endpoints and collections</small>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label for="description" class="form-label">
                        <i class="fas fa-info-circle"></i>
                        Description
                    </label>
                    <textarea class="form-control @error('description') is-invalid @enderror" 
                              id="description"
                              wire:model="description" 
                              rows="3"
                              placeholder="Brief description of your organization"></textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Organization
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Join Organization Tab -->
    @if($tab === 'join')
        <div class="tab-pane fade show active">
            @if(count($existingOrganizations) > 0)
                <div class="mb-3">
                    <p class="text-muted">
                        <i class="fas fa-info-circle"></i>
                        Choose an existing organization to request access:
                    </p>
                </div>

                <div class="row">
                    @foreach($existingOrganizations as $org)
                        <div class="col-md-6 mb-3">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">{{ $org->name }}</h5>
                                    <p class="card-text text-muted">
                                        {{ $org->description ?: 'No description available' }}
                                    </p>
                                    <p class="small text-muted">
                                        <i class="fas fa-link"></i>
                                        Slug: {{ $org->slug }}
                                    </p>
                                </div>
                                <div class="card-footer">
                                    <button wire:click="requestAccess({{ $org->id }})" 
                                            class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-user-plus"></i>
                                        Request Access
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-4">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <h5>No Organizations Available</h5>
                    <p class="text-muted">There are no existing organizations to join. You can create a new one instead.</p>
                    <button wire:click="$set('tab', 'create')" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Create Organization
                    </button>
                </div>
            @endif
        </div>
    @endif
</div>
