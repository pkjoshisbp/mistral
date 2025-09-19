<div>
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    @if($editMode)
        <!-- Edit Mode -->
        <form wire:submit.prevent="saveContent">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="title">Page Title</label>
                        <input type="text" class="form-control @error('title') is-invalid @enderror" 
                               wire:model="title" placeholder="Contact page title">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="subtitle">Subtitle</label>
                        <input type="text" class="form-control @error('subtitle') is-invalid @enderror" 
                               wire:model="subtitle" placeholder="Page subtitle">
                        @error('subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="description">Description</label>
                        <textarea class="form-control @error('description') is-invalid @enderror" 
                                  wire:model="description" rows="4" 
                                  placeholder="Contact page description"></textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="email">Contact Email</label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror" 
                               wire:model="email" placeholder="contact@company.com">
                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                               wire:model="phone" placeholder="+1 (555) 123-4567">
                        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="address">Address</label>
                        <textarea class="form-control @error('address') is-invalid @enderror" 
                                  wire:model="address" rows="3" 
                                  placeholder="Company address"></textarea>
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-3">
                <label for="business_hours">Business Hours</label>
                <input type="text" class="form-control @error('business_hours') is-invalid @enderror" 
                       wire:model="business_hours" placeholder="Monday - Friday: 9:00 AM - 6:00 PM EST">
                @error('business_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            
            <div class="form-group mb-3">
                <label for="map_embed">Google Maps Embed Code (Optional)</label>
                <textarea class="form-control" wire:model="map_embed" rows="3" 
                          placeholder='<iframe src="https://www.google.com/maps/embed..." width="100%" height="300" frameborder="0"></iframe>'></textarea>
                <small class="form-text text-muted">Paste the complete iframe embed code from Google Maps</small>
            </div>
            
            <div class="d-flex justify-content-end">
                <button type="button" class="btn btn-secondary me-2" wire:click="disableEditMode">
                    Cancel
                </button>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    @else
        <!-- View Mode -->
        <div class="row">
            <div class="col-lg-8">
                <div class="contact-content">
                    <h1 class="display-4 mb-3">{{ $title }}</h1>
                    <p class="lead text-muted mb-4">{{ $subtitle }}</p>
                    <p class="mb-4">{{ $description }}</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <div class="contact-info-card h-100 p-4 border rounded">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-envelope fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1">Email Us</h5>
                                        <p class="text-muted mb-0">Send us a message</p>
                                    </div>
                                </div>
                                <p class="mb-0">
                                    <a href="mailto:{{ $email }}" class="text-decoration-none">{{ $email }}</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="contact-info-card h-100 p-4 border rounded">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-phone fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1">Call Us</h5>
                                        <p class="text-muted mb-0">Mon-Fri from 8am to 5pm</p>
                                    </div>
                                </div>
                                <p class="mb-0">
                                    <a href="tel:{{ str_replace([' ', '(', ')', '-'], '', $phone) }}" class="text-decoration-none">{{ $phone }}</a>
                                </p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="contact-info-card h-100 p-4 border rounded">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1">Visit Us</h5>
                                        <p class="text-muted mb-0">Come say hello</p>
                                    </div>
                                </div>
                                <p class="mb-0">{{ $address }}</p>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <div class="contact-info-card h-100 p-4 border rounded">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="contact-icon me-3">
                                        <i class="fas fa-clock fa-2x text-primary"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-1">Business Hours</h5>
                                        <p class="text-muted mb-0">When we're available</p>
                                    </div>
                                </div>
                                <p class="mb-0">{{ $business_hours }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="contact-form-section">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-paper-plane me-2"></i>
                                Send us a Message
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($contactSubmitted)
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Thank you!</strong> Your message has been sent successfully. We'll get back to you soon!
                                </div>
                                <div class="text-center">
                                    <button type="button" class="btn btn-outline-primary" wire:click="resetContactForm">
                                        <i class="fas fa-plus me-2"></i>Send Another Message
                                    </button>
                                </div>
                            @else
                                <form wire:submit.prevent="submitContactForm">
                                    <!-- Honeypot field (hidden) -->
                                    <div style="display: none;">
                                        <input type="text" wire:model="honeypot" tabindex="-1" autocomplete="off">
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="contactName">Name *</label>
                                        <input type="text" class="form-control @error('contactName') is-invalid @enderror" 
                                               wire:model="contactName" id="contactName" required>
                                        @error('contactName')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="contactEmail">Email *</label>
                                        <input type="email" class="form-control @error('contactEmail') is-invalid @enderror" 
                                               wire:model="contactEmail" id="contactEmail" required>
                                        @error('contactEmail')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="contactPhone">Phone (Optional)</label>
                                        <input type="text" class="form-control @error('contactPhone') is-invalid @enderror" 
                                               wire:model="contactPhone" id="contactPhone">
                                        @error('contactPhone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="contactSubject">Subject *</label>
                                        <input type="text" class="form-control @error('contactSubject') is-invalid @enderror" 
                                               wire:model="contactSubject" id="contactSubject" required>
                                        @error('contactSubject')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <div class="form-group mb-3">
                                        <label for="contactMessage">Message *</label>
                                        <textarea class="form-control @error('contactMessage') is-invalid @enderror" 
                                                  wire:model="contactMessage" id="contactMessage" rows="4" required></textarea>
                                        @error('contactMessage')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    
                                    <!-- Math Captcha -->
                                    <div class="form-group mb-3">
                                        <label for="mathAnswer">
                                            <i class="fas fa-calculator me-2"></i>
                                            Security Check: What is {{ $mathQuestion }}? *
                                        </label>
                                        <input type="number" class="form-control @error('mathAnswer') is-invalid @enderror" 
                                               wire:model="mathAnswer" id="mathAnswer" required 
                                               placeholder="Enter the answer">
                                        @error('mathAnswer')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        <small class="form-text text-muted">
                                            Please solve this simple math problem to verify you're human.
                                        </small>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary w-100" wire:loading.attr="disabled">
                                        <span wire:loading.remove>
                                            <i class="fas fa-paper-plane me-2"></i>Send Message
                                        </span>
                                        <span wire:loading>
                                            <i class="fas fa-spinner fa-spin me-2"></i>Sending...
                                        </span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        @if($map_embed)
            <div class="mt-5">
                <h4 class="mb-3">Find Us</h4>
                <div class="map-container">
                    {!! $map_embed !!}
                </div>
            </div>
        @endif
    @endif

    <style>
    .contact-info-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .contact-info-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }
    
    .contact-icon {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: rgba(102, 126, 234, 0.1);
        border-radius: 50%;
    }
    
    .map-container iframe {
        border-radius: 8px;
        width: 100%;
        height: 300px;
    }
    </style>
</div>
