@extends('layouts.public')

@section('content')
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

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">{{ __('common.hero_title') }}</h1>
            <p class="lead mb-5">{{ __('common.hero_subtitle') }}</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>{{ __('common.login') }}
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>{{ __('common.get_started') }}
                        </a>
                    @else
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-cog me-2"></i>{{ __('common.admin_panel') ?? 'Go to Admin Panel' }}
                            </a>
                        @elseif(auth()->user()->role === 'customer')
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>{{ __('common.dashboard') ?? 'Go to Dashboard' }}
                            </a>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </section>

    <!-- Social Share Section -->
    <section class="py-4" style="background: #1e293b; color: white;">
        <div class="container text-center">
            <h5 class="mb-3 text-white">{{ __('Share AI Chat Support') }}</h5>
            <x-social-share 
                :url="url('/')"
                :title="config('app.name') . ' - AI-Powered Customer Support'"
                :description="__('Transform your customer support with AI-powered chat solutions')"
                style="buttons"
                size="md"
                theme="dark"
            />
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="text-center mb-5">
                    <h2>{{ __('common.features_title') }}</h2>
                    <p class="text-muted">{{ __('common.features_subtitle') }}</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                                <h5>{{ __('common.features_ai_chat_title') }}</h5>
                                <p class="text-muted">{{ __('common.features_ai_chat_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-3x text-success mb-3"></i>
                                <h5>{{ __('common.features_data_sources_title') }}</h5>
                                <p class="text-muted">{{ __('common.features_data_sources_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-code fa-3x text-warning mb-3"></i>
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
                            <i class="fas fa-language fa-3x text-info mb-3"></i>
                                <h5>{{ __('common.features_multilang_title') }}</h5>
                                <p class="text-muted">{{ __('common.features_multilang_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-search fa-3x text-danger mb-3"></i>
                                <h5>{{ __('common.features_vector_title') }}</h5>
                                <p class="text-muted">{{ __('common.features_vector_desc') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sync fa-3x text-secondary mb-3"></i>
                                <h5>{{ __('common.features_sync_title') }}</h5>
                                <p class="text-muted">{{ __('common.features_sync_desc') }}</p>
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
                <h2>{{ __('common.pricing_title') }}</h2>
                <p class="lead">{{ __('common.pricing_subtitle') }}</p>
                
                <!-- Billing Toggle -->
                <div class="billing-toggle mb-4">
                    <div class="btn-group" role="group" aria-label="Billing cycle">
                        <input type="radio" class="btn-check" name="billingCycle" id="monthly" value="monthly" checked>
                        <label class="btn btn-outline-primary" for="monthly">Monthly</label>
                        
                        <input type="radio" class="btn-check" name="billingCycle" id="yearly" value="yearly">
                        <label class="btn btn-outline-primary" for="yearly">
                            Yearly <small class="badge bg-success ms-1">Save 17%</small>
                        </label>
                    </div>
                </div>
            </div>
            <div class="row pricing-container show-monthly">
                @php
                    try {
                        $plans = App\Models\SubscriptionPlan::where('is_active', true)
                            ->orderBy('sort_order')
                            ->get();
                    } catch (\Throwable $e) {
                        $plans = collect();
                    }
                    $locationService = app()->bound(\App\Services\LocationService::class) ? app(\App\Services\LocationService::class) : null;
                    $isFromIndia = $locationService && method_exists($locationService, 'isFromIndia') ? $locationService->isFromIndia() : false;
                    $currency = $locationService && method_exists($locationService, 'getUserCurrency') ? $locationService->getUserCurrency() : 'USD';
                @endphp

                @php if(!isset($plans) || !($plans instanceof \Illuminate\Support\Collection)) { $plans = collect(); } @endphp
                @forelse($plans as $plan)
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card h-100 {{ $plan->slug === 'pro' ? 'border-primary' : '' }}">
                            @if($plan->slug === 'pro')
                                <div class="card-header bg-primary text-white text-center">
                                    <span class="badge bg-warning text-dark">{{ __('common.most_popular') }}</span>
                                </div>
                            @endif
                            <div class="card-body text-center">
                                <h4 class="card-title">{{ __('common.plan_' . $plan->slug . '_title') }}</h4>
                                <div class="price-section mb-3">
                                    @if($plan->monthly_price > 0)
                                        <div class="monthly-price price-display" data-cycle="monthly">
                                            @php
                                                $monthlyPrice = $plan->getMonthlyPriceForCurrency($currency);
                                                $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                            @endphp
                                            @if($plan->slug === 'starter')
                                                <span class="h3 text-success">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                                @if($currency === 'INR')
                                                    <small class="text-muted"><s>₹{{ number_format(7900, 0) }}</s> promo</small>
                                                @else
                                                    <small class="text-muted"><s>${{ number_format(79, 0) }}</s> promo</small>
                                                @endif
                                            @elseif($plan->slug === 'payg')
                                                <span class="h3">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                                <small class="text-muted"> for credits</small>
                                            @else
                                                <span class="h3">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                                <small class="text-muted">/month</small>
                                            @endif
                                        </div>
                                        <div class="yearly-price price-display" data-cycle="yearly">
                                            @php
                                                $yearlyPrice = $plan->getYearlyPriceForCurrency($currency);
                                            @endphp
                                            @if($plan->slug === 'starter')
                                                <span class="h3 text-success">{{ $currencySymbol }}{{ number_format($yearlyPrice, 0) }}</span>
                                                @if($currency === 'INR')
                                                    <small class="text-muted"><s>₹{{ number_format(79000, 0) }}</s> regular</small>
                                                @else
                                                    <small class="text-muted"><s>${{ number_format(790, 0) }}</s> regular</small>
                                                @endif
                                            @else
                                                <span class="h3">{{ $currencySymbol }}{{ number_format($yearlyPrice, 0) }}</span>
                                            @endif
                                            <small class="text-muted">/year</small>
                                            <div class="text-success small">
                                                <i class="fas fa-check-circle"></i> 
                                                Save {{ $currencySymbol }}{{ number_format(($monthlyPrice * 12) - $yearlyPrice, 0) }} 
                                            </div>
                                        </div>
                                    @elseif($plan->slug === 'payg')
                                        <div class="h3">{{ $currencySymbol }}5</div>
                                        <small class="text-muted">Minimum charge (100k tokens)</small>
                                    @else
                                        <div class="h3">Custom</div>
                                        @php
                                            $customPrice = $plan->getMonthlyPriceForCurrency($currency);
                                            $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                        @endphp
                                        <small class="text-muted">Starting ~{{ $currencySymbol }}{{ number_format($customPrice, 0) }}</small>
                                    @endif
                                </div>
                                
                                <p class="text-muted">{{ __('common.plan_' . $plan->slug . '_desc') }}</p>
                                
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
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="fas fa-check text-success me-2 mt-1 flex-shrink-0"></i>
                                            <span>{{ __('common.plan_' . $plan->slug . '_feature_' . Str::slug($feature, '_')) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer">
                                @guest
                                    <a href="{{ route('register', ['plan' => $plan->slug]) }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100">
                                        {{ __('common.plan_' . $plan->slug . '_button') }}
                                    </a>
                                @else
                                    @if($plan->slug === 'enterprise')
                                        <a href="mailto:sales@ai-chat.support" class="btn btn-outline-primary btn-block w-100">
                                            {{ __('common.plan_enterprise_button') }}
                                        </a>
                                    @elseif($plan->slug === 'payg')
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia)
                                                <a href="{{ route('razorpay.create-onetime-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="{{ route('razorpay.create-onetime-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia)
                                                <a href="{{ route('razorpay.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="{{ route('razorpay.create-subscription-direct', ['planId' => $plan->id, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                @endguest
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-12 text-center">
                        <p class="text-muted">{{ __('common.pricing_coming_soon') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('common.blog_latest_title') }}</h2>
                <p class="lead">{{ __('common.blog_latest_subtitle') }}</p>
            </div>
            
            <div class="row g-4">
                @forelse($latestBlogs as $blog)
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        @if($blog->featured_image)
                        <img src="{{ $blog->featured_image }}" class="card-img-top" alt="{{ $blog->title }}" style="height: 200px; object-fit: cover;">
                        @endif
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                @foreach(array_slice($blog->tags, 0, 2) as $tag)
                                <span class="badge bg-primary">{{ $tag }}</span>
                                @endforeach
                            </div>
                            
                            <h5 class="card-title">{{ $blog->title }}</h5>
                            <p class="card-text flex-grow-1">{{ $blog->excerpt }}</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                {{ $blog->published_at->format('M d, Y') }}
                                <span class="ms-3">
                                    <i class="fas fa-clock me-2"></i>
                                    {{ $blog->reading_time }} {{ __('common.blog_read_time') }}
                                </span>
                            </div>
                            
                            <a href="{{ route('blog.show', $blog->slug) }}" class="btn btn-primary">
                                {{ __('common.blog_read_more') }} <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1677442136019-21780ecad995?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="AI Customer Support Guide" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-primary">{{ __('common.blog_guide_badge') }}</span>
                                <span class="badge bg-secondary">{{ __('common.blog_implementation_badge') }}</span>
                            </div>
                            
                            <h5 class="card-title">{{ __('common.blog_article_1_title') }}</h5>
                            <p class="card-text flex-grow-1">{{ __('common.blog_article_1_desc') }}</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 28, 2025
                                <span class="ms-3">
                                    <i class="fas fa-clock me-2"></i>
                                    3 {{ __('common.blog_read_time') }}
                                </span>
                            </div>
                            
                            <a href="{{ route('blog.index') }}" class="btn btn-primary">
                                {{ __('common.blog_read_more') }} <i class="fas fa-arrow-right ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
                @endforelse
            </div>
            
            <div class="text-center mt-5">
                <a href="{{ route('blog.index') }}" class="btn btn-outline-primary btn-lg">
                    View All Articles <i class="fas fa-arrow-right ms-2"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- Customer Reviews Section -->
    <section class="py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center mb-5">
                    <h2>What Our Customers Say</h2>
                    <p class="lead">See how our AI chat service has transformed customer support for businesses worldwide</p>
                </div>
            </div>
            
            @livewire('public.reviews-display')
            
            <div class="text-center mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        @auth
                            <div class="bg-primary text-white p-4 rounded">
                                <h4>Share Your Experience</h4>
                                <p class="mb-3">Help others discover the benefits of our AI chat service by sharing your experience.</p>
                                <a href="{{ route('reviews.submit') }}" class="btn btn-light btn-lg">
                                    <i class="fas fa-star me-2"></i>Write a Review
                                </a>
                            </div>
                        @else
                            <div class="bg-light p-4 rounded">
                                <h4>Join Our Community</h4>
                                <p class="mb-3">Sign up to share your review and help others discover our AI chat service.</p>
                                <a href="{{ route('register') }}" class="btn btn-primary btn-lg me-2">
                                    <i class="fas fa-user-plus me-2"></i>Sign Up
                                </a>
                                <a href="{{ route('reviews.index') }}" class="btn btn-outline-primary btn-lg">
                                    <i class="fas fa-eye me-2"></i>View All Reviews
                                </a>
                            </div>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <h3>{{ __('common.cta_title') }}</h3>
            <p class="mb-4">{{ __('common.cta_subtitle') }}</p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket me-2"></i>{{ __('common.cta_start_trial') }}
                </a>
            @endguest
        </div>
    </section>

    @auth
    @vite(['resources/js/payment.js'])
    
    <script>
        // Set PayPal client ID for payment.js
        window.paypalClientId = '{{ env('PAYPAL_CLIENT_ID') }}';
        
        // Initialize payment gateways when needed
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize payment SDKs only when subscription buttons are present
            if (document.querySelector('[id^="subscribe-btn-"]')) {
                window.initializePayPal();
                window.initializeRazorpay();
            }
        });
    </script>
    @endauth

    <!-- Billing Cycle Toggle Script - Works for all visitors -->
    <script>
        // Handle billing cycle toggle - simplified approach
        function initBillingToggle() {
            const billingRadios = document.querySelectorAll('input[name="billingCycle"]');
            const pricingContainer = document.querySelector('.pricing-container');
            
            if (!pricingContainer) {
                console.error('Pricing container not found');
                return;
            }
            
            billingRadios.forEach(radio => {
                radio.addEventListener('change', function() {
                    const selectedCycle = this.value;
                    
                    // Remove both classes first
                    pricingContainer.classList.remove('show-monthly', 'show-yearly');
                    
                    // Add the appropriate class
                    if (selectedCycle === 'yearly') {
                        pricingContainer.classList.add('show-yearly');
                    } else {
                        pricingContainer.classList.add('show-monthly');
                    }
                    
                    // Update payment button URLs
                    document.querySelectorAll('.payment-btn').forEach(link => {
                        if (link.href && (link.href.includes('cycle=monthly') || link.href.includes('cycle=yearly'))) {
                            const url = new URL(link.href);
                            url.searchParams.set('cycle', selectedCycle);
                            link.href = url.toString();
                        }
                    });
                });
            });
        }
        
        // Initialize when DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initBillingToggle);
        } else {
            initBillingToggle();
        }
    </script>

    <!-- Footer -->

    <style>
    /* Pricing toggle styles */
    .pricing-container.show-monthly .monthly-price {
        display: block !important;
    }
    .pricing-container.show-monthly .yearly-price {
        display: none !important;
    }
    .pricing-container.show-yearly .monthly-price {
        display: none !important;
    }
    .pricing-container.show-yearly .yearly-price {
        display: block !important;
    }
    
    /* Default state - show monthly */
    .pricing-container .monthly-price {
        display: block;
    }
    .pricing-container .yearly-price {
        display: none;
    }
    
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
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Villa No.10, Sriram Villa, AN Guha Lane",
            "addressLocality": "Sambalpur",
            "postalCode": "768001",
            "addressCountry": "IN"
        },
        "contactPoint": {
            "@type": "ContactPoint",
            "contactType": "customer service",
            "availableLanguage": ["English", "Hindi"]
        },
        "offers": {
            "@type": "Offer",
            "name": "AI Chat Support Service",
            "description": "AI-powered customer support automation with 24/7 availability",
            "category": "Software as a Service",
            "priceCurrency": "USD",
            "price": "49.00"
        }
    }
    </script>
@endsection