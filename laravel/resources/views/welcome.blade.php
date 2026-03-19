@extends('layouts.public')

@section('content')
@php
    $clientLogoGap = max(8, min(80, (int) \App\Models\AdminSetting::get('homepage_client_logo_gap', 24)));
    $clientLogoHeight = max(40, min(140, (int) \App\Models\AdminSetting::get('homepage_client_logo_height', 100)));
@endphp
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
    .clients-section {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        overflow: hidden;
        --client-logo-gap: {{ $clientLogoGap }}px;
        --client-logo-height: {{ $clientLogoHeight }}px;
    }
    .clients-kicker {
        letter-spacing: 0.18em;
        text-transform: uppercase;
        font-size: 0.78rem;
        font-weight: 700;
        color: #0d6efd;
    }
    .clients-carousel-shell {
        position: relative;
        overflow: hidden;
        padding: 0.5rem 0;
    }
    .clients-carousel-shell::before,
    .clients-carousel-shell::after {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        width: 100px;
        z-index: 2;
        pointer-events: none;
    }
    .clients-carousel-shell::before {
        left: 0;
        background: linear-gradient(90deg, #f8fbff 0%, rgba(248, 251, 255, 0) 100%);
    }
    .clients-carousel-shell::after {
        right: 0;
        background: linear-gradient(270deg, #f8fbff 0%, rgba(248, 251, 255, 0) 100%);
    }
    .clients-carousel-track {
        display: flex;
        align-items: stretch;
        width: max-content;
        animation: clients-scroll 42s linear infinite;
    }
    .clients-carousel-shell:hover .clients-carousel-track {
        animation-play-state: paused;
    }
    .client-logo-card {
        width: 220px;
        min-width: 220px;
        margin-right: var(--client-logo-gap);
       /* border: 1px solid rgba(13, 110, 253, 0.08);*/
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.96);
       /* box-shadow: 0 10px 24px rgba(15, 23, 42, 0.06); */
        padding: 1.25rem 1rem;
        text-align: center;
    }
    .client-logo-frame {
        height: calc(var(--client-logo-height) + 10px);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 0.75rem;
    }
    .client-logo-frame img {
        max-height: var(--client-logo-height);
        width: auto;
        max-width: 100%;
        object-fit: contain;
        display: block;
    }
    .client-logo-frame img.logo-darken {
        filter: brightness(0.42) contrast(1.22) saturate(0.85);
    }
    .client-logo-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.2rem;
    }
    .client-logo-url {
        font-size: 0.78rem;
        color: #6b7280;
    }
    @keyframes clients-scroll {
        from {
            transform: translateX(0);
        }
        to {
            transform: translateX(-50%);
        }
    }
    @media (max-width: 767.98px) {
        .client-logo-card {
            width: 180px;
            min-width: 180px;
        }
        .clients-carousel-track {
            animation-duration: 34s;
        }
    }
</style>

@php
    $clientLogos = [
        ['name' => 'Indian Art Zone', 'url' => 'https://indianartzone.com', 'logo' => asset('images/clients/indian-art-zone.png')],
        ['name' => 'Vedic International', 'url' => 'https://vedic.ac.in', 'logo' => asset('images/clients/vedic-international.png')],
        ['name' => 'Dr Instruments', 'url' => 'https://drinstruments.com', 'logo' => asset('images/clients/dr-instruments.png')],
        ['name' => 'Powerup Links', 'url' => 'https://poweruplinks.com', 'logo' => asset('images/clients/powerup-links.png')],
        ['name' => 'FloorPlan Expert', 'url' => 'https://www.floorplanexpert.co.uk', 'logo' => asset('images/clients/floorplan-expert.png')],
        ['name' => 'Odyssey Motors', 'url' => 'https://odysseymotors.com', 'logo' => asset('images/clients/odyssey-motors.png')],
        ['name' => 'Gupta Diagnostic', 'url' => 'https://guptadiagnostic.com', 'logo' => asset('images/clients/gupta-diagnostic.png'), 'class' => 'logo-darken'],
        ['name' => 'Gurunanak Public School', 'url' => 'https://gurunanakpublicschool.org.in', 'logo' => asset('images/clients/gurunanak-public-school.gif')],
        ['name' => 'SIDI', 'url' => 'https://sidi.org.in', 'logo' => asset('images/clients/sidi.png')],
        ['name' => 'ADARSA', 'url' => 'https://adarsa.org', 'logo' => asset('images/clients/adarsa.png')],
    ];
