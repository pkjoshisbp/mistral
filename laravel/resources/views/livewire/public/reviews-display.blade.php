<div class="container">
    <div class="row">
        <div class="col-12">
            <!-- Header Section -->
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">
                    <i class="fas fa-star text-warning me-2"></i>Customer Reviews
                </h2>
                @if($organization)
                    <p class="lead text-muted">What our customers say about {{ $organization->name }}'s AI Chat Service</p>
                @else
                    <p class="lead text-muted">What our customers say about our AI Chat Services</p>
                @endif
            </div>

            <!-- Stats Overview -->
            @if($stats['total'] > 0)
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <div class="row">
                            <!-- Average Rating -->
                            <div class="col-md-6 text-center">
                                <div class="display-4 fw-bold text-dark">{{ $stats['average'] }}</div>
                                <div class="mb-2">
                                    @for($i = 1; $i <= 5; $i++)
                                        <span class="fs-3" style="color: {{ $i <= floor($stats['average']) ? '#ffc107' : ($i - 0.5 <= $stats['average'] ? '#fff3cd' : '#e9ecef') }}">★</span>
                                    @endfor
                                </div>
                                <p class="text-muted">Based on {{ $stats['total'] }} {{ Str::plural('review', $stats['total']) }}</p>
                            </div>

                            <!-- Rating Breakdown -->
                            <div class="col-md-6">
                                @foreach($stats['breakdown'] as $rating => $data)
                                    <div class="d-flex align-items-center mb-2">
                                        <span class="text-muted small" style="min-width: 60px;">{{ $rating }} star</span>
                                        <div class="flex-grow-1 mx-3">
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $data['percentage'] }}%"></div>
                                            </div>
                                        </div>
                                        <span class="text-muted small" style="min-width: 80px;">{{ $data['count'] }} ({{ $data['percentage'] }}%)</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Filters -->
            <div class="card shadow mb-4">
                <div class="card-body">
                    <div class="row g-3 align-items-end">
                        <!-- Rating Filter -->
                        <div class="col-md-4">
                            <label for="ratingFilter" class="form-label">Filter by Rating</label>
                            <select wire:model.live="ratingFilter" class="form-select" id="ratingFilter">
                                <option value="all">All Ratings</option>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>

                        <!-- Sort By -->
                        <div class="col-md-4">
                            <label for="sortBy" class="form-label">Sort By</label>
                            <select wire:model.live="sortBy" class="form-select" id="sortBy">
                                <option value="latest">Latest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="rating_high">Highest Rating</option>
                                <option value="rating_low">Lowest Rating</option>
                            </select>
                        </div>

                        <!-- Featured Only -->
                        <div class="col-md-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="showFeaturedOnly" wire:model.live="showFeaturedOnly">
                                <label class="form-check-label" for="showFeaturedOnly">
                                    <i class="fas fa-star text-warning me-1"></i>Featured Reviews Only
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reviews Grid -->
            @if($reviews->count() > 0)
                <div class="row">
                    @foreach($reviews as $review)
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="card h-100 shadow-sm {{ $review->is_featured ? 'border-warning' : '' }}">
                                @if($review->is_featured)
                                    <div class="card-header bg-warning text-white py-2">
                                        <small class="fw-bold">
                                            <i class="fas fa-star me-1"></i>Featured Review
                                        </small>
                                    </div>
                                @endif
                                <div class="card-body d-flex flex-column">
                                    <!-- Review Header -->
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center mb-2">
                                            {!! $review->stars_html !!}
                                            <span class="ms-2 text-muted small">({{ $review->rating }}/5)</span>
                                        </div>
                                        @if($review->title)
                                            <h5 class="card-title">{{ $review->title }}</h5>
                                        @endif
                                    </div>

                                    <!-- Review Content -->
                                    @if($review->review)
                                        <p class="card-text flex-grow-1">{{ $review->review }}</p>
                                    @endif

                                    <!-- Review Footer -->
                                    <div class="mt-auto">
                                        <hr>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <small class="fw-semibold text-dark">{{ $review->user->name }}</small>
                                                @if(!$organizationId)
                                                    <br><small class="text-muted">{{ $review->organization->name }}</small>
                                                @endif
                                            </div>
                                            <small class="text-muted">{{ $review->created_at->format('M j, Y') }}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {{ $reviews->links() }}
                </div>
            @else
                <!-- No Reviews State -->
                <div class="card shadow">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-comments fa-4x text-muted mb-4"></i>
                        <h3 class="h4 mb-3">
                            @if($ratingFilter !== 'all' || $showFeaturedOnly)
                                No Reviews Found
                            @else
                                 
                            @endif
                        </h3>
                        <p class="text-muted mb-4">
                            @if($ratingFilter !== 'all' || $showFeaturedOnly)
                                No reviews found matching your criteria. Try adjusting your filters.
                            @else
                                 
                            @endif
                        </p>
                    </div>
                </div>
            @endif

           
    </div>
</div>
