<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <!-- Header -->
            <div class="text-center mb-5">
                <div class="d-flex justify-content-center align-items-center mb-3">
                    <i class="fab fa-shopify fa-3x text-success me-3"></i>
                    <i class="fas fa-robot fa-3x text-primary"></i>
                </div>
                <h1 class="display-5 fw-bold text-dark">Install AI Chat Support</h1>
                <p class="lead text-muted">Connect your Shopify store with intelligent AI customer support</p>
            </div>

            <!-- Installation Form -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white text-center py-3">
                    <h3 class="mb-0">
                        <i class="fas fa-download me-2"></i>Shopify App Installation
                    </h3>
                </div>
                
                <div class="card-body p-4">
                    @if($errorMessage)
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>{{ $errorMessage }}
                        </div>
                    @endif

                    <div class="row mb-4">
                        <div class="col-12">
                            <h5 class="fw-bold text-dark mb-3">What you'll get:</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>24/7 AI customer support
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>Instant response to customer questions
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>Seamless theme integration
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>Mobile-responsive chat widget
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>Analytics and reporting
                                        </li>
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>Customizable appearance
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($showManualEntry)
                        <div class="alert alert-info border-0 mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> Normally Shopify provides your store information automatically. 
                            This manual entry is only for edge cases.
                        </div>
                        
                        <form wire:submit="startInstallation">
                            <div class="mb-4">
                                <label for="shopDomain" class="form-label fw-bold">
                                    <i class="fab fa-shopify me-2"></i>Your Shopify Store Domain
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">https://</span>
                                    <input 
                                        type="text" 
                                        class="form-control @error('shopDomain') is-invalid @enderror" 
                                        id="shopDomain"
                                        wire:model="shopDomain"
                                        placeholder="your-store-name"
                                        autocomplete="off"
                                    >
                                    <span class="input-group-text">.myshopify.com</span>
                                </div>
                                @error('shopDomain')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="form-text text-muted">
                                    Enter just your store name (e.g., "my-awesome-store")
                                </small>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fab fa-shopify me-2"></i>Install on Shopify
                                    <span class="ms-2">
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                </button>
                            </div>
                        </form>
                    @else
                        <div class="text-center py-4">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3 text-muted">Connecting to Shopify...</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Installation Steps -->
            <div class="card mt-4 shadow-sm border-0">
                <div class="card-header bg-light">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-list-ol me-2"></i>Installation Process
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <strong>1</strong>
                            </div>
                            <h6 class="mt-2 fw-bold">Enter Store Domain</h6>
                            <small class="text-muted">Provide your Shopify store name</small>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <strong>2</strong>
                            </div>
                            <h6 class="mt-2 fw-bold">Shopify Login</h6>
                            <small class="text-muted">Authenticate with your Shopify account</small>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <strong>3</strong>
                            </div>
                            <h6 class="mt-2 fw-bold">Setup Complete</h6>
                            <small class="text-muted">Configure and activate your AI chat</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Security Notice -->
            <div class="alert alert-info mt-4">
                <h6 class="fw-bold mb-2">
                    <i class="fas fa-shield-alt me-2"></i>Security & Privacy
                </h6>
                <small>
                    This installation uses Shopify's secure OAuth system. We only request the minimum permissions needed 
                    to install the chat widget on your store. Your customer data and store information remain secure and private.
                </small>
            </div>

            <!-- Support -->
            <div class="text-center mt-4">
                <p class="text-muted">
                    Need help with installation? 
                    <a href="{{ route('contact') }}" class="text-decoration-none">
                        <i class="fas fa-envelope me-1"></i>Contact our support team
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>