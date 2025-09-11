@extends('layouts.app')

@section('title', 'Credits & Services')

@section('content')
<div class="container mt-5">
    <!-- Page Header -->
    <div class="text-center mb-5">
        <h1 class="display-4 mb-3">Credits & Professional Services</h1>
        <p class="lead text-muted">
            Flexible credit packages that never expire and professional services to get you up and running faster
        </p>
    </div>

    <div class="row">
        <!-- Credit Packages -->
        <div class="col-lg-8">
            <div class="card mb-5">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-coins"></i> Credit Packages (No Expiration)
                    </h3>
                    <small>Pay once, use anytime - Credits never expire!</small>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Small Credit Package -->
                        <div class="col-md-4">
                            <div class="card border-success h-100">
                                <div class="card-header bg-light">
                                    <h5 class="text-center">Basic Credits</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <span class="h3 text-success">₹2,500</span>
                                        <div><small class="text-muted">$29</small></div>
                                    </div>
                                    <ul class="list-unstyled mb-4">
                                        <li><strong>1M tokens</strong></li>
                                        <li>✓ Never expires</li>
                                        <li>✓ Pay once, use anytime</li>
                                        <li>✓ Perfect for small projects</li>
                                    </ul>
                                    @auth
                                        @php 
                                            $isFromIndia = request()->header('CF-IPCountry') === 'IN' || 
                                                          str_contains(request()->ip(), '127.') ||
                                                          str_contains(request()->ip(), '192.168.') ||
                                                          in_array(request()->ip(), ['::1', '127.0.0.1']);
                                        @endphp
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia)
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-success mb-2">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-success">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-success mb-2">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-success">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <a href="{{ route('login') }}" class="btn btn-success btn-block">
                                            Login to Purchase
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>

                        <!-- Medium Credit Package -->
                        <div class="col-md-4">
                            <div class="card border-primary h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="text-center">Popular Credits</h5>
                                    <div class="text-center">
                                        <span class="badge badge-warning">Best Value</span>
                                    </div>
                                </div>
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <span class="h3 text-primary">₹4,900</span>
                                        <div><small class="text-muted">$59</small></div>
                                    </div>
                                    <ul class="list-unstyled mb-4">
                                        <li><strong>2.5M tokens</strong></li>
                                        <li>✓ Never expires</li>
                                        <li>✓ 25% more tokens</li>
                                        <li>✓ Priority support</li>
                                    </ul>
                                    @auth
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia ?? true)
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-primary mb-2">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-primary">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-primary mb-2">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-primary">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <a href="{{ route('login') }}" class="btn btn-primary btn-block">
                                            Login to Purchase
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>

                        <!-- Large Credit Package -->
                        <div class="col-md-4">
                            <div class="card border-info h-100">
                                <div class="card-header bg-light">
                                    <h5 class="text-center">Enterprise Credits</h5>
                                </div>
                                <div class="card-body text-center">
                                    <div class="mb-3">
                                        <span class="h3 text-info">₹9,800</span>
                                        <div><small class="text-muted">$118</small></div>
                                    </div>
                                    <ul class="list-unstyled mb-4">
                                        <li><strong>6M tokens</strong></li>
                                        <li>✓ Never expires</li>
                                        <li>✓ 50% more tokens</li>
                                        <li>✓ Dedicated support</li>
                                    </ul>
                                    @auth
                                        <div class="btn-group-vertical w-100">
                                            @if($isFromIndia ?? true)
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-info mb-2">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-info">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                            @else
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-info mb-2">
                                                    <i class="fab fa-paypal"></i> Pay with PayPal
                                                </a>
                                                <a href="#" onclick="alert('Credit packages coming soon!')" class="btn btn-outline-info">
                                                    <i class="fas fa-credit-card"></i> Pay with Razorpay
                                                </a>
                                            @endif
                                        </div>
                                    @else
                                        <a href="{{ route('login') }}" class="btn btn-info btn-block">
                                            Login to Purchase
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Services -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h3 class="mb-0">
                        <i class="fas fa-concierge-bell"></i> Professional Services
                    </h3>
                    <small>Let our experts help you succeed faster</small>
                </div>
                <div class="card-body">
                    <!-- Data Setup Service -->
                    <div class="service-item mb-4 p-3 border rounded">
                        <h5><i class="fas fa-database"></i> Data Setup & Integration</h5>
                        <p class="text-muted small">
                            Send us your data and we'll integrate it into your AI system, including initial training and optimization.
                        </p>
                        <div class="price mb-2">
                            <span class="h5 text-warning">₹4,200</span>
                            <small class="text-muted">($50)</small>
                        </div>
                        <ul class="list-unstyled small">
                            <li>✓ Data entry & formatting</li>
                            <li>✓ System integration</li>
                            <li>✓ Initial training setup</li>
                            <li>✓ Quality assurance</li>
                        </ul>
                        @auth
                            <a href="mailto:support@ai-chat.support?subject=Data Setup Service Request" 
                               class="btn btn-warning btn-sm btn-block">
                                Request Service
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-warning btn-sm btn-block">
                                Login to Request
                            </a>
                        @endauth
                    </div>

                    <!-- Monitoring Service -->
                    <div class="service-item mb-4 p-3 border rounded">
                        <h5><i class="fas fa-chart-line"></i> Ongoing Data Monitoring</h5>
                        <p class="text-muted small">
                            We monitor your chat interactions and continuously improve your AI with new training data.
                        </p>
                        <div class="price mb-2">
                            <span class="h5 text-info">₹2,100/month</span>
                            <small class="text-muted">($25/month)</small>
                        </div>
                        <ul class="list-unstyled small">
                            <li>✓ Chat monitoring</li>
                            <li>✓ Regular training updates</li>
                            <li>✓ Performance optimization</li>
                            <li>✓ Monthly reports</li>
                        </ul>
                        @auth
                            <a href="mailto:support@ai-chat.support?subject=Monitoring Service Request" 
                               class="btn btn-info btn-sm btn-block">
                                Subscribe Now
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-info btn-sm btn-block">
                                Login to Subscribe
                            </a>
                        @endauth
                    </div>

                    <!-- WhatsApp Integration -->
                    <div class="service-item mb-4 p-3 border rounded">
                        <h5><i class="fab fa-whatsapp"></i> WhatsApp Integration</h5>
                        <p class="text-muted small">
                            Connect your AI chatbot directly to WhatsApp for seamless customer communication.
                        </p>
                        <div class="price mb-2">
                            <span class="h5 text-success">₹4,200</span>
                            <small class="text-muted">($50)</small>
                        </div>
                        <ul class="list-unstyled small">
                            <li>✓ WhatsApp Business API setup</li>
                            <li>✓ Bot integration</li>
                            <li>✓ Message routing</li>
                            <li>✓ Testing & deployment</li>
                        </ul>
                        @auth
                            <a href="mailto:support@ai-chat.support?subject=WhatsApp Integration Request" 
                               class="btn btn-success btn-sm btn-block">
                                Get Started
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-success btn-sm btn-block">
                                Login to Get Started
                            </a>
                        @endauth
                    </div>

                    <!-- Custom Integration -->
                    <div class="service-item p-3 border rounded">
                        <h5><i class="fas fa-cogs"></i> Custom Integration</h5>
                        <p class="text-muted small">
                            Need something specific? We offer custom integrations tailored to your business needs.
                        </p>
                        <div class="price mb-2">
                            <span class="h5 text-primary">Custom Quote</span>
                        </div>
                        <ul class="list-unstyled small">
                            <li>✓ Custom API development</li>
                            <li>✓ Third-party integrations</li>
                            <li>✓ Workflow automation</li>
                            <li>✓ Dedicated support</li>
                        </ul>
                        @auth
                            <a href="mailto:support@ai-chat.support?subject=Custom Integration Request" 
                               class="btn btn-primary btn-sm btn-block">
                                Get Quote
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-sm btn-block">
                                Login for Quote
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Information -->
    <div class="row mt-5">
        <div class="col-12">
            <div class="card bg-light">
                <div class="card-body text-center">
                    <h4>Why Choose Our Credits & Services?</h4>
                    <div class="row mt-4">
                        <div class="col-md-3">
                            <i class="fas fa-infinity fa-3x text-primary mb-3"></i>
                            <h6>Never Expire</h6>
                            <small class="text-muted">Credits remain valid forever</small>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-users fa-3x text-success mb-3"></i>
                            <h6>Expert Support</h6>
                            <small class="text-muted">Professional team to help you succeed</small>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-rocket fa-3x text-warning mb-3"></i>
                            <h6>Fast Setup</h6>
                            <small class="text-muted">Get up and running quickly</small>
                        </div>
                        <div class="col-md-3">
                            <i class="fas fa-shield-alt fa-3x text-info mb-3"></i>
                            <h6>Secure & Reliable</h6>
                            <small class="text-muted">Enterprise-grade security</small>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('contact') }}" class="btn btn-outline-primary">
                            Have Questions? Contact Us
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
