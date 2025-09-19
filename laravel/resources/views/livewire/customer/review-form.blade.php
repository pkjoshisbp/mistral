<div>
    @if(!auth()->check())
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body text-center p-5">
                            <i class="fas fa-lock fa-3x text-muted mb-3"></i>
                            <h3 class="h4 mb-3">Login Required</h3>
                            <p class="text-muted mb-4">Please log in to submit a review for our AI chat service.</p>
                            <a href="{{ route('login') }}" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt me-2"></i>Login to Review
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card shadow">
                        <div class="card-body">
                            @if($submitted)
                                <!-- Success Message -->
                                <div class="alert alert-success text-center">
                                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                                    <h4 class="alert-heading">
                                        {{ $existingReview ? 'Review Updated!' : 'Thank You!' }}
                                    </h4>
                                    <p class="mb-3">{{ session('message') }}</p>
                                    <button wire:click="resetForm" class="btn btn-outline-success">
                                        <i class="fas fa-plus me-2"></i>Submit Another Review
                                    </button>
                                </div>
                            @else
                                <!-- Review Form -->
                                <!-- Header -->
                                <div class="text-center mb-4">
                                    <h2 class="h3 mb-3">
                                        <i class="fas fa-star text-warning me-2"></i>
                                        {{ $existingReview ? 'Update Your Review' : 'Share Your Experience' }}
                                    </h2>
                                    @if($organization)
                                        <p class="text-muted">
                                            Tell us about your experience with {{ $organization->name }}'s AI chat service
                                        </p>
                                    @endif
                                    @if($existingReview)
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Note:</strong> You already have a review for this service. Submitting will update your existing review.
                                        </div>
                                    @endif
                                </div>

                                <form wire:submit="submitReview">
                                    <!-- Rating -->
                                    <div class="mb-4">
                                        <label class="form-label">
                                            Overall Rating <span class="text-danger">*</span>
                                        </label>
                                        <div class="d-flex align-items-center">
                                            @for($i = 1; $i <= 5; $i++)
                                                <button 
                                                    type="button"
                                                    wire:click="setRating({{ $i }})"
                                                    class="btn btn-link p-0 me-1 text-decoration-none"
                                                    style="font-size: 2rem; color: {{ $rating >= $i ? '#ffc107' : '#e9ecef' }};"
                                                >
                                                    {{ $rating >= $i ? '★' : '☆' }}
                                                </button>
                                            @endfor
                                            @if($rating > 0)
                                                <span class="ms-2 text-muted">({{ $rating }}/5 stars)</span>
                                            @endif
                                        </div>
                                        @error('rating') <div class="text-danger small">{{ $message }}</div> @enderror
                                    </div>

                                    <!-- Title -->
                                    <div class="mb-4">
                                        <label for="title" class="form-label">
                                            Review Title <small class="text-muted">(Optional)</small>
                                        </label>
                                        <input 
                                            type="text" 
                                            wire:model="title" 
                                            id="title"
                                            class="form-control @error('title') is-invalid @enderror"
                                            placeholder="Brief summary of your experience..."
                                            maxlength="200"
                                        >
                                        <div class="d-flex justify-content-between mt-1">
                                            @error('title') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <small class="text-muted ms-auto">{{ strlen($title) }}/200</small>
                                        </div>
                                    </div>

                                    <!-- Review -->
                                    <div class="mb-4">
                                        <label for="review" class="form-label">
                                            Your Review <small class="text-muted">(Optional)</small>
                                        </label>
                                        <textarea 
                                            wire:model="review" 
                                            id="review"
                                            rows="4" 
                                            class="form-control @error('review') is-invalid @enderror"
                                            placeholder="Share details about your experience with our AI chat service..."
                                            maxlength="1000"
                                        ></textarea>
                                        <div class="d-flex justify-content-between mt-1">
                                            @error('review') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                            <small class="text-muted ms-auto">{{ strlen($review) }}/1000</small>
                                        </div>
                                    </div>

                                    <!-- Submit Button -->
                                    <div class="d-flex justify-content-end">
                                        @if($existingReview)
                                            <button type="button" wire:click="resetForm" class="btn btn-outline-secondary me-2">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </button>
                                        @endif
                                        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                                            <span wire:loading.remove>
                                                <i class="fas fa-paper-plane me-2"></i>
                                                {{ $existingReview ? 'Update Review' : 'Submit Review' }}
                                            </span>
                                            <span wire:loading>
                                                <i class="fas fa-spinner fa-spin me-2"></i>
                                                {{ $existingReview ? 'Updating...' : 'Submitting...' }}
                                            </span>
                                        </button>
                                    </div>
                                </form>

                                <!-- Help Text -->
                                <hr class="my-4">
                                <div class="text-center">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Your review will be moderated before being published. We appreciate your honest feedback to help us improve our AI chat service.
                                    </small>
                                </div>
                            @endif

                            <!-- Flash Messages -->
                            @if (session()->has('message'))
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle me-2"></i>{{ session('message') }}
                                </div>
                            @endif

                            @if (session()->has('error'))
                                <div class="alert alert-danger mt-3">
                                    <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
