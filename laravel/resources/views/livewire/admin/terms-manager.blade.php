<div>
    <div class="row">
        <!-- Type Selection Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Document Types</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @foreach($types as $typeKey => $typeName)
                            <button wire:click="selectType('{{ $typeKey }}')" 
                                    class="list-group-item list-group-item-action {{ $selectedType === $typeKey ? 'active' : '' }}">
                                <i class="fas fa-file-alt"></i>
                                {{ $typeName }}
                                @if($terms->where('type', $typeKey)->count() > 0)
                                    <span class="badge badge-success float-right">✓</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Existing Terms List -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Documents</h5>
                </div>
                <div class="card-body">
                    @if($terms->count() > 0)
                        @foreach($terms as $term)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <strong>{{ $types[$term->type] ?? $term->type }}</strong><br>
                                    <small class="text-muted">{{ $term->title }}</small>
                                </div>
                                <div>
                                    <button wire:click="selectType('{{ $term->type }}')" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button wire:click="delete({{ $term->id }})" 
                                            class="btn btn-sm btn-outline-danger"
                                            onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">No documents created yet.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Main Content Editor -->
        <div class="col-md-9">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        {{ $isEditing ? 'Edit' : 'Create' }} {{ $types[$selectedType] ?? 'Document' }}
                    </h4>
                </div>
                <div class="card-body">
                    <form wire:submit.prevent="save">
                        <div class="form-group mb-3">
                            <label for="title" class="form-label">
                                <i class="fas fa-heading"></i>
                                Document Title *
                            </label>
                            <input type="text" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   id="title"
                                   wire:model="title" 
                                   placeholder="Enter document title">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="form-group mb-3">
                            <label for="content" class="form-label">
                                <i class="fas fa-file-text"></i>
                                Content *
                            </label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      id="content"
                                      wire:model="content" 
                                      rows="20"
                                      placeholder="Enter document content..."></textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                You can use HTML tags for formatting. This content will be displayed on the public pages.
                            </small>
                        </div>

                        <div class="d-flex justify-content-between">
                            <div>
                                @if($isEditing)
                                    <button type="button" wire:click="resetForm" class="btn btn-secondary">
                                        <i class="fas fa-times"></i>
                                        Cancel
                                    </button>
                                @endif
                            </div>
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    {{ $isEditing ? 'Update' : 'Create' }} {{ $types[$selectedType] ?? 'Document' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Preview Card -->
            @if($content)
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-eye"></i>
                            Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        <h4>{{ $title }}</h4>
                        <hr>
                        <div>{!! nl2br(e($content)) !!}</div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
