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
    <meta property="og:image" content="{{ url('/images/ai-chat-support-banner.jpg') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="AI Chat Support">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="AI Chat Support - Revolutionary Customer Service Automation">
    <meta name="twitter:description" content="Transform your customer support with AI-powered chat solutions. Provide instant 24/7 assistance, reduce costs, and boost customer satisfaction.">
    <meta name="twitter:image" content="{{ url('/images/ai-chat-support-banner.jpg') }}">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="{{ url('/') }}">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
@extends('layouts.public')

@section('content')

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

                <!-- Testing Policy Notice -->
                <div class="alert alert-info mx-auto" style="max-width: 600px;">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h6 class="alert-heading mb-1">🚀 Start Testing for Just $5</h6>
                            <small>Try our Pay-as-you-go plan with 200K tokens. No refunds, but you can upgrade anytime!</small>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-sm btn-primary" onclick="document.getElementById('plans').scrollIntoView({behavior: 'smooth'}); document.querySelector('[onclick*=\'payg\']')?.focus();">
                                View PAYG Plan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
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
                        <div class="card h-100 {{ $plan->slug === 'pro' ? 'border-primary' : ($plan->slug === 'payg' ? 'border-success' : '') }}">
                            @if($plan->slug === 'pro')
                                <div class="card-header bg-primary text-white text-center">
                                    <span class="badge bg-warning text-dark">{{ __('common.most_popular') }}</span>
                                </div>
                            @elseif($plan->slug === 'payg')
                                <div class="card-header bg-success text-white text-center">
                                    <span class="badge bg-light text-success">🚀 Test Drive</span>
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
                                            @else
                                                <span class="h3">{{ $currencySymbol }}{{ number_format($monthlyPrice, 0) }}</span>
                                            @endif
                                            <small class="text-muted">/month</small>
                                        </div>
                                        <div class="yearly-price price-display" data-cycle="yearly" style="display: none;">
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
                                        <div class="h3 text-success">${{ $currency === 'INR' ? '₹500' : '5' }}</div>
                                        <small class="text-muted">
                                            <strong>Perfect for Testing</strong><br>
                                            200K tokens included<br>
                                            <span class="text-danger">No refunds</span>
                                        </small>
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
                                        @if($isFromIndia)
                                            <button onclick="createRazorpaySubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                {{ __('common.plan_payg_button') }}
                                            </button>
                                        @else
                                            <button onclick="createSubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                {{ __('common.plan_payg_button') }}
                                            </button>
                                        @endif
                                    @else
                                        @if($isFromIndia)
                                            <button onclick="createRazorpaySubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                {{ __('common.plan_' . $plan->slug . '_button') }}
                                            </button>
                                        @else
                                            <button onclick="createSubscription({{ $plan->id }})" 
                                                    class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100"
                                                    id="subscribe-btn-{{ $plan->id }}">
                                                {{ __('common.plan_' . $plan->slug . '_button') }}
                                            </button>
                                        @endif
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

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Frequently Asked Questions</h2>
                <p class="lead">Important information about our testing and refund policy</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                    <strong>🚀 Can I test your service before committing to a larger plan?</strong>
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Yes! Start with our Pay-as-you-go plan for just $5 (₹500).</strong> 
                                    You'll get 200,000 tokens to thoroughly test our AI chat system. This is perfect for evaluating our service quality, response accuracy, and integration capabilities before upgrading to a higher plan.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                    <strong>❌ Do you offer refunds?</strong>
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong class="text-danger">No, we do not offer refunds.</strong> However, we encourage you to test our service with the affordable $5 Pay-as-you-go plan first. This gives you ample tokens to evaluate our AI quality and features. If satisfied, you can then upgrade to any higher plan that suits your needs.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                    <strong>📈 Can I upgrade my plan anytime?</strong>
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Absolutely!</strong> You can upgrade from any plan to a higher tier at any time. Unused tokens from your current plan will be credited to your new plan. This makes it risk-free to start small with our Pay-as-you-go option and scale up as your needs grow.
                                </div>
                            </div>
                        </div>
                        
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                    <strong>🔢 What are tokens and how many do I need?</strong>
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Tokens are units of AI processing. Roughly, 1 token ≈ 0.75 words. A typical customer question and AI response uses about 100-300 tokens. With 200K tokens in our $5 test plan, you can handle approximately <strong>600-2000 customer conversations</strong> - plenty for thorough testing!
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Section -->
    <section class="py-5">
        <div class="container">
            <div class="text-center mb-5">
                <h2>{{ __('common.blog_latest_title') }}</h2>
                <p class="lead">{{ __('common.blog_latest_subtitle') }}</p>
            </div>
            
            <div class="row g-4">
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
                
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="Sales Growth with AI" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-success">{{ __('common.blog_sales_badge') }}</span>
                                <span class="badge bg-warning">{{ __('common.blog_growth_badge') }}</span>
                            </div>
                            
                            <h5 class="card-title">{{ __('common.blog_article_2_title') }}</h5>
                            <p class="card-text flex-grow-1">{{ __('common.blog_article_2_desc') }}</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 26, 2025
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
                
                <div class="col-lg-4 col-md-6">
                    <div class="card blog-card h-100 shadow-sm">
                        <img src="https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=500&h=300&fit=crop&crop=center" class="card-img-top" alt="Future of Customer Communications" style="height: 200px; object-fit: cover;">
                        <div class="card-body d-flex flex-column">
                            <div class="mb-3">
                                <span class="badge bg-info">{{ __('common.blog_tips_badge') }}</span>
                                <span class="badge bg-dark">{{ __('common.blog_implementation_badge') }}</span>
                            </div>
                            
                            <h5 class="card-title">{{ __('common.blog_article_3_title') }}</h5>
                            <p class="card-text flex-grow-1">{{ __('common.blog_article_3_desc') }}</p>
                            
                            <div class="blog-meta mb-3 text-muted small">
                                <i class="fas fa-calendar-alt me-2"></i>
                                Aug 24, 2025
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
            </div>
            
            <div class="text-center mt-4">
                <a href="{{ route('blog.index') }}" class="btn btn-primary">{{ __('common.view_all_articles') }}</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @auth
    <!-- PayPal SDK -->
    <script src="https://www.paypal.com/sdk/js?client-id={{ env('PAYPAL_CLIENT_ID') }}&vault=true&intent=subscription"></script>
    
    <!-- Razorpay SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    
    <script>
        async function createSubscription(planId) {
            const button = document.getElementById(`subscribe-btn-${planId}`);
            const originalText = button.innerHTML;
            const billingCycle = document.querySelector('input[name="billingCycle"]:checked').value;
            
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
                        plan_id: planId,
                        billing_cycle: billingCycle
                    })
                });
                
                const data = await response.json();
                
                // Check if user needs to authenticate
                if (response.status === 401 || (data.redirect && data.redirect.includes('login'))) {
                    // Store the plan ID in both sessionStorage (client) and session via query for server-side fallback
                    sessionStorage.setItem('selected_plan_id', planId);
                    sessionStorage.setItem('payment_provider', 'paypal');
                    sessionStorage.setItem('billing_cycle', billingCycle);
                    // Also hit a lightweight URL to persist to session via server after login
                    // Persist in server session too for reliability
                    try { await fetch('{{ route('persist-selected-plan') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ plan_id: planId, provider: 'paypal', billing_cycle: billingCycle }) }); } catch(e) {}
                    document.cookie = `resume_payment=1; path=/; max-age=600; secure`; // hint for post-login
                    document.cookie = `plan_id=${planId}; path=/; max-age=600; secure`;
                    document.cookie = `provider=paypal; path=/; max-age=600; secure`;
                    document.cookie = `cycle=${billingCycle}; path=/; max-age=600; secure`;
                    window.location.href = '{{ route("login") }}';
                    return;
                }
                
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
            const billingCycle = document.querySelector('input[name="billingCycle"]:checked').value;
            
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
                        plan_id: planId,
                        billing_cycle: billingCycle
                    })
                });
                
                const data = await response.json();
                
                // Check if user needs to authenticate
                if (response.status === 401 || (data.redirect && data.redirect.includes('login'))) {
                    // Store the plan ID in both sessionStorage (client) and session via query for server-side fallback
                    sessionStorage.setItem('selected_plan_id', planId);
                    sessionStorage.setItem('payment_provider', 'razorpay');
                    sessionStorage.setItem('billing_cycle', billingCycle);
                    try { await fetch('{{ route('persist-selected-plan') }}', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' }, body: JSON.stringify({ plan_id: planId, provider: 'razorpay', billing_cycle: billingCycle }) }); } catch(e) {}
                    document.cookie = `resume_payment=1; path=/; max-age=600; secure`;
                    document.cookie = `plan_id=${planId}; path=/; max-age=600; secure`;
                    document.cookie = `provider=razorpay; path=/; max-age=600; secure`;
                    document.cookie = `cycle=${billingCycle}; path=/; max-age=600; secure`;
                    window.location.href = '{{ route("login") }}';
                    return;
                }
                
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

        // Check if user was redirected back after login for payment
        document.addEventListener('DOMContentLoaded', function() {
            @auth
            const selectedPlanId = sessionStorage.getItem('selected_plan_id');
            const paymentProvider = sessionStorage.getItem('payment_provider');
            const billingCycle = sessionStorage.getItem('billing_cycle') || 'monthly';

            // Also support resuming via URL query params (e.g., after server redirect)
            const params = new URLSearchParams(window.location.search);
            const resumeFromQuery = params.get('resume_payment');
            const planFromQuery = params.get('plan_id');
            const providerFromQuery = params.get('provider');
            const cycleFromQuery = params.get('cycle') || billingCycle;
            
            const finalPlanId = selectedPlanId || planFromQuery;
            const finalProvider = paymentProvider || providerFromQuery;
            const finalCycle = cycleFromQuery;

            // Check if user just registered and selected a plan (from server session)
            @if(session('plan_selected') && session('auto_payment'))
                const registeredPlanSlug = '{{ session('plan_selected') }}';
                console.log('User just registered with plan:', registeredPlanSlug);
                
                // Find the plan ID by slug
                const planButtons = document.querySelectorAll('[onclick*="createSubscription"], [onclick*="createRazorpaySubscription"]');
                let targetPlanId = null;
                
                planButtons.forEach(button => {
                    const onclick = button.getAttribute('onclick');
                    if (onclick) {
                        const match = onclick.match(/\((\d+)\)/);
                        if (match) {
                            const planId = match[1];
                            // Check if this button is for the registered plan
                            const card = button.closest('.card');
                            if (card && card.innerHTML.includes(registeredPlanSlug)) {
                                targetPlanId = planId;
                            }
                        }
                    }
                });

                if (targetPlanId) {
                    // Auto-scroll to plans section
                    document.getElementById('plans').scrollIntoView({ behavior: 'smooth' });
                    
                    // Show a confirmation dialog and auto-trigger payment
                    setTimeout(() => {
                        if (confirm('Welcome! Ready to complete your {{ ucfirst(session('plan_selected')) }} plan subscription?')) {
                            // Determine payment provider based on location
                            @if(app(\App\Services\LocationService::class)->isFromIndia())
                                createRazorpaySubscription(targetPlanId);
                            @else
                                createSubscription(targetPlanId);
                            @endif
                        }
                    }, 1500);
                }
            @endif

            if (resumeFromQuery && finalPlanId && finalProvider) {
                // Set billing cycle in UI
                document.querySelector(`input[name="billingCycle"][value="${finalCycle}"]`).checked = true;
                // Trigger the toggle to update pricing display
                document.querySelector(`input[name="billingCycle"][value="${finalCycle}"]`).dispatchEvent(new Event('change'));
                
                // Clear the stored values
                sessionStorage.removeItem('selected_plan_id');
                sessionStorage.removeItem('payment_provider');
                sessionStorage.removeItem('billing_cycle');
                // Remove query params from URL
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location);
                    url.searchParams.delete('resume_payment');
                    url.searchParams.delete('plan_id');
                    url.searchParams.delete('provider');
                    url.searchParams.delete('cycle');
                    window.history.replaceState({}, document.title, url.toString());
                }
                
                // Auto-trigger the payment based on provider
                setTimeout(() => {
                    if (finalProvider === 'razorpay') {
                        createRazorpaySubscription(finalPlanId);
                    } else if (finalProvider === 'paypal') {
                        createSubscription(finalPlanId);
                    }
                }, 1000); // Small delay to ensure page is fully loaded
            }
            @endauth
        });
        
        // Handle billing cycle toggle
        document.querySelectorAll('input[name="billingCycle"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const selectedCycle = this.value;
                
                // Hide all price displays
                document.querySelectorAll('.price-display').forEach(display => {
                    display.style.display = 'none';
                });
                
                // Show selected cycle prices
                document.querySelectorAll(`.price-display[data-cycle="${selectedCycle}"]`).forEach(display => {
                    display.style.display = 'block';
                });
            });
        });
    </script>
    @endauth

    <!-- Footer -->

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