@endphp

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

    <!-- Key Benefits Banner -->
    <section class="py-3" style="background: #f8f9fa;">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-4">
                    <i class="fas fa-robot text-primary fa-2x mb-2"></i>
                    <h6>AI Chat on Your Website</h6>
                    <small class="text-muted">Instant answers for every visitor</small>
                </div>
                <div class="col-md-4">
                    <i class="fab fa-whatsapp text-success fa-2x mb-2"></i>
                    <h6>WhatsApp Automation</h6>
                    <small class="text-muted">Reply to customers automatically</small>
                </div>
                <div class="col-md-4">
                    <i class="fas fa-clock text-info fa-2x mb-2"></i>
                    <h6>Never Miss a Lead</h6>
                    <small class="text-muted">Available 24/7, even when you sleep</small>
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
                <div class="alert alert-info mt-3 mb-0">
                    <strong>Shopify merchants:</strong> App subscriptions are managed through Shopify Billing inside the app dashboard. Website checkout options are for non-Shopify customers.
                </div>
                
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
                        $rawPlans = App\Models\PricingPlan::active()
                            ->subscriptions()
                            ->orderBy('sort_order')
                            ->orderBy('billing_period')
                            ->get();

                        $grouped = [];
                        foreach ($rawPlans as $plan) {
                            $meta = is_array($plan->metadata) ? $plan->metadata : [];
                            $baseSlug = $meta['original_slug'] ?? $plan->slug;
                            $key = $baseSlug ?: $plan->name;

                            if (!isset($grouped[$key])) {
                                $tokenCap = (int) ($plan->token_cap ?? 0);
                                $grouped[$key] = (object) [
                                    'id' => null,
                                    'monthly_id' => null,
                                    'yearly_id' => null,
                                    'one_time_id' => null,
                                    'name' => $plan->name,
                                    'description' => $plan->description,
                                    'slug' => $baseSlug ?: $plan->slug,
                                    'monthly_price' => null,
                                    'yearly_price' => null,
                                    'one_time_price' => null,
                                    'token_cap_monthly' => $tokenCap,
                                    'overage_price_per_100k' => $plan->overage_price_per_100k,
                                    'features' => $meta['features'] ?? [],
                                    'formatted_token_cap' => \App\Models\PricingPlan::formatTokenCap($tokenCap),
                                ];
                            }

                            if ($plan->billing_period === 'monthly') {
                                $grouped[$key]->id = $plan->id;
                                $grouped[$key]->monthly_id = $plan->id;
                                $grouped[$key]->monthly_price = $plan->price;
                            }
                            if ($plan->billing_period === 'yearly') {
                                if (!$grouped[$key]->id) {
                                    $grouped[$key]->id = $plan->id;
                                }
                                $grouped[$key]->yearly_id = $plan->id;
                                $grouped[$key]->yearly_price = $plan->price;
                            }
                            if ($plan->billing_period === 'one_time') {
                                if (!$grouped[$key]->id) {
                                    $grouped[$key]->id = $plan->id;
                                }
                                $grouped[$key]->one_time_id = $plan->id;
                                $grouped[$key]->one_time_price = $plan->price;
                                if ($grouped[$key]->monthly_price === null) {
                                    $grouped[$key]->monthly_price = $plan->price;
                                }
                            }
                        }

                        $plans = collect(array_values($grouped));
                    } catch (\Throwable $e) {
                        $plans = collect();
                    }
                    $locationService = app()->bound(\App\Services\LocationService::class) ? app(\App\Services\LocationService::class) : null;
                    $isFromIndia = $locationService && method_exists($locationService, 'isFromIndia')
                        ? (bool) $locationService->isFromIndia()
                        : false;

                    if (!$isFromIndia) {
                        $isFromIndia = request()->header('CF-IPCountry') === 'IN'
                            || str_contains(request()->ip(), '127.')
                            || str_contains(request()->ip(), '192.168.')
                            || in_array(request()->ip(), ['::1', '127.0.0.1']);
                    }

                    $currency = $isFromIndia ? 'INR' : 'USD';
                @endphp

                @php if(!isset($plans) || !($plans instanceof \Illuminate\Support\Collection)) { $plans = collect(); } @endphp
                @forelse($plans as $plan)
                    @php
                        $titleKey = 'common.plan_' . $plan->slug . '_title';
                        $descKey = 'common.plan_' . $plan->slug . '_desc';
                        $translatedTitle = __($titleKey);
                        $translatedDesc = __($descKey);
                        $planTitle = $translatedTitle === $titleKey ? $plan->name : $translatedTitle;
                        $planDesc = $translatedDesc === $descKey ? $plan->description : $translatedDesc;
                        $buttonKey = 'common.plan_' . $plan->slug . '_button';
                        $translatedButton = __($buttonKey);
                        $planButton = $translatedButton === $buttonKey ? 'Get Started' : $translatedButton;
                        $isShopifyUser = auth()->check() && auth()->user()->organizations()
                            ->whereHas('integrations', function($q) {
                                $q->where('provider', 'shopify')->where('active', true);
                            })
                            ->exists();
                        $subscriptionPlanId = $plan->monthly_id ?: $plan->id;
                        $oneTimePlanId = $plan->one_time_id ?: $plan->id;
                    @endphp
                    <div class="col-lg-3 col-md-6 mb-4">
                        <div class="card h-100 {{ $plan->slug === 'pro' ? 'border-primary' : '' }}">
                            @if($plan->slug === 'pro')
                                <div class="card-header bg-primary text-white text-center">
                                    <span class="badge bg-warning text-dark">{{ __('common.most_popular') }}</span>
                                </div>
                            @endif
                            <div class="card-body text-center">
                                <h4 class="card-title">{{ $planTitle }}</h4>
                                <div class="price-section mb-3">
                                    @php
                                        $currencySymbol = $currency === 'INR' ? '₹' : '$';
                                    @endphp
                                    @if((float) $plan->monthly_price > 0)
                                        <div class="monthly-price price-display" data-cycle="monthly">
                                            @php
                                                $monthlyPrice = $plan->monthly_price;
                                                if ($currency === 'INR' && $locationService) {
                                                    $monthlyPrice = $locationService->convertToINR($monthlyPrice);
                                                }
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
                                                $yearlyPrice = $plan->yearly_price;
                                                if ($currency === 'INR' && $locationService) {
                                                    $yearlyPrice = $locationService->convertToINR($yearlyPrice);
                                                }
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
                                    @elseif($plan->slug === 'free')
                                        <div class="h3 text-success">Free</div>
                                        <small class="text-muted">20K one-time tokens (valid 1 month)</small>
                                    @else
                                        <div class="h3">Custom</div>
                                        @php
                                            $customPrice = $plan->monthly_price;
                                            if ($currency === 'INR' && $locationService) {
                                                $customPrice = $locationService->convertToINR($customPrice);
                                            }
                                        @endphp
                                        <small class="text-muted">Starting ~{{ $currencySymbol }}{{ number_format($customPrice, 0) }}</small>
                                    @endif
                                </div>
                                
                                <p class="text-muted">{{ $planDesc }}</p>
                                
                                <div class="mb-3">
                                    @if($plan->slug === 'free' && $plan->token_cap_monthly > 0)
                                        <strong>{{ $plan->formatted_token_cap }} one-time tokens</strong>
                                    @elseif($plan->token_cap_monthly > 0)
                                        <strong>{{ $plan->formatted_token_cap }} tokens/month</strong>
                                    @else
                                        <strong>No usage cap</strong>
                                    @endif
                                    <br>
                                    <small class="text-muted">
                                        @php
                                            $overagePrice = $plan->overage_price_per_100k;
                                            if ($currency === 'INR' && $locationService) {
                                                $overagePrice = $locationService->convertToINR($overagePrice);
                                            }
                                        @endphp
                                        Overage: {{ $currencySymbol }}{{ number_format($overagePrice, 0) }} per 100k tokens
                                    </small>
                                </div>

                                <ul class="list-unstyled text-start">
                                    @foreach($plan->features as $feature)
                                        @php
                                            $featureKey = 'common.plan_' . $plan->slug . '_feature_' . Str::slug($feature, '_');
                                            $translatedFeature = __($featureKey);
                                        @endphp
                                        <li class="mb-2 d-flex align-items-start">
                                            <i class="fas fa-check text-success me-2 mt-1 flex-shrink-0"></i>
                                            <span>{{ $translatedFeature === $featureKey ? $feature : $translatedFeature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="card-footer">
                                @guest
                                    <a href="{{ route('register', ['plan' => $plan->slug]) }}" class="btn {{ $plan->slug === 'pro' ? 'btn-primary' : 'btn-outline-primary' }} btn-block w-100">
                                        {{ $planButton }}
                                    </a>
                                @else
                                    @if($isShopifyUser && $plan->slug !== 'free')
                                        <a href="{{ route('customer.subscription') }}" class="btn btn-outline-primary btn-block w-100">
                                            Manage Plan in Shopify Dashboard
                                        </a>
                                    @elseif($plan->slug === 'free')
                                        <a href="{{ route('customer.subscription') }}" class="btn btn-outline-success btn-block w-100">
                                            {{ __('common.plan_free_button') }}
                                        </a>
                                    @elseif($plan->slug === 'enterprise')
                                        <a href="mailto:sales@ai-chat.support" class="btn btn-outline-primary btn-block w-100">
                                            {{ __('common.plan_enterprise_button') }}
                                        </a>
                                    @elseif($plan->slug === 'payg')
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia)
                                                <a href="{{ route('razorpay.create-onetime-direct', ['planId' => $oneTimePlanId, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="{{ route('paypal.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="{{ route('razorpay.create-onetime-direct', ['planId' => $oneTimePlanId, 'cycle' => 'monthly']) }}" 
                                                   class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        @if($subscriptionPlanId)
                                            <div class="btn-group-vertical w-100">
                                                @if($isFromIndia)
                                                    <a href="{{ route('razorpay.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                       class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                        <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                    </a>
                                                    <a href="{{ route('paypal.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                       class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                        <i class="fab fa-paypal"></i> Pay with PayPal
                                                    </a>
                                                @else
                                                    <a href="{{ route('paypal.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                       class="btn btn-primary mb-2 payment-btn" data-plan-id="{{ $plan->id }}" data-provider="paypal">
                                                        <i class="fab fa-paypal"></i> Pay with PayPal
                                                    </a>
                                                    <a href="{{ route('razorpay.create-subscription-direct', ['planId' => $subscriptionPlanId, 'cycle' => 'monthly']) }}" 
                                                       class="btn btn-outline-primary payment-btn" data-plan-id="{{ $plan->id }}" data-provider="razorpay">
                                                        <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                    </a>
                                                @endif
                                            </div>
                                        @else
                                            <a href="{{ route('customer.subscription') }}" class="btn btn-outline-primary btn-block w-100">
                                                Manage Subscription
                                            </a>
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

    <section class="py-5 clients-section" id="our-clients">
        <div class="container">
            <div class="text-center mb-4">
                <div class="clients-kicker mb-2">Our Clients</div>
                <h2 class="mb-2">Trusted by teams across healthcare, education, ecommerce, and services</h2>
                
            </div>
        </div>
        <div class="clients-carousel-shell">
            <div class="clients-carousel-track">
                @foreach(array_merge($clientLogos, $clientLogos) as $client)
                    <div class="client-logo-card">
                        <div class="client-logo-frame">
                            <img src="{{ $client['logo'] }}" alt="{{ $client['name'] }} logo" loading="lazy" class="{{ $client['class'] ?? '' }}">
                        </div>
                        <div class="client-logo-name">{{ $client['name'] }}</div>
                        <div class="client-logo-url">{{ parse_url($client['url'], PHP_URL_HOST) }}</div>
                    </div>
                @endforeach
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

    <!-- SEO Content Section -->
    <section class="py-5" style="background: #f8f9fa;">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="text-center mb-4">Turn Your Website and WhatsApp Into a 24/7 Sales Team</h2>
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5><i class="fas fa-robot text-primary me-2"></i>AI Chat on Your Website</h5>
                                    <p class="text-muted mb-0">Answer customer questions instantly, capture leads while they're interested, and provide support even when your team is offline.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body">
                                    <h5><i class="fab fa-whatsapp text-success me-2"></i>WhatsApp Automation</h5>
                                    <p class="text-muted mb-0">Reply to WhatsApp messages automatically, send appointment reminders, and follow up with leads — all without lifting a finger.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded shadow-sm mb-4">
                        <h4 class="mb-3">Why Businesses Choose Our AI Chat</h4>
                        <ul class="list-unstyled">
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i><strong>Cut support costs by up to 70%</strong><br><small class="text-muted">Your AI handles routine questions so your team focuses on real sales</small></li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i><strong>24/7 availability</strong><br><small class="text-muted">Customers get instant answers even when your office is closed</small></li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i><strong>Works on website + WhatsApp</strong><br><small class="text-muted">One AI assistant for all your channels</small></li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i><strong>More leads, less manual work</strong><br><small class="text-muted">The bot qualifies visitors before you call them</small></li>
                            <li class="mb-3"><i class="fas fa-check-circle text-success me-2"></i><strong>Easy setup, no coding required</strong><br><small class="text-muted">Start chatting with customers in under 10 minutes</small></li>
                        </ul>
                    </div>

                    <div class="row">
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3">
                                <i class="fas fa-store text-info fa-3x mb-2"></i>
                                <h6>Ecommerce</h6>
                                <small class="text-muted">Answer product questions and recover abandoned carts</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3">
                                <i class="fas fa-hospital text-danger fa-3x mb-2"></i>
                                <h6>Healthcare</h6>
                                <small class="text-muted">Automate appointment booking and reminders</small>
                            </div>
                        </div>
                        <div class="col-md-4 text-center mb-3">
                            <div class="p-3">
                                <i class="fas fa-graduation-cap text-warning fa-3x mb-2"></i>
                                <h6>Education</h6>
                                <small class="text-muted">Answer admissions questions 24/7</small>
                            </div>
                        </div>
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
        "@type": "SoftwareApplication",
        "name": "AI Chat Support",
        "applicationCategory": "BusinessApplication",
        "operatingSystem": "Web Browser",
        "url": "{{ url('/') }}",
        "logo": "{{ asset('images/logo.png') }}",
        "description": "AI-powered customer support automation platform providing 24/7 intelligent chatbot assistance for businesses. Includes live chat, multilingual support, and seamless website integration.",
        "offers": [
            {
                "@type": "Offer",
                "name": "Basic Monthly Subscription",
                "description": "Perfect for individuals and small projects testing AI chat support",
                "price": "15.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "15.00",
                    "priceCurrency": "USD",
                    "billingDuration": "P1M",
                    "unitText": "500,000 tokens per month"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/register') }}"
            },
            {
                "@type": "Offer",
                "name": "Starter Monthly Subscription",
                "description": "Perfect for small businesses getting started with AI chat",
                "price": "49.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "49.00",
                    "priceCurrency": "USD",
                    "billingDuration": "P1M",
                    "unitText": "2 million tokens per month"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/register') }}"
            },
            {
                "@type": "Offer",
                "name": "Business Monthly Subscription",
                "description": "Advanced features for established businesses",
                "price": "199.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "199.00",
                    "priceCurrency": "USD",
                    "billingDuration": "P1M",
                    "unitText": "10 million tokens per month"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/register') }}"
            },
            {
                "@type": "Offer",
                "name": "Starter Credits Package",
                "description": "One-time credit purchase for occasional usage",
                "price": "19.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "19.00",
                    "priceCurrency": "USD",
                    "unitText": "500,000 tokens - valid for 12 months"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/credits-and-services') }}"
            },
            {
                "@type": "Offer",
                "name": "Basic Credits Package",
                "description": "Perfect for occasional usage with 12-month validity",
                "price": "69.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "69.00",
                    "priceCurrency": "USD",
                    "unitText": "2 million tokens - valid for 12 months"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/credits-and-services') }}"
            },
            {
                "@type": "Offer",
                "name": "Standard Credits Package",
                "description": "Great value for regular usage with 12-month validity",
                "price": "129.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "129.00",
                    "priceCurrency": "USD",
                    "unitText": "4 million tokens - valid for 12 months"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/credits-and-services') }}"
            },
            {
                "@type": "Offer",
                "name": "Premium Credits Package",
                "description": "Best value for heavy users with maximum flexibility",
                "price": "299.00",
                "priceCurrency": "USD",
                "priceSpecification": {
                    "@type": "UnitPriceSpecification",
                    "price": "299.00",
                    "priceCurrency": "USD",
                    "unitText": "5 million tokens - valid for 12 months"
                },
                "availability": "https://schema.org/InStock",
                "url": "{{ url('/credits-and-services') }}"
            }
        ],
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": "4.8",
            "reviewCount": "127",
            "bestRating": "5",
            "worstRating": "1"
        },
        "provider": {
            "@type": "Organization",
            "name": "MYWEB SOLUTIONS",
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
                "availableLanguage": ["en", "hi"],
                "telephone": "+91-XXX-XXX-XXXX"
            }
        }
    }
    </script>
@endsection