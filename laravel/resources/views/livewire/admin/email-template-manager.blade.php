<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Email Templates</h4>
                    <button type="button" class="btn btn-primary" wire:click="openModal()">
                        <i class="fas fa-plus"></i> Add Template
                    </button>
                </div>
                
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <input type="text" class="form-control" placeholder="Search templates..." wire:model.live="search">
                        </div>
                        <div class="col-md-3">
                            <select class="form-control" wire:model.live="industryFilter">
                                <option value="">All Industries</option>
                                @foreach($industries as $industry)
                                    <option value="{{ $industry }}">{{ ucfirst($industry) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            @if($search || $industryFilter)
                                <button class="btn btn-outline-secondary" wire:click="$set('search', ''); $set('industryFilter', '')">
                                    Clear Filters
                                </button>
                            @endif
                        </div>
                    </div>

                    <!-- Templates Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Industry</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($templates as $template)
                                    <tr>
                                        <td>
                                            <strong>{{ $template->name }}</strong>
                                            @if($template->description)
                                                <br><small class="text-muted">{{ Str::limit($template->description, 50) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge badge-info">{{ ucfirst($template->industry_type) }}</span>
                                        </td>
                                        <td>{{ Str::limit($template->subject, 50) }}</td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                       {{ $template->is_active ? 'checked' : '' }}
                                                       wire:click="toggleStatus({{ $template->id }})">
                                                <label class="form-check-label">
                                                    {{ $template->is_active ? 'Active' : 'Inactive' }}
                                                </label>
                                            </div>
                                        </td>
                                        <td>{{ $template->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        wire:click="previewTemplate({{ $template->id }})"
                                                        title="Preview Template">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        wire:click="openModal({{ $template->id }})">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        wire:click="delete({{ $template->id }})"
                                                        onclick="return confirm('Are you sure you want to delete this template?')">
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
                                                <p>No email templates found</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-center">
                        {{ $templates->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal -->
    @if($showModal)
        <div class="modal fade show email-template-modal" style="display: block; background-color: rgba(0,0,0,0.5);" tabindex="-1" wire:ignore.self>
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            {{ $editingTemplate ? 'Edit Template' : 'Create Template' }}
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal()"></button>
                    </div>
                    
                    <div class="modal-body">
                        <form wire:submit.prevent="save">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Template Name *</label>
                                        <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                               wire:model="name" placeholder="Enter template name">
                                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Industry Type *</label>
                                        <select class="form-control @error('industry_type') is-invalid @enderror" wire:model="industry_type">
                                            <option value="general">General</option>
                                            <option value="healthcare">Healthcare</option>
                                            <option value="technology">Technology</option>
                                            <option value="retail">Retail</option>
                                            <option value="consulting">Consulting</option>
                                            <option value="education">Education</option>
                                            <option value="finance">Finance</option>
                                        </select>
                                        @error('industry_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Subject *</label>
                                <input type="text" class="form-control @error('subject') is-invalid @enderror" 
                                       wire:model="subject" placeholder="Enter email subject (use {variables} for dynamic content)">
                                @error('subject') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" wire:model="description" rows="2" 
                                          placeholder="Brief description of this template"></textarea>
                            </div>

                            <!-- Variables Section -->
                            <div class="mb-3">
                                <label class="form-label">Template Variables</label>
                                <div class="input-group mb-2">
                                    <input type="text" class="form-control" wire:model="variableInput" 
                                           placeholder="Add a variable (e.g., recipient_name, company_name)">
                                    <button type="button" class="btn btn-outline-primary" wire:click="addVariable()">
                                        Add Variable
                                    </button>
                                </div>
                                
                                @if(count($variables) > 0)
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($variables as $index => $variable)
                                            <span class="badge bg-secondary">
                                                {{{ $variable }}}
                                                <button type="button" class="btn-close btn-close-white ms-1" 
                                                        wire:click="removeVariable({{ $index }})"></button>
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Content (HTML) *</label>
                                <textarea class="form-control @error('content') is-invalid @enderror" 
                                          wire:model="content" rows="15" 
                                          placeholder="Enter HTML email content. Use {variable_name} for dynamic content."></textarea>
                                @error('content') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <small class="form-text text-muted">
                                    Use HTML for formatting. Variables like {recipient_name} will be replaced with actual values.
                                </small>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" wire:model="is_active" id="is_active">
                                <label class="form-check-label" for="is_active">
                                    Active Template
                                </label>
                            </div>
                        </form>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-info" wire:click="previewTemplate()">
                            <i class="fas fa-eye"></i> Preview
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="closeModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" wire:click="save()" wire:loading.attr="disabled">
                            <span wire:loading.remove wire:target="save">{{ $editingTemplate ? 'Update Template' : 'Create Template' }}</span>
                            <span wire:loading wire:target="save"><i class="fas fa-spinner fa-spin"></i> Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Preview Modal -->
    @if($showPreviewModal)
        <div class="modal fade show email-template-modal" style="display: block;" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="previewModalLabel">
                            <i class="fas fa-eye"></i> Email Template Preview
                        </h5>
                        <button type="button" class="btn-close" wire:click="closePreviewModal()"></button>
                    </div>
                    
                    <div class="modal-body p-0">
                        <div class="row g-0">
                            <!-- Desktop Preview -->
                            <div class="col-lg-8">
                                <div class="p-3 border-end">
                                    <h6 class="mb-3 text-muted">
                                        <i class="fas fa-desktop"></i> Desktop View
                                    </h6>
                                    <div class="border rounded p-3" style="background: #f8f9fa; max-height: 600px; overflow-y: auto;">
                                        <div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                                            {!! $previewContent !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Mobile Preview -->
                            <div class="col-lg-4">
                                <div class="p-3">
                                    <h6 class="mb-3 text-muted">
                                        <i class="fas fa-mobile-alt"></i> Mobile View
                                    </h6>
                                    <div class="border rounded p-2" style="background: #f8f9fa; max-height: 600px; overflow-y: auto;">
                                        <div style="width: 320px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); transform: scale(0.8); transform-origin: top;">
                                            {!! $previewContent !!}
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <strong>Preview Notes:</strong><br>
                                            • Variables are replaced with sample data<br>
                                            • Mobile view shows approximate scaling<br>
                                            • Actual emails may render slightly differently across email clients
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Preview
                        </button>
                        <button type="button" class="btn btn-secondary" wire:click="closePreviewModal()">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Inline styles within the root div -->
    <style>
    .email-template-modal .modal-xl {
        max-width: 90%;
    }

    .email-template-modal .modal-content {
        max-height: 90vh;
        overflow-y: auto;
    }

    .email-template-modal .modal-footer {
        border-top: 1px solid #dee2e6;
        padding: 1rem;
        background: #fff;
        position: sticky;
        bottom: 0;
        z-index: 1;
    }

    @media (max-width: 768px) {
        .email-template-modal .modal-xl {
            max-width: 95%;
        }
    }
    </style>
</div>
