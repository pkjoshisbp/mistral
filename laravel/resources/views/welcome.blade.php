<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @auth
        <meta name="user-authenticated" content="true">
    @endauth
    
    <!-- SEO Meta Tags -->
    <title>AI Chat Support - Revolutionary Customer Service Automation | 24/7 AI Assistance</title>
    <meta name="description" content="Transform your customer support with AI-powered chat solutions. Provide instant 24/7 assistance, reduce costs, and boost customer satisfaction with our intelligent AI chat system.">
    <meta name="keywords" content="AI chat support, customer service automation, chatbot, artificial intelligence, live chat, customer support software, AI assistant, automated customer service">
    <meta name="robots" content="index, follow">
    <meta name="author" content="AI Chat Support">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="AI Chat Support - Revolutionary Customer Service Automation">
    <meta property="og:description" content="Transform your customer support with AI-powered chat solutions. Provide instant 24/7 assistance, reduce costs, and boost customer satisfaction.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ asset('images/ai-chat-support-og.jpg') }}">
    <meta property="og:site_name" content="AI Chat Support">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="AI Chat Support - Revolutionary Customer Service Automation">
    <meta name="twitter:description" content="Transform your customer support with AI-powered chat solutions. Provide instant 24/7 assistance, reduce costs, and boost customer satisfaction.">
    <meta name="twitter:image" content="{{ asset('images/ai-chat-support-og.jpg') }}">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url('/') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .blog-card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            overflow: hidden;
        }
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .blog-card img {
            transition: transform 0.3s ease;
        }
        .blog-card:hover img {
            transform: scale(1.05);
        }
        .blog-meta {
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-robot me-2"></i>AI Agent System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#pricing">Pricing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('blog.index') }}">Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('about') }}">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('contact') }}">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="fas fa-cog me-1"></i>Admin Panel
                                </a>
                            </li>
                        @elseif(auth()->user()->role === 'customer')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('customer.dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-link nav-link" style="border: none; background: none;">
                                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">Register</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">AI-Powered Support Agent</h1>
            <p class="lead mb-5">Multi-organization AI support system with advanced data integration capabilities</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Get Started
                        </a>
                    @else
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-cog me-2"></i>Go to Admin Panel
                            </a>
                        @elseif(auth()->user()->role === 'customer')
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Powerful Features</h2>
                <p class="text-muted">Everything you need to power your AI support system</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                            <h5>AI-Powered Chat</h5>
                            <p class="text-muted">Advanced AI models for natural language understanding</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-3x text-success mb-3"></i>
                            <h5>Multiple Data Sources</h5>
                            <p class="text-muted">Website crawling, file uploads, Google Sheets, database connections</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-code fa-3x text-warning mb-3"></i>
                            <h5>Embeddable Widget</h5>
                            <p class="text-muted">Easy-to-integrate JavaScript widget for any website</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-language fa-3x text-info mb-3"></i>
                            <h5>Multi-Language Support</h5>
                            <p class="text-muted">Ollama 3.2 models handle English, Spanish, French, German, Italian, Portuguese, Hindi & more</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-search fa-3x text-danger mb-3"></i>
                            <h5>Vector Search</h5>
                            <p class="text-muted">Powered by Qdrant for semantic search and retrieval</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sync fa-3x text-secondary mb-3"></i>
                            <h5>Real-time Sync</h5>
                            <p class="text-muted">Automatic data synchronization and updates</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Choose Your Plan</h2>
                <p class="lead">Flexible pricing that scales with your business</p>
            </div>
            <div class="row">
                @php
                    $plans = App\Models\SubscriptionPlan::where('is_active', true)
                        ->orderBy('sort_order')
                        ->get();
                    $locationService = app(\App\Services\LocationService::class);
                    $isFromIndia = $locationService->isFromIndia();
                    $currency = $locationService->getUserCurrency();
                @endphp

                @foreach($plans as $plan)
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card h-100 {{ $plan->slug === 'pro' ? 'border-primary' : '' }}">
                            @if($plan->slug === 'pro')
                                <div class="card-header bg-primary text-white text-center">
                                    <span class="badge bg-warning text-dark">Most Popular</span>
                                </div>
                            @endif
                            <div class="card-body text-center">
                                <h4 class="card-title">{{ $plan->name }}</h4>
                                <div class="price-section mb-3">
                                    @if($plan->monthly_price > 0)
                                        <div class="monthly-price">
                                            @php
                                                $monthlyPrice = $plan->getMonthlyPriceForCurrency($currency);
                                                $yearlyPrice = $plan->getYearlyPriceForCurrency($currency);
                                                $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                            @endphp
                                            @if($plan->slug === 'starter')
                                                <span class="h3 text-success">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                                @if($currency === 'INR')
                                                    <small class="text-muted"><s>₹{{ number_format(7900, 0) }}</s> promo</small>
                                                @else
                                                    <small class="text-muted"><s>${{ number_format(79, 0) }}</s> promo</small>
                                                @endif
                                            @else
                                                <span class="h3">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                            @endif
                                            <small class="text-muted">/month</small>
                                        </div>
                                        <div class="yearly-price">
                                            @if($plan->slug === 'starter')
                                                <small class="text-muted">
                                                    {{ $currencySymbol }}{{ number_format($yearlyPrice, 0) }} yearly (promo) 
                                                    @if($currency === 'INR')
                                                        / ₹79,000 normal
                                                    @else 
                                                        / $790 normal
                                                    @endif
                                                </small>
                                            @else
                                                <small class="text-muted">{{ $currencySymbol }}{{ number_format($yearlyPrice, 0) }} yearly (10× monthly)</small>
                                            @endif
                                        </div>
                                    @elseif($plan->slug === 'payg')
                                        <div class="h3">{{ $currencySymbol }}5</div>
                                        <small class="text-muted">Minimum charge (200k tokens)</small>
                                    @else
                                        <div class="h3">Custom</div>
                                        @php
                                            $customPrice = $plan->getMonthlyPriceForCurrency($currency);
                                            $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                        @endphp
                                        <small class="text-muted">Starting ~{{ $currencySymbol }}{{ number_format($customPrice, 0) }}</small>
                                    @endif
                                </div>
                                
                                <p class="text-muted">{{ $plan->description }}</p>
                                
                                <div class="mb-3">
                                    @if($plan->token_cap_monthly > 0)
                                        <strong>{{ $plan->formatted_token_cap }} tokens/month</strong>
                                    @else
                                        <strong>No usage cap</strong>
                                    @endif
                                    <br>
                                    <small class="text-muted">
                                        @php
                                            $overagePrice = $currency === 'INR' ? $locationService->convertToINR($plan->overage_price_per_100k) : $plan->overage_price_per_100k;
                                            $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                        @endphp
                                        Overage: {{ $currencySymbol }}{{ number_format($overagePrice, 0) }} per 100k tokens
                                    </small>
                                </div>

                                <ul class="list-unstyled text-start">
                                    @foreach($plan->features as $feature)
                                        <li class="mb-2">
                                            <i class="fas fa-check text-success me-2"></i>{{ $feature }}
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer">
                                @guest
                                    <a href="{{ route('register', ['plan' => $plan->slug]) }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100">
                                        @if($plan->slug === 'enterprise')
                                            Contact Sales
                                        @else
                                            Choose {{ $plan->name }}
                                        @endif
                                    </a>
                                @else
                                    @if($plan->slug === 'enterprise')
                                        <a href="mailto:sales@ai-chat.support" class="btn btn-outline-primary btn-block w-100">
                                            Contact Sales
                                        </a>
                                    @elseif($plan->slug === 'payg')
                                        @if($isFromIndia)
                                            <button onclick="createRazorpaySubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                Activate Pay-as-you-go
                                            </button>
                                        @else
                                            <button onclick="createSubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                Activate Pay-as-you-go
                                            </button>
                                        @endif
                                    @else
                                        @if($isFromIndia)
                                            <button onclick="createRazorpaySubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                Subscribe to {{ $plan->name }}
                                            </button>
                                        @else
                                            <button onclick="createSubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                Subscribe to {{ $plan->name }}
                                            </button>
                                        @endif
                                    @endif
                                @endguest
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Latest from Our Blog</h2>
                <p class="lead">Stay updated with the latest insights and tips for AI customer support</p>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1677442136019-21780ecad995?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="AI Customer Support Guide" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-primary">Guide</span>
                                <span class="badge bg-secondary">Implementation</span>
                            </div>
                            
                            <h5 class="card-title">Implementing AI Customer Support: A Complete Guide</h5>
                            <p class="card-text flex-grow-1">A practical guide for small businesses to implement AI customer support solutions without breaking the bank, including tips, tools, and strategies.</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 28, 2025
                                <span class="ms-3">
                                    <i class="fas fa-clock me-2"></i>
                                    3 min read
                                </span>
                            </div>
                            
                            <a href="{{ route('blog.index') }}" class="btn btn-primary">
                                Read More <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="Sales Growth with AI" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-success">Sales</span>
                                <span class="badge bg-warning">Growth</span>
                            </div>
                            
                            <h5 class="card-title">Boost Sales with AI Chatbots</h5>
                            <p class="card-text flex-grow-1">Learn how implementing AI chatbots can directly impact your bottom line through improved customer satisfaction, reduced costs, and increased sales conversions.</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 26, 2025
                                <span class="ms-3">
                                    <i class="fas fa-clock me-2"></i>
                                    3 min read
                                </span>
                            </div>
                            
                            <a href="{{ route('blog.index') }}" class="btn btn-primary">
                                Read More <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="Future of Customer Communications" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-info">Future</span>
                                <span class="badge bg-dark">Technology</span>
                            </div>
                            
                            <h5 class="card-title">Future of Customer Communications</h5>
                            <p class="card-text flex-grow-1">Discover how artificial intelligence is transforming customer support, making it more efficient, personalized, and available 24/7 for businesses of all sizes.</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 24, 2025
                                <span class="ms-3">
                                    <i class="fas fa-clock me-2"></i>
                                    3 min read
                                </span>
                            </div>
                            
                            <a href="{{ route('blog.index') }}" class="btn btn-primary">
                                Read More <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <a href="{{ route('blog.index') }}" class="btn btn-primary">View All Articles</a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <h3>Ready to Get Started?</h3>
            <p class="mb-4">Join organizations already using our AI support system</p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
            @endguest
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    @auth
    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id={{ env('PAYPAL_CLIENT_ID') }}&vault=true&intent=subscription"></script>
    
    <!-- Razorpay SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <script>
        async function createSubscription(planId) {
            const button = document.getElementById(`subscribe-btn-${planId}`);
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            try {
                const response = await fetch('{{ route("paypal.create-subscription") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        plan_id: planId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    if (data.approval_url) {
                        // Redirect to PayPal for approval
                        window.location.href = data.approval_url;
                    } else if (data.redirect_url) {
                        // Direct redirect (development mode)
                        alert(data.message || 'Subscription activated successfully!');
                        window.location.href = data.redirect_url;
                    } else {
                        alert(data.message || 'Subscription created successfully!');
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to create subscription'));
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while processing your request');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        async function createRazorpaySubscription(planId) {
            const button = document.getElementById(`subscribe-btn-${planId}`);
            const originalText = button.innerHTML;
            
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            button.disabled = true;
            
            try {
                const response = await fetch('{{ route("razorpay.create-subscription") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        plan_id: planId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Initialize Razorpay
                    const options = {
                        key: data.razorpay_key,
                        subscription_id: data.subscription_id,
                        name: data.name,
                        description: data.description,
                        handler: function (response) {
                            // Handle successful payment
                            const form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '{{ route("razorpay.success") }}';
                            
                            const csrfInput = document.createElement('input');
                            csrfInput.type = 'hidden';
                            csrfInput.name = '_token';
                            csrfInput.value = '{{ csrf_token() }}';
                            form.appendChild(csrfInput);
                            
                            const subscriptionInput = document.createElement('input');
                            subscriptionInput.type = 'hidden';
                            subscriptionInput.name = 'razorpay_subscription_id';
                            subscriptionInput.value = response.razorpay_subscription_id;
                            form.appendChild(subscriptionInput);
                            
                            const paymentInput = document.createElement('input');
                            paymentInput.type = 'hidden';
                            paymentInput.name = 'razorpay_payment_id';
                            paymentInput.value = response.razorpay_payment_id;
                            form.appendChild(paymentInput);
                            
                            const signatureInput = document.createElement('input');
                            signatureInput.type = 'hidden';
                            signatureInput.name = 'razorpay_signature';
                            signatureInput.value = response.razorpay_signature;
                            form.appendChild(signatureInput);
                            
                            document.body.appendChild(form);
                            form.submit();
                        },
                        prefill: data.prefill,
                        theme: {
                            color: '#007bff'
                        },
                        modal: {
                            ondismiss: function() {
                                button.innerHTML = originalText;
                                button.disabled = false;
                            }
                        }
                    };
                    
                    const rzp = new Razorpay(options);
                    rzp.open();
                } else {
                    alert('Error: ' + (data.message || 'Failed to create subscription'));
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while processing your request');
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }
    </script>
    @endauth

    <!-- AI Chat Widget -->
    <div id="ai-chat-widget">
        <div id="ai-chat-button" onclick="toggleChat()">
            <i class="fas fa-comments"></i>
        </div>
        <div id="ai-chat-window" style="display: none;">
            <div id="ai-chat-header">
                <span>AI Chat Support</span>
                <button onclick="toggleChat()" id="close-chat">×</button>
            </div>
            <div id="ai-chat-messages">
                <div class="ai-message">
                    <div class="message-bubble">
                        Hello! I'm your AI assistant. How can I help you today?
                        <div class="timestamp">Just now</div>
                    </div>
                </div>
            </div>
            <div id="ai-chat-input">
                <input type="text" id="chat-message-input" placeholder="Type your message..." onkeypress="handleChatKeyPress(event)">
                <button onclick="sendMessage()" id="send-message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>

    <style>
    #ai-chat-widget {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
    }

    #ai-chat-button {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    #ai-chat-button:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
    }

    #ai-chat-window {
        position: absolute;
        bottom: 70px;
        right: 0;
        width: 420px; /* enlarged */
        height: 600px; /* enlarged */
        background: white;
        border-radius: 12px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    #ai-chat-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 600;
    }

    #close-chat {
        background: none;
        border: none;
        color: white;
        font-size: 20px;
        cursor: pointer;
        padding: 0;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #ai-chat-messages {
        flex: 1;
        padding: 20px;
        overflow-y: auto;
        background: #f8f9fa;
    }

    .message-bubble {
        background: white;
        padding: 12px 16px;
        border-radius: 18px;
        margin-bottom: 10px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        max-width: 80%;
        position: relative;
    }

    .user-message {
        text-align: right;
    }

    .user-message .message-bubble {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        margin-left: auto;
    }

    .timestamp {
        display: block;
        font-size: 10px;
        opacity: 0.7;
        margin-top: 4px;
    }

    .ai-message {
        text-align: left;
    }

    #ai-chat-input {
        padding: 15px 20px;
        background: white;
        border-top: 1px solid #e9ecef;
        display: flex;
        gap: 10px;
    }

    #chat-message-input {
        flex: 1;
        border: 1px solid #dee2e6;
        border-radius: 20px;
        padding: 10px 15px;
        outline: none;
        font-size: 14px;
    }

    #chat-message-input:focus {
        border-color: #667eea;
    }

    #send-message {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #send-message:hover {
        opacity: 0.9;
    }

    @media (max-width: 768px) {
        #ai-chat-window {
            width: 90vw;
            height: 70vh;
        }
    }
    </style>

    <script>
    function toggleChat() {
        const chatWindow = document.getElementById('ai-chat-window');
        const chatButton = document.getElementById('ai-chat-button');
        
        if (chatWindow.style.display === 'none') {
            chatWindow.style.display = 'flex';
            chatButton.style.display = 'none';
        } else {
            chatWindow.style.display = 'none';
            chatButton.style.display = 'flex';
        }
    }

    function handleChatKeyPress(event) {
        if (event.key === 'Enter') {
            sendMessage();
        }
    }

    async function sendMessage() {
        const input = document.getElementById('chat-message-input');
        const message = input.value.trim();
        
        if (!message) return;
        
        // Add user message to chat
        addMessageToChat(message, 'user');
        input.value = '';
        
        // Show typing indicator
        const typingId = addTypingIndicator();
        
        try {
            // Send message to AI Chat Support API
            const response = await fetch('https://ai-chat.support/widget/3/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    message: message,
                    session_id: getOrCreateConversationId()
                })
            });
            
            const data = await response.json();
            
            // Remove typing indicator
            removeTypingIndicator(typingId);
            
            if (data.response) {
                addMessageToChat(data.response, 'ai');
            } else {
                addMessageToChat('Sorry, I encountered an error. Please try again.', 'ai');
            }
        } catch (error) {
            console.error('Chat error:', error);
            removeTypingIndicator(typingId);
            addMessageToChat('Sorry, I\'m having trouble connecting. Please try again later.', 'ai');
        }
    }

    function addMessageToChat(message, sender) {
        const messagesContainer = document.getElementById('ai-chat-messages');
        const messageDiv = document.createElement('div');
        messageDiv.className = sender + '-message';

        const bubbleDiv = document.createElement('div');
        bubbleDiv.className = 'message-bubble';
        bubbleDiv.innerHTML = `${escapeHtml(message)}<div class="timestamp">${formatTimestamp(new Date())}</div>`;

        messageDiv.appendChild(bubbleDiv);
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function formatTimestamp(date) {
        const now = new Date();
        const isToday = date.toDateString() === now.toDateString();
        const hours = date.getHours();
        const minutes = date.getMinutes().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const hour12 = hours % 12 || 12;
        const timePart = `${hour12}:${minutes} ${ampm}`;
        if (isToday) return timePart;
        return `${date.getMonth()+1}/${date.getDate()}/${date.getFullYear()} ${timePart}`;
    }

    function escapeHtml(str) {
        return str.replace(/[&<>"']/g, function(tag) {
            const chars = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };
            return chars[tag] || tag;
        });
    }

    function addTypingIndicator() {
        const messagesContainer = document.getElementById('ai-chat-messages');
        const typingDiv = document.createElement('div');
        const typingId = 'typing-' + Date.now();
        typingDiv.id = typingId;
        typingDiv.className = 'ai-message';
        typingDiv.innerHTML = '<div class="message-bubble">Typing...</div>';
        messagesContainer.appendChild(typingDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        return typingId;
    }

    function removeTypingIndicator(typingId) {
        const typingElement = document.getElementById(typingId);
        if (typingElement) {
            typingElement.remove();
        }
    }

    function getOrCreateConversationId() {
        let conversationId = localStorage.getItem('ai-chat-conversation-id');
        if (!conversationId) {
            conversationId = 'conv-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
            localStorage.setItem('ai-chat-conversation-id', conversationId);
        }
        return conversationId;
    }
    </script>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="footer-brand">
                        <h5 class="mb-3">
                            <i class="fas fa-robot me-2"></i>
                            AI Chat Support
                        </h5>
                        <p class="text-light">
                            Revolutionizing customer support with intelligent AI conversations. 
                            Provide 24/7 assistance to your customers with our advanced chatbot platform.
                        </p>
                        <div class="social-links">
                            <a href="#" class="text-light me-3"><i class="fab fa-twitter fa-lg"></i></a>
                            <a href="#" class="text-light me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                            <a href="#" class="text-light me-3"><i class="fab fa-facebook fa-lg"></i></a>
                            <a href="#" class="text-light"><i class="fab fa-github fa-lg"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3 text-uppercase">Product</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#features" class="text-light text-decoration-none">Features</a></li>
                        <li class="mb-2"><a href="#pricing" class="text-light text-decoration-none">Pricing</a></li>
                        <li class="mb-2"><a href="/widget/1/test" class="text-light text-decoration-none">Demo</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">API Docs</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3 text-uppercase">Company</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="{{ route('about') }}" class="text-light text-decoration-none">About Us</a></li>
                        <li class="mb-2"><a href="{{ route('blog.index') }}" class="text-light text-decoration-none">Blog</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Careers</a></li>
                        <li class="mb-2"><a href="{{ route('contact') }}" class="text-light text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3 text-uppercase">Legal</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="{{ route('privacy') }}" class="text-light text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="{{ route('terms') }}" class="text-light text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="{{ route('refund-policy') }}" class="text-light text-decoration-none">Refund Policy</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Cookie Policy</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6 class="mb-3 text-uppercase">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Documentation</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Community</a></li>
                        <li class="mb-2"><a href="#" class="text-light text-decoration-none">Status</a></li>
                    </ul>
                </div>
            </div>
            
            <hr class="my-4 border-secondary">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 text-light">
                        &copy; {{ date('Y') }} AI Chat Support. All rights reserved.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 text-light">
                        <i class="fas fa-heart text-danger"></i> 
                        Made with AI Technology
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <style>
    footer .social-links a:hover {
        color: #667eea !important;
        transition: color 0.3s ease;
    }
    
    footer ul li a:hover {
        color: #667eea !important;
        transition: color 0.3s ease;
    }
    </style>

    <!-- Schema.org Structured Data -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "AI Chat Support",
        "url": "{{ url('/') }}",
        "logo": "{{ asset('images/logo.png') }}",
        "description": "Revolutionary AI-powered customer support automation platform providing 24/7 intelligent chat assistance.",
        "sameAs": [
            "https://twitter.com/aichatsupport",
            "https://linkedin.com/company/aichatsupport",
            "https://github.com/aichatsupport"
        ],
        "contactPoint": {
            "@type": "ContactPoint",
            "telephone": "+1-555-AI-CHAT",
            "contactType": "customer service"
        },
        "offers": {
            "@type": "Offer",
            "name": "AI Chat Support Service",
            "description": "AI-powered customer support automation with 24/7 availability",
            "category": "Software as a Service"
        }
    }
    </script>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "AI Chat Support",
        "url": "{{ url('/') }}",
        "description": "Transform your customer support with AI-powered chat solutions. Provide instant 24/7 assistance, reduce costs, and boost customer satisfaction.",
        "potentialAction": {
            "@type": "SearchAction",
            "target": "{{ url('/') }}/search?q={search_term_string}",
            "query-input": "required name=search_term_string"
        }
    }
    </script>

    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "AI Chat Support Platform",
        "description": "Revolutionary AI-powered customer support automation platform providing 24/7 intelligent chat assistance.",
        "brand": {
            "@type": "Brand",
            "name": "AI Chat Support"
        },
        "offers": {
            "@type": "Offer",
            "priceCurrency": "USD",
            "price": "29.00",
            "priceValidUntil": "{{ now()->addYear()->format('Y-m-d') }}",
            "availability": "https://schema.org/InStock"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "150"
        }
    }
    </script>
</body>
</html>
