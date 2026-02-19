@extends('layouts.public')

@section('title', 'AI Chatbot for Ecommerce | AI Shopping Assistant for Online Stores')
@section('description', 'Boost ecommerce sales with AI chatbots. Get 24/7 product recommendations, order tracking, and customer support. Perfect for Shopify, WooCommerce, and Magento stores with WhatsApp automation for lead follow-up.')
@section('keywords', 'AI chatbot for ecommerce, online store chatbot, shopping assistant AI, ecommerce automation, product recommendations, Shopify chatbot, WooCommerce AI, WhatsApp ecommerce, automated lead generation, ecommerce customer support')

@section('og_title', 'AI Chatbot for Ecommerce - Increase Sales & Customer Satisfaction')
@section('og_description', 'Automate customer service, product discovery, and order support with intelligent AI chatbots designed for online stores.')

@section('content')
<style>
    .hero-ecommerce {
        background: linear-gradient(135deg, #FF6B6B 0%, #E85D75 100%);
        color: white;
        padding: 100px 0 80px;
    }
    .feature-icon {
        font-size: 3rem;
        color: #FF6B6B;
        margin-bottom: 1.5rem;
    }
    .stats-section {
        background: #f8f9fa;
        padding: 60px 0;
    }
    .benefit-card {
        border-left: 4px solid #FF6B6B;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
</style>

<!-- Hero Section -->
<section class="hero-ecommerce">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 fw-bold mb-4">AI Chatbot for Ecommerce Stores</h1>
                <p class="lead mb-4">Boost conversions, reduce cart abandonment, and provide instant customer support with AI-powered shopping assistants.</p>
                <div class="mb-4">
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">Shopify Integration</span>
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">WooCommerce</span>
                    <span class="badge bg-light text-dark me-2 mb-2 fs-6">Magento</span>
                </div>
                <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
                <a href="{{ route('integrations') }}" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-plug me-2"></i>View Integrations
                </a>
            </div>
            <div class="col-lg-6 text-center">
                <i class="fas fa-shopping-cart" style="font-size: 15rem; opacity: 0.2;"></i>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="container">
        <div class="row text-center">
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-danger">35%</h2>
                <p class="text-muted">Increase in conversions</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-danger">67%</h2>
                <p class="text-muted">Reduction in cart abandonment</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-danger">24/7</h2>
                <p class="text-muted">Customer support coverage</p>
            </div>
            <div class="col-md-3 mb-4">
                <h2 class="display-4 fw-bold text-danger">4x</h2>
                <p class="text-muted">Faster response times</p>
            </div>
        </div>
    </div>
</section>

<!-- Key Features -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Powerful Ecommerce AI Features</h2>
            <p class="lead text-muted">Everything you need to create exceptional shopping experiences</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-shopping-bag"></i></div>
                <h3>Product Recommendations</h3>
                <p class="text-muted">AI-powered product suggestions based on customer preferences, browsing history, and purchase patterns to increase average order value.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-search"></i></div>
                <h3>Smart Product Search</h3>
                <p class="text-muted">Help customers find exactly what they're looking for with natural language search and intelligent filtering across your entire catalog.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-truck"></i></div>
                <h3>Order Tracking</h3>
                <p class="text-muted">Automated order status updates, shipping information, and delivery tracking without human intervention.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-tags"></i></div>
                <h3>Promotions & Discounts</h3>
                <p class="text-muted">Automatically inform customers about ongoing sales, coupon codes, bundle deals, and personalized offers.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-undo"></i></div>
                <h3>Returns & Refunds</h3>
                <p class="text-muted">Guide customers through return processes, refund policies, and exchange procedures with automated workflows.</p>
            </div>
            <div class="col-md-4 text-center">
                <div class="feature-icon"><i class="fas fa-shopping-cart"></i></div>
                <h3>Cart Recovery</h3>
                <p class="text-muted">Proactively engage customers who abandon carts with personalized messages and incentives to complete purchases.</p>
            </div>
        </div>
    </div>
</section>

<!-- Benefits -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="display-5 fw-bold text-center mb-5">Why Ecommerce Brands Love Our AI Chatbot</h2>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="benefit-card">
                    <h4><i class="fas fa-chart-line text-success me-2"></i>Increase Sales Revenue</h4>
                    <p class="mb-0">Guide customers to the right products, upsell complementary items, and reduce decision fatigue to boost conversions by up to 35%.</p>
                </div>
                
                <div class="benefit-card">
                    <h4><i class="fas fa-clock text-success me-2"></i>24/7 Customer Support</h4>
                    <p class="mb-0">Never miss a sale. Provide instant answers to product questions, sizing, shipping, and policies at any time of day or night.</p>
                </div>
                
                <div class="benefit-card">
                    <h4><i class="fas fa-globe text-success me-2"></i>Multilingual Support</h4>
                    <p class="mb-0">Serve international customers in their native language with AI chat supporting 50+ languages automatically.</p>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="benefit-card">
                    <h4><i class="fas fa-mobile-alt text-success me-2"></i>Mobile-Optimized</h4>
                    <p class="mb-0">Perfect shopping experience on smartphones and tablets where 70% of ecommerce traffic happens.</p>
                </div>
                
                <div class="benefit-card">
                    <h4><i class="fas fa-database text-success me-2"></i>Product Catalog Sync</h4>
                    <p class="mb-0">Automatically sync product information, pricing, inventory, and availability from your store's database.</p>
                </div>
                
                <div class="benefit-card">
                    <h4><i class="fas fa-chart-pie text-success me-2"></i>Analytics & Insights</h4>
                    <p class="mb-0">Track customer questions, identify product gaps, and optimize your store based on AI chat interactions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Platform Integration -->
<section class="py-5">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold">Works With Your Ecommerce Platform</h2>
            <p class="lead text-muted">Seamless integration with leading ecommerce solutions</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4 text-center">
                <div class="p-4 bg-light rounded">
                    <i class="fab fa-shopify fa-4x mb-3" style="color: #96bf48;"></i>
                    <h4>Shopify</h4>
                    <p class="text-muted">One-click installation. Sync products, orders, and customers automatically.</p>
                    <a href="{{ route('integrations') }}" class="btn btn-outline-primary">Learn More</a>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="p-4 bg-light rounded">
                    <i class="fab fa-wordpress fa-4x mb-3" style="color: #21759b;"></i>
                    <h4>WooCommerce</h4>
                    <p class="text-muted">WordPress plugin with full WooCommerce product catalog integration.</p>
                    <a href="{{ route('integrations') }}" class="btn btn-outline-primary">Learn More</a>
                </div>
            </div>
            <div class="col-md-4 text-center">
                <div class="p-4 bg-light rounded">
                    <i class="fab fa-magento fa-4x mb-3" style="color: #f46f25;"></i>
                    <h4>Magento</h4>
                    <p class="text-muted">Native Magento 2 extension with real-time product sync.</p>
                    <a href="{{ route('integrations') }}" class="btn btn-outline-primary">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-5 bg-danger text-white">
    <div class="container text-center">
        <h2 class="display-5 fw-bold mb-4">Ready to Boost Your Online Sales?</h2>
        <p class="lead mb-4">Join thousands of ecommerce stores using AI chatbots to increase revenue</p>
        <a href="{{ route('register') }}" class="btn btn-light btn-lg me-3">
            <i class="fas fa-rocket me-2"></i>Start Free Trial
        </a>
        <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">
            <i class="fas fa-comments me-2"></i>Talk to Ecommerce Expert
        </a>
    </div>
</section>
@endsection
