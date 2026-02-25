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

                            <!-- Demo FAQs Section -->
                            <div class="mb-4">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0 fw-semibold">
                                        <i class="fas fa-question-circle me-1 text-primary"></i> Demo FAQs *
                                    </label>
                                    @if(!$showFaqForm)
                                        <button type="button" class="btn btn-primary btn-sm" wire:click="$set('showFaqForm', true)">
                                            <i class="fas fa-plus me-1"></i> Add FAQ
                                        </button>
                                    @endif
                                </div>

                                @error('sample_questions') <div class="alert alert-danger py-1 px-2 mb-2 small">{{ $message }}</div> @enderror

                                {{-- Inline FAQ Form (add new or edit existing) --}}
                                @if($showFaqForm)
                                    <div class="border rounded p-3 mb-3 bg-light">
                                        <h6 class="mb-3 text-primary">
                                            <i class="fas fa-{{ $editingQuestionIndex !== null ? 'edit' : 'plus-circle' }} me-1"></i>
                                            {{ $editingQuestionIndex !== null ? 'Edit FAQ' : 'Add New FAQ' }}
                                        </h6>

                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">Question <span class="text-danger">*</span></label>
                                            @if($editingQuestionIndex !== null)
                                                <input type="text" class="form-control" wire:model="editingQuestionValue" placeholder="Enter the question">
                                            @else
                                                <input type="text" class="form-control" wire:model="questionInput" placeholder="Enter the question">
                                            @endif
                                        </div>

                                        <div class="mb-2">
                                            <label class="form-label form-label-sm mb-1">Answer <span class="text-danger">*</span></label>
                                            @if($editingQuestionIndex !== null)
                                                <textarea class="form-control" wire:model="editingQuestionAnswer" rows="4" placeholder="Enter the answer that the AI will use for this question"></textarea>
                                            @else
                                                <textarea class="form-control" wire:model="questionAnswerInput" rows="4" placeholder="Enter the answer that the AI will use for this question"></textarea>
                                            @endif
                                            <small class="text-muted">This answer will be stored in the vector database and used as context for the AI response.</small>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label form-label-sm mb-1">Keywords <span class="text-muted">(optional)</span></label>
                                            @if($editingQuestionIndex !== null)
                                                <input type="text" class="form-control" wire:model="editingQuestionKeywords" placeholder="e.g., maternity, pediatric, care (comma separated)">
                                            @else
                                                <input type="text" class="form-control" wire:model="questionKeywordsInput" placeholder="e.g., appointment, schedule, booking (comma separated)">
                                            @endif
                                            <small class="text-muted">Keywords improve search matching. Use comma-separated values.</small>
                                        </div>

                                        <div class="d-flex gap-2">
                                            @if($editingQuestionIndex !== null)
                                                <button type="button" class="btn btn-success btn-sm" wire:click="saveQuestionEdit">
                                                    <i class="fas fa-save me-1"></i> Update FAQ
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-success btn-sm" wire:click="addQuestion">
                                                    <i class="fas fa-save me-1"></i> Save FAQ
                                                </button>
                                            @endif
                                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelQuestionEdit">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </button>
                                        </div>
                                    </div>
                                @endif

                                {{-- FAQ List --}}
                                @if(!empty($sample_questions))
                                    <div class="border rounded overflow-hidden">
                                        <table class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="width:35%">Question</th>
                                                    <th style="width:45%">Answer Preview</th>
                                                    <th style="width:12%">Keywords</th>
                                                    <th style="width:8%" class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($sample_questions as $index => $questionEntry)
                                                    <tr class="{{ ($editingQuestionIndex === $index) ? 'table-warning' : '' }}">
                                                        <td class="align-top">
                                                            <span class="fw-medium small">{{ $questionEntry['question'] ?? '' }}</span>
                                                        </td>
                                                        <td class="align-top">
                                                            @if(!empty($questionEntry['answer']))
                                                                <span class="text-muted small">{{ \Illuminate\Support\Str::limit($questionEntry['answer'], 120) }}</span>
                                                            @else
                                                                <span class="badge bg-warning text-dark"><i class="fas fa-exclamation-triangle me-1"></i>No answer yet</span>
                                                            @endif
                                                        </td>
                                                        <td class="align-top">
                                                            @if(!empty($questionEntry['keywords']))
                                                                <small class="text-muted">{{ \Illuminate\Support\Str::limit($questionEntry['keywords'], 30) }}</small>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                        <td class="align-top text-end">
                                                            <div class="btn-group btn-group-sm">
                                                                <button type="button" class="btn btn-outline-primary" wire:click="startQuestionEdit({{ $index }})" title="Edit FAQ">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger" wire:click="removeQuestion({{ $index }})" title="Remove FAQ"
                                                                        onclick="if(!confirm('Remove this FAQ?')) { event.preventDefault(); event.stopImmediatePropagation(); }">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-1">{{ count($sample_questions) }} FAQ(s). After saving, click "Sync to Qdrant" to update the demo AI search database.</small>
                                @else
                                    @if(!$showFaqForm)
                                        <div class="border rounded p-4 text-center text-muted bg-light">
                                            <i class="fas fa-question-circle fa-2x mb-2 d-block"></i>
                                            No demo FAQs added yet. Click <strong>Add FAQ</strong> to get started.
                                        </div>
                                    @endif
                                @endif
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
