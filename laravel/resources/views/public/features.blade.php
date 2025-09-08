@extends('layouts.public')

@section('title', 'Features - AI Chat Support')
@section('meta_description', 'Discover powerful AI chat support features including intelligent conversations, multiple data sources, easy widget integration, multilingual support, vector search, and real-time sync.')

@push('styles')
<style>
    .hero-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 80px 0;
    }
    .feature-card {
        border: none;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }
    .feature-card:hover {
        transform: translateY(-5px);
    }
    .feature-icon {
        font-size: 3rem;
        margin-bottom: 1rem;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">{{ __('common.features_title') }}</h1>
            <p class="lead mb-5">{{ __('common.features_subtitle') }}</p>
            <a href="{{ route('register') }}" class="btn btn-light btn-lg">
                <i class="fas fa-rocket me-2"></i>{{ __('common.get_started') }}
            </a>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-robot feature-icon text-primary"></i>
                            <h5>{{ __('common.features_ai_chat_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_ai_chat_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-database feature-icon text-success"></i>
                            <h5>{{ __('common.features_data_sources_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_data_sources_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-code feature-icon text-warning"></i>
                            <h5>{{ __('common.features_widget_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_widget_desc') }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-language feature-icon text-info"></i>
                            <h5>{{ __('common.features_multilang_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_multilang_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-search feature-icon text-danger"></i>
                            <h5>{{ __('common.features_vector_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_vector_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sync feature-icon text-secondary"></i>
                            <h5>{{ __('common.features_sync_title') }}</h5>
                            <p class="text-muted">{{ __('common.features_sync_desc') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Additional Features Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>More Powerful Features</h2>
                <p class="text-muted">Everything you need for comprehensive customer support</p>
            </div>
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-chart-line fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5>Advanced Analytics</h5>
                            <p class="text-muted">Track conversation metrics, customer satisfaction, and response times with detailed analytics.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-shield-alt fa-2x text-success"></i>
                        </div>
                        <div>
                            <h5>Enterprise Security</h5>
                            <p class="text-muted">Bank-level security with data encryption, GDPR compliance, and secure data handling.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-cog fa-2x text-warning"></i>
                        </div>
                        <div>
                            <h5>Easy Customization</h5>
                            <p class="text-muted">Customize the chat widget appearance, behavior, and responses to match your brand.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-mobile-alt fa-2x text-info"></i>
                        </div>
                        <div>
                            <h5>Mobile Responsive</h5>
                            <p class="text-muted">Works perfectly on all devices - desktop, tablet, and mobile with responsive design.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-clock fa-2x text-danger"></i>
                        </div>
                        <div>
                            <h5>24/7 Availability</h5>
                            <p class="text-muted">Your AI assistant never sleeps, providing instant responses any time of day or night.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="d-flex">
                        <div class="me-3">
                            <i class="fas fa-plug fa-2x text-secondary"></i>
                        </div>
                        <div>
                            <h5>API Integration</h5>
                            <p class="text-muted">Integrate with your existing systems using our powerful REST API and webhooks.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action Section -->
    <section class="py-5">
        <div class="container text-center">
            <h2 class="mb-4">Ready to Transform Your Customer Support?</h2>
            <p class="lead mb-4">Join thousands of businesses using AI Chat Support to improve customer satisfaction and reduce support costs.</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    @guest
                        <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-3">
                            <i class="fas fa-user-plus me-2"></i>Get Started Free
                        </a>
                        <a href="{{ route('contact') }}" class="btn btn-outline-primary btn-lg">
                            <i class="fas fa-envelope me-2"></i>Contact Sales
                        </a>
                    @else
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-cog me-2"></i>Go to Admin Panel
                            </a>
                        @else
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-primary btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </section>

@endsection
