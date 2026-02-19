<div class="text-center mt-3">
    <small>ai-chat.support is owned and operated by MYWEB SOLUTIONS.</small>
</div>
<!-- Footer -->
<footer class="section-padding">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="mb-3">
                    <img src="{{ asset('images/ai-chat-logo-sm.png') }}" alt="AI Chat Support" style="height: 50px; width: auto;">
                </div>
                <p class="text-light">
                    {{ __('common.hero_intro') }}
                    {{ __('common.hero_sub') }}
                </p>
                <div class="social-links">
                    <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-github"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 col-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.product') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('home') }}#features">{{ __('marketing.features') }}</a></li>
                    <li><a href="{{ route('home') }}#pricing">{{ __('marketing.pricing') }}</a></li>
                    <li><a href="{{ route('integrations') }}">{{ __('marketing.integration') }}</a></li>
                    <li><a href="#">{{ __('marketing.api_docs') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 col-6 mb-4">
                <h6 class="fw-bold mb-3">Solutions</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('solutions.healthcare') }}">Healthcare AI Chatbot</a></li>
                    <li><a href="{{ route('solutions.ecommerce') }}">Ecommerce Chatbot</a></li>
                    <li><a href="{{ route('solutions.education') }}">Education AI Support</a></li>
                    <li><a href="{{ route('integrations') }}">WhatsApp Automation</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 col-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.company') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('about') }}">{{ __('common.about_us') }}</a></li>
                    <li><a href="{{ route('blog.index') }}">{{ __('common.blog') }}</a></li>
                    <li><a href="{{ route('affiliate.register') }}">Become an Affiliate</a></li>
                    <li><a href="#">{{ __('common.careers') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('common.contact') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 col-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.support') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="#">{{ __('marketing.help_center') }}</a></li>
                    <li><a href="#">{{ __('marketing.documentation') }}</a></li>
                    <li><a href="#">{{ __('marketing.community') }}</a></li>
                    <li><a href="#">{{ __('marketing.status') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 col-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.legal') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('privacy') }}">{{ __('marketing.privacy') }}</a></li>
                    <li><a href="{{ route('terms') }}">{{ __('marketing.terms') }}</a></li>
                    <li><a href="{{ route('refund-policy') }}">{{ __('marketing.refund') }}</a></li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 border-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0">&copy; {{ date('Y') }} AI Chat Support. {{ __('marketing.all_rights') }}</p>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted me-3">Villa No.10, Sriram Villa, AN Guha Lane, Sambalpur - 768001</span>
            </div>
        </div>
    </div>
</footer>
<!-- AI Chat Widget for Organization: ai-chat-support -->
<script>
(function() {
    var script = document.createElement('script');
    script.src = 'https://ai-chat.support/widget/ai-chat-support/script.js?v=' + Date.now();
    script.async = true;
    document.head.appendChild(script);
})();
</script>
