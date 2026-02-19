@extends('layouts.public')

@section('title', 'AI Chatbot Features - 24/7 Automation & WhatsApp Integration')
@section('meta_description', 'Discover powerful AI chatbot for website with 24/7 customer support automation, automated WhatsApp replies, AI support bot for customer service, chatbot integration with website, and AI-powered virtual assistant for customers.')
@section('keywords', 'AI chatbot for business websites, 24/7 live chat support automation, WhatsApp chatbot for business support, AI chatbot automation tools, chatbot integration with website, automated lead generation chatbot, AI support bot for customer service')

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

    <!-- Detailed Features & Benefits Section -->
    <section class="py-5" style="background: #f8f9fa;">
        <div class="container">
            <div class="row">
                <div class="col-lg-10 mx-auto">
                    <h2 class="text-center mb-5">Everything You Need to Automate Customer Support</h2>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-comments text-primary me-2"></i>Website Chat</h5>
                                    <p class="card-text">Add AI chat to your website in minutes. Answer visitor questions instantly, capture leads, and provide support 24/7.</p>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i>Instant answers to common questions</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Lead capture when visitors show interest</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Works even when your team is offline</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fab fa-whatsapp text-success me-2"></i>WhatsApp Integration</h5>
                                    <p class="card-text">Connect your business WhatsApp and let AI handle routine messages. Perfect for appointment reminders and follow-ups.</p>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i>Auto-reply to customer messages</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Send automated reminders</li>
                                        <li><i class="fas fa-check text-success me-2"></i>Follow up with leads automatically</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded shadow-sm mb-4">
                        <h4 class="mb-3">Built for Results</h4>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-dollar-sign text-success me-2"></i><strong>Lower Costs:</strong> Reduce support expenses by up to 70%</li>
                                    <li class="mb-2"><i class="fas fa-chart-line text-primary me-2"></i><strong>More Engagement:</strong> Visitors get answers instantly and stay on your site longer</li>
                                    <li class="mb-2"><i class="fas fa-users text-info me-2"></i><strong>Better Leads:</strong> Qualify visitors before your team calls</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="list-unstyled">
                                    <li class="mb-2"><i class="fas fa-clock text-warning me-2"></i><strong>Always Available:</strong> Support customers 24/7</li>
                                    <li class="mb-2"><i class="fas fa-bolt text-danger me-2"></i><strong>Instant Response:</strong> No more waiting for replies</li>
                                    <li class="mb-2"><i class="fas fa-robot text-secondary me-2"></i><strong>Set and Forget:</strong> Works automatically once configured</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <i class="fas fa-shopping-cart text-primary fa-2x mb-2"></i>
                            <h6 class="mb-1">Ecommerce</h6>
                            <small class="text-muted">Answer product questions instantly</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <i class="fas fa-heartbeat text-danger fa-2x mb-2"></i>
                            <h6 class="mb-1">Healthcare</h6>
                            <small class="text-muted">Book appointments 24/7</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <i class="fas fa-briefcase text-success fa-2x mb-2"></i>
                            <h6 class="mb-1">Small Business</h6>
                            <small class="text-muted">Handle inquiries automatically</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <i class="fas fa-handshake text-warning fa-2x mb-2"></i>
                            <h6 class="mb-1">Sales Teams</h6>
                            <small class="text-muted">Qualify leads before calling</small>
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
