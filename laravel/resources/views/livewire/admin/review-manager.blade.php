<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-2">
                <i class="fas fa-star text-warning me-2"></i>Customer Reviews
            </h1>
            <p class="text-muted">Manage and moderate customer reviews for all organizations</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-list fa-2x text-muted"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-muted text-uppercase small">Total</h6>
                        <h3 class="mb-0 fw-bold">{{ $stats['total'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-clock fa-2x text-warning"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-warning text-uppercase small">Pending</h6>
                        <h3 class="mb-0 fw-bold text-warning">{{ $stats['pending'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-success text-uppercase small">Approved</h6>
                        <h3 class="mb-0 fw-bold text-success">{{ $stats['approved'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-times-circle fa-2x text-danger"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-danger text-uppercase small">Rejected</h6>
                        <h3 class="mb-0 fw-bold text-danger">{{ $stats['rejected'] }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center">
                    <div class="me-3">
                        <i class="fas fa-star fa-2x text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-primary text-uppercase small">Featured</h6>
                        <h3 class="mb-0 fw-bold text-primary">{{ $stats['featured'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <!-- Search -->
                <div class="col-lg-4 col-md-6">
                    <label for="search" class="form-label">
                        <i class="fas fa-search me-1"></i>Search
                    </label>
                    <input 
                        type="text" 
                        wire:model.live="search" 
                        placeholder="Search reviews, titles, or users..."
                        class="form-control"
                        id="search"
                    >
                </div>

                <!-- Status Filter -->
                <div class="col-lg-2 col-md-6">
                    <label for="statusFilter" class="form-label">Status</label>
                    <select wire:model.live="statusFilter" class="form-select" id="statusFilter">
                        <option value="all">All Statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <!-- Organization Filter -->
                <div class="col-lg-3 col-md-6">
                    <label for="organizationFilter" class="form-label">Organization</label>
                    <select wire:model.live="organizationFilter" class="form-select" id="organizationFilter">
                        <option value="all">All Organizations</option>
                        @foreach($organizations as $org)
                            <option value="{{ $org->id }}">{{ $org->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Rating Filter -->
                <div class="col-lg-3 col-md-6">
                    <label for="ratingFilter" class="form-label">Rating</label>
                    <select wire:model.live="ratingFilter" class="form-select" id="ratingFilter">
                        <option value="all">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                </div>
            </div>

            <!-- Bulk Actions -->
            @if(!empty($selectedReviews))
                <div class="alert alert-info mt-3 d-flex justify-content-between align-items-center">
                    <span>
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>{{ count($selectedReviews) }}</strong> review(s) selected
                    </span>
                    <div class="btn-group">
                        <button wire:click="bulkApprove" class="btn btn-success btn-sm">
                            <i class="fas fa-check me-1"></i>Approve Selected
                        </button>
                        <button wire:click="bulkReject" class="btn btn-danger btn-sm">
                            <i class="fas fa-times me-1"></i>Reject Selected
                        </button>
                        <button 
                            wire:click="bulkDelete" 
                            class="btn btn-outline-secondary btn-sm"
                            onclick="return confirm('Are you sure you want to delete selected reviews?')"
                        >
                            <i class="fas fa-trash me-1"></i>Delete Selected
                        </button>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Reviews Display -->
    <div class="card shadow-sm">
        <div class="card-header bg-light">
            <div class="form-check">
                <input 
                    type="checkbox" 
                    class="form-check-input"
                    wire:model="selectAll"
                    id="selectAll"
                >
                <label class="form-check-label text-muted small text-uppercase fw-bold" for="selectAll">
                    Select All Reviews
                </label>
            </div>
        </div>
        
        <div class="card-body p-0">
                
            @forelse($reviews as $review)
                <div class="border-bottom p-4">
                    <div class="d-flex align-items-start">
                        <div class="me-3">
                            <input 
                                type="checkbox" 
                                value="{{ $review->id }}" 
                                wire:model="selectedReviews"
                                class="form-check-input"
                                id="review_{{ $review->id }}"
                            >
                        </div>
                        
                        <div class="flex-grow-1">
                            <!-- Review Header -->
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="d-flex align-items-center flex-wrap gap-3">
                                    <h5 class="mb-0">{{ $review->title ?: 'Untitled Review' }}</h5>
                                    <div class="d-flex align-items-center">
                                        {!! $review->stars_html !!}
                                        <span class="ms-2 text-muted small">({{ $review->rating }}/5)</span>
                                    </div>
                                    {!! $review->status_badge !!}
                                    @if($review->is_featured)
                                        <span class="badge bg-warning">
                                            <i class="fas fa-star me-1"></i>Featured
                                        </span>
                                    @endif
                                </div>
                                
                                <div class="btn-group">
                                    @if($review->status === 'pending')
                                        <button 
                                            wire:click="approveReview({{ $review->id }})" 
                                            class="btn btn-success btn-sm"
                                            title="Approve Review"
                                        >
                                            <i class="fas fa-check me-1"></i>Approve
                                        </button>
                                        <button 
                                            wire:click="rejectReview({{ $review->id }})" 
                                            class="btn btn-danger btn-sm"
                                            title="Reject Review"
                                        >
                                            <i class="fas fa-times me-1"></i>Reject
                                        </button>
                                    @endif
                                    
                                    <button 
                                        wire:click="toggleFeatured({{ $review->id }})" 
                                        class="btn {{ $review->is_featured ? 'btn-warning' : 'btn-outline-warning' }} btn-sm"
                                        title="{{ $review->is_featured ? 'Remove from Featured' : 'Mark as Featured' }}"
                                    >
                                        <i class="fas fa-star me-1"></i>{{ $review->is_featured ? 'Unfeature' : 'Feature' }}
                                    </button>
                                    
                                    <button 
                                        wire:click="editReview({{ $review->id }})" 
                                        class="btn btn-outline-secondary btn-sm"
                                        title="Add Admin Notes"
                                    >
                                        <i class="fas fa-sticky-note me-1"></i>Notes
                                    </button>
                                    
                                    <button 
                                        wire:click="deleteReview({{ $review->id }})" 
                                        class="btn btn-outline-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to delete this review?')"
                                        title="Delete Review"
                                    >
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Review Content -->
                            @if($review->review)
                                <p class="mb-3 text-dark">{{ Str::limit($review->review, 200) }}</p>
                            @endif
                            
                            <!-- Review Meta -->
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-user me-1"></i><strong>By:</strong> {{ $review->user->name }}
                                    </small>
                                    <small class="text-muted d-block">
                                        <i class="fas fa-building me-1"></i><strong>Organization:</strong> {{ $review->organization->name }}
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted d-block">
                                        <i class="fas fa-calendar me-1"></i><strong>Posted:</strong> {{ $review->created_at->format('M j, Y g:i A') }}
                                    </small>
                                    @if($review->approved_at)
                                        <small class="text-muted d-block">
                                            <i class="fas fa-check me-1"></i><strong>Approved:</strong> {{ $review->approved_at->format('M j, Y') }} by {{ $review->approvedBy?->name }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                            
                            @if($review->admin_notes)
                                <div class="alert alert-warning mt-3 mb-0">
                                    <small>
                                        <i class="fas fa-sticky-note me-1"></i><strong>Admin Notes:</strong> {{ $review->admin_notes }}
                                    </small>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-5 text-center">
                    <i class="fas fa-comments fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No reviews found matching your criteria.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-4">
        {{ $reviews->links() }}
    </div>

    <!-- Admin Notes Modal -->
    @if($showModal)
        <div class="modal fade show d-block" tabindex="-1" style="background-color: rgba(0,0,0,0.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-sticky-note me-2"></i>Admin Notes
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeModal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">Notes for this review:</label>
                            <textarea 
                                wire:model="adminNotes" 
                                rows="4" 
                                class="form-control"
                                id="adminNotes"
                                placeholder="Add internal notes about this review..."
                            ></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" wire:click="closeModal">
                            <i class="fas fa-times me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-primary" wire:click="saveAdminNotes">
                            <i class="fas fa-save me-1"></i>Save Notes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
