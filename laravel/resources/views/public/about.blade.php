@extends('layouts.app')

@section('title', 'About Us - AI Chat Support')

@section('content')
<!-- Hero Section -->
<section class="hero-section bg-gradient-primary text-white py-5">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">
                    Revolutionizing Customer Support with AI
                </h1>
                <p class="lead mb-4">
                    We're on a mission to make customer support accessible, intelligent, and available 24/7 
                    for businesses of all sizes through the power of artificial intelligence.
                </p>
            </div>
            <div class="col-lg-6">
                <div class="text-center">
                    <i class="fas fa-robot fa-10x text-white opacity-75"></i>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Our Story Section -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <h2 class="text-center mb-5">Our Story</h2>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-lightbulb fa-3x text-warning mb-3"></i>
                                <h5>The Problem</h5>
                                <p class="text-muted">
                                    Small and medium businesses struggle to provide 24/7 customer support 
                                    due to cost and resource constraints, leading to lost opportunities 
                                    and frustrated customers.
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-body text-center">
                                <i class="fas fa-rocket fa-3x text-success mb-3"></i>
                                <h5>Our Solution</h5>
                                <p class="text-muted">
                                    AI Chat Support democratizes intelligent customer service by making 
                                    advanced AI technology affordable and easy to implement for any business.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Mission & Vision -->
<section class="py-5 bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="card h-100 border-0 shadow">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-bullseye fa-2x text-primary me-3"></i>
                            <h3 class="mb-0">Our Mission</h3>
                        </div>
                        <p class="text-muted">
                            To empower every business with intelligent, conversational AI that enhances 
                            customer experience, reduces support costs, and drives growth through better 
                            customer engagement.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="card h-100 border-0 shadow">
                    <div class="card-body p-5">
                        <div class="d-flex align-items-center mb-3">
                            <i class="fas fa-eye fa-2x text-primary me-3"></i>
                            <h3 class="mb-0">Our Vision</h3>
                        </div>
                        <p class="text-muted">
                            A world where every customer interaction is meaningful, every question gets 
                            an instant, accurate answer, and businesses can focus on what they do best 
                            while AI handles support seamlessly.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Key Features -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">What Makes Us Different</h2>
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="text-center">
                    <div class="feature-icon bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-brain fa-2x"></i>
                    </div>
                    <h5>Advanced AI Technology</h5>
                    <p class="text-muted">
                        Our AI understands context, maintains conversation flow, and learns from your 
                        business data to provide accurate, personalized responses.
                    </p>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="text-center">
                    <div class="feature-icon bg-success text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-plug fa-2x"></i>
                    </div>
                    <h5>Easy Integration</h5>
                    <p class="text-muted">
                        No technical expertise required. Add our chat widget to any website with just 
                        a simple copy-paste of code. Works with all platforms.
                    </p>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="text-center">
                    <div class="feature-icon bg-warning text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <h5>Actionable Insights</h5>
                    <p class="text-muted">
                        Get detailed analytics on customer interactions, popular questions, and 
                        satisfaction scores to continuously improve your service.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-5 bg-primary text-white">
    <div class="container">
        <div class="row text-center">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-item">
                    <h2 class="display-4 fw-bold">95%</h2>
                    <p class="mb-0">Response Accuracy</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-item">
                    <h2 class="display-4 fw-bold">24/7</h2>
                    <p class="mb-0">Availability</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-item">
                    <h2 class="display-4 fw-bold">< 2s</h2>
                    <p class="mb-0">Response Time</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-item">
                    <h2 class="display-4 fw-bold">50+</h2>
                    <p class="mb-0">Languages</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Technology Stack -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">Powered by Cutting-Edge Technology</h2>
        <div class="row">
            <div class="col-lg-6 mb-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-microchip fa-2x text-primary me-3 mt-1"></i>
                    <div>
                        <h5>Neural Language Processing</h5>
                        <p class="text-muted">
                            Advanced transformer models trained on vast datasets to understand 
                            natural language with human-like comprehension.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-database fa-2x text-primary me-3 mt-1"></i>
                    <div>
                        <h5>Vector Database</h5>
                        <p class="text-muted">
                            Qdrant-powered vector storage for lightning-fast semantic search 
                            and contextual information retrieval.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-cloud fa-2x text-primary me-3 mt-1"></i>
                    <div>
                        <h5>Cloud Infrastructure</h5>
                        <p class="text-muted">
                            Scalable, secure cloud deployment ensuring 99.9% uptime and 
                            enterprise-grade security for your data.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="d-flex align-items-start">
                    <i class="fas fa-shield-alt fa-2x text-primary me-3 mt-1"></i>
                    <div>
                        <h5>Enterprise Security</h5>
                        <p class="text-muted">
                            End-to-end encryption, GDPR compliance, and SOC 2 Type II 
                            certification to protect your business and customer data.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-gradient-primary text-white">
    <div class="container text-center">
        <h2 class="mb-4">Ready to Transform Your Customer Support?</h2>
        <p class="lead mb-4">
            Join thousands of businesses already using AI Chat Support to deliver exceptional customer experiences.
        </p>
        <div class="d-flex justify-content-center gap-3 flex-wrap">
            <a href="{{ route('register') }}" class="btn btn-light btn-lg">
                <i class="fas fa-rocket me-2"></i>Start Free Trial
            </a>
            <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
                <i class="fas fa-comments me-2"></i>Talk to Sales
            </a>
        </div>
    </div>
</section>

<style>
.hero-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.feature-icon {
    transition: transform 0.3s ease;
}

.feature-icon:hover {
    transform: scale(1.1);
}

.stat-item h2 {
    font-weight: 800;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}
</style>
@endsection
