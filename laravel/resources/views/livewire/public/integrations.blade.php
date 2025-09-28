<div class="container my-5">
    <!-- Header -->
    <div class="row">
        <div class="col-12 text-center mb-5">
            <h1 class="display-4 fw-bold text-primary mb-3">
                <i class="fas fa-puzzle-piece me-3"></i>Integrations
            </h1>
            <p class="lead text-muted">
                Easy integration with your favorite platforms. Install our AI Chat Support system 
                on WordPress, WooCommerce, and Shopify with just a few clicks.
            </p>
        </div>
    </div>

    <!-- Flash Messages -->
    @if (session()->has('message'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('message') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- WordPress Plugin -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-primary text-white py-3">
                    <div class="d-flex align-items-center">
                        <i class="fab fa-wordpress fa-2x me-3"></i>
                        <div>
                            <h3 class="mb-0">{{ $pluginFiles['wordpress']['name'] }}</h3>
                            <small class="opacity-75">Version {{ $pluginFiles['wordpress']['version'] }}</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Description -->
                        <div class="col-md-6 mb-4">
                            <h5 class="fw-bold text-dark mb-3">Description</h5>
                            <p class="text-muted mb-4">{{ $pluginFiles['wordpress']['description'] }}</p>
                            
                            <!-- Download Button -->
                            <div class="d-grid mb-3">
                                <button wire:click="downloadWordPress" class="btn btn-primary btn-lg">
                                    <i class="fas fa-download me-2"></i>Download WordPress Plugin
                                    <small class="ms-2">({{ $pluginFiles['wordpress']['size'] }})</small>
                                </button>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> You can also find this plugin on the official WordPress.org repository.
                                </small>
                            </div>
                        </div>
                        
                        <!-- Features & Requirements -->
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6 class="fw-bold text-dark">Features:</h6>
                                    <ul class="list-unstyled">
                                        @foreach($pluginFiles['wordpress']['features'] as $feature)
                                            <li class="mb-1">
                                                <i class="fas fa-check text-success me-2"></i>{{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark">Requirements:</h6>
                                    <ul class="list-unstyled">
                                        @foreach($pluginFiles['wordpress']['requirements'] as $requirement)
                                            <li class="mb-1">
                                                <i class="fas fa-cog text-muted me-2"></i>{{ $requirement }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shopify App -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-success text-white py-3">
                    <div class="d-flex align-items-center">
                        <i class="fab fa-shopify fa-2x me-3"></i>
                        <div>
                            <h3 class="mb-0">{{ $pluginFiles['shopify']['name'] }}</h3>
                            <small class="opacity-75">Version {{ $pluginFiles['shopify']['version'] }}</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Description -->
                        <div class="col-md-6 mb-4">
                            <h5 class="fw-bold text-dark mb-3">Description</h5>
                            <p class="text-muted mb-4">{{ $pluginFiles['shopify']['description'] }}</p>
                            
                            <!-- Install Button -->
                            <div class="d-grid mb-3">
                                <a href="{{ $pluginFiles['shopify']['install_url'] }}" target="_blank" class="btn btn-success btn-lg">
                                    <i class="fab fa-shopify me-2"></i>Install Shopify App
                                </a>
                            </div>
                            
                            <div class="alert alert-info">
                                <small>
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Also available on the Shopify App Store for easy discovery.
                                </small>
                            </div>
                        </div>
                        
                        <!-- Features & Requirements -->
                        <div class="col-md-6">
                            <div class="row">
                                <div class="col-12 mb-3">
                                    <h6 class="fw-bold text-dark">Features:</h6>
                                    <ul class="list-unstyled">
                                        @foreach($pluginFiles['shopify']['features'] as $feature)
                                            <li class="mb-1">
                                                <i class="fas fa-check text-success me-2"></i>{{ $feature }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                                <div class="col-12">
                                    <h6 class="fw-bold text-dark">Requirements:</h6>
                                    <ul class="list-unstyled">
                                        @foreach($pluginFiles['shopify']['requirements'] as $requirement)
                                            <li class="mb-1">
                                                <i class="fas fa-cog text-muted me-2"></i>{{ $requirement }}
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Installation Instructions -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-book me-2"></i>Installation Instructions
                    </h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- WordPress Instructions -->
                        <div class="col-md-6 mb-4">
                            <h5 class="fw-bold text-primary">
                                <i class="fab fa-wordpress me-2"></i>WordPress/WooCommerce
                            </h5>
                            <ol class="list-group list-group-numbered">
                                <li class="list-group-item border-0 ps-0">
                                    Download the plugin ZIP file using the button above
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Go to your WordPress Admin → Plugins → Add New
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Click "Upload Plugin" and select the downloaded ZIP file
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Click "Install Now" and then "Activate Plugin"
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Go to Settings → AI Chat Support to configure
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Enter your organization details and customize settings
                                </li>
                            </ol>
                        </div>
                        
                        <!-- Shopify Instructions -->
                        <div class="col-md-6 mb-4">
                            <h5 class="fw-bold text-success">
                                <i class="fab fa-shopify me-2"></i>Shopify
                            </h5>
                            <ol class="list-group list-group-numbered">
                                <li class="list-group-item border-0 ps-0">
                                    Click the "Install Shopify App" button above
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    You'll be redirected to Shopify OAuth login
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Log in to your Shopify admin account
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Review and accept the app permissions
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Complete the setup wizard in the app
                                </li>
                                <li class="list-group-item border-0 ps-0">
                                    Customize the chat widget appearance
                                </li>
                            </ol>
                        </div>
                    </div>

                    <!-- Support Contact -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="alert alert-primary">
                                <h6 class="fw-bold mb-2">
                                    <i class="fas fa-headset me-2"></i>Need Help?
                                </h6>
                                <p class="mb-2">
                                    If you need assistance with installation or configuration, our support team is here to help!
                                </p>
                                <a href="{{ route('contact') }}" class="btn btn-primary btn-sm">
                                    <i class="fas fa-envelope me-2"></i>Contact Support
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>