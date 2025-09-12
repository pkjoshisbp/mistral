<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">🎯 Industry Demo Management</h4>
                    <div>
                        <button type="button" class="btn btn-success me-2" wire:click="syncToQdrant()">
                            <i class="fas fa-sync"></i> Sync to Qdrant
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="openModal()">
                            <i class="fas fa-plus"></i> Add Demo
                        </button>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Success Alert -->
                    @if (session()->has('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <!-- Search -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="Search demos..." wire:model.live="search">
                        </div>
                        <div class="col-md-6">
                            @if($search)
                                <button class="btn btn-outline-secondary" wire:click="$set('search', '')">
                                    Clear Search
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Demos Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Industry</th>
                                    <th>Organization Name</th>
                                    <th>Features</th>
                                    <th>Questions</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($demos as $demo)
                                    <tr>
                                        <td>
                                            <span class="badge badge-info">{{ ucfirst($demo->industry) }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $demo->name }}</strong>
                                            <br><small class="text-muted">{{ Str::limit($demo->description, 50) }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ count($demo->features) }} features</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">{{ count($demo->sample_questions) }} questions</small>
                                        </td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       {{ $demo->is_active ? 'checked' : '' }}
                                                       wire:click="toggleStatus({{ $demo->id }})">
                                                <label class="form-check-label">
                                                    {{ $demo->is_active ? 'Active' : 'Inactive' }}
                                                </label>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('demo', ['industry' => $demo->industry]) }}" 
                                                   target="_blank" class="btn btn-sm btn-outline-success" title="View Demo">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        wire:click="openModal({{ $demo->id }})">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        wire:click="delete({{ $demo->id }})"
                                                        onclick="return confirm('Are you sure you want to delete this demo?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <div class="text-muted">
                                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                                <p>No demo organizations found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $demos->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="modal fade show demo-modal" style="display: block; background-color: rgba(0,0,0,0.5);" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingDemo ? 'Edit Demo Organization' : 'Create Demo Organization' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal()"></button>
                    </div>
                    
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Industry *</label>
                                        <select class="form-control @error('industry') is-invalid @enderror" wire:model="industry">
                                            <option value="">Select Industry</option>
                                            <option value="healthcare">Healthcare</option>
                                            <option value="education">Education</option>
                                            <option value="automotive">Automotive</option>
                                            <option value="ecommerce">E-commerce</option>
                                            <option value="hospitality">Hospitality</option>
                                            <option value="realestate">Real Estate</option>
                                            <option value="legal">Legal</option>
                                            <option value="manufacturing">Manufacturing</option>
                                            <option value="nonprofit">Non-profit</option>
                                        </select>
                                        @error('industry') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Organization Name *</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               wire:model="name" placeholder="e.g., City General Hospital">
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description *</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" 
                                          wire:model="description" rows="3" 
                                          placeholder="Brief description of what this demo showcases"></textarea>
                                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <!-- Features Section -->
                            <div class="mb-4">
                                <label class="form-label">Features *</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" wire:model="featureInput" 
                                           placeholder="Add a feature (e.g., Appointment Scheduling)">
                                    <button type="button" class="btn btn-outline-primary" wire:click="addFeature">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                @if(!empty($features))
                                    <div class="border rounded p-2">
                                        @foreach($features as $index => $feature)
                                            <span class="badge bg-primary me-1 mb-1">
                                                {{ $feature }}
                                                <button type="button" class="btn-close btn-close-white ms-1" 
                                                        wire:click="removeFeature({{ $index }})" style="font-size: 0.7em;"></button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                @error('features') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>

                            <!-- Sample Questions Section -->
                            <div class="mb-4">
                                <label class="form-label">Sample Questions *</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" wire:model="questionInput" 
                                           placeholder="Add a sample question users can try">
                                    <button type="button" class="btn btn-outline-primary" wire:click="addQuestion">
                                        <i class="fas fa-plus"></i> Add
                                    </button>
                                </div>
                                @if(!empty($sample_questions))
                                    <div class="border rounded p-2 max-height-200 overflow-auto">
                                        @foreach($sample_questions as $index => $question)
                                            <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                                                <small>{{ $question }}</small>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        wire:click="removeQuestion({{ $index }})">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @error('sample_questions') <div class="text-danger">{{ $message }}</div> @enderror
                            </div>

                            <!-- AI Responses Section -->
                            <div class="mb-4">
                                <label class="form-label">Custom AI Responses (Optional)</label>
                                <div class="row mb-2">
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" wire:model="responseQuestion" 
                                               placeholder="Question keyword">
                                    </div>
                                    <div class="col-md-5">
                                        <input type="text" class="form-control" wire:model="responseAnswer" 
                                               placeholder="AI response">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-primary w-100" wire:click="addResponse">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                                @if(!empty($ai_responses))
                                    <div class="border rounded p-2 max-height-200 overflow-auto">
                                        @foreach($ai_responses as $question => $response)
                                            <div class="border-bottom py-2">
                                                <div class="d-flex justify-content-between">
                                                    <strong class="text-primary">{{ $question }}</strong>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            wire:click="removeResponse('{{ $question }}')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">{{ $response }}</small>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                                <label class="form-check-label" for="is_active">
                                    Active Demo
                                </label>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="save()" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ $editingDemo ? 'Update Demo' : 'Create Demo' }}</span>
                            <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin"></i> Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
    .demo-modal .modal-xl {
        max-width: 90%;
    }

    .demo-modal .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }

    .demo-modal .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem;
        background: #fff;
        position: sticky;
        bottom: 0;
        z-index: 1;
    }

    .max-height-200 {
        max-height: 200px;
    }

    @media (max-width: 768px) {
        .demo-modal .modal-xl {
            max-width: 95%;
        }
    }
    </style>
</div>
