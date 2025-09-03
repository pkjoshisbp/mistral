<!-- Footer -->
<footer class="section-padding">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="text-gradient mb-3">
                    <i class="fas fa-robot me-2"></i>AI Chat Support
                </h5>
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
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.product') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('home') }}#features">{{ __('marketing.features') }}</a></li>
                    <li><a href="{{ route('home') }}#pricing">{{ __('marketing.pricing') }}</a></li>
                    <li><a href="#">{{ __('marketing.integration') }}</a></li>
                    <li><a href="#">{{ __('marketing.api_docs') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.company') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('about') }}">{{ __('common.about_us') }}</a></li>
                    <li><a href="{{ route('blog.index') }}">{{ __('common.blog') }}</a></li>
                    <li><a href="#">{{ __('common.careers') }}</a></li>
                    <li><a href="{{ route('contact') }}">{{ __('common.contact') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">{{ __('marketing.support') }}</h6>
                <ul class="list-unstyled">
                    <li><a href="#">{{ __('marketing.help_center') }}</a></li>
                    <li><a href="#">{{ __('marketing.documentation') }}</a></li>
                    <li><a href="#">{{ __('marketing.community') }}</a></li>
                    <li><a href="#">{{ __('marketing.status') }}</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
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
<!-- AI Chat Widget for Organization ID: 3 -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Footer: DOM loaded, initializing chat widget...');
        
        // Add debug information
        console.log('Current URL:', window.location.href);
        console.log('Auth meta tag:', document.querySelector('meta[name="user-authenticated"]'));
        
        // Load the AI chat widget script from local domain
        var script = document.createElement('script');
        script.src = '{{ route('widget.script', ['orgId' => 3]) }}';
        script.async = true;
        script.onload = function() {
            console.log('AI Chat Widget loaded successfully');
            
            // Debug widget state after load
            setTimeout(function() {
                const widget = document.querySelector('.ai-chat-widget');
                const button = document.querySelector('.ai-chat-button');
                const window = document.querySelector('.ai-chat-window');
                
                console.log('Widget element:', widget);
                console.log('Button element:', button);
                console.log('Window element:', window);
                
                if (window) {
                    console.log('Window display:', window.style.display);
                    console.log('Window computed display:', getComputedStyle(window).display);
                }
            }, 1000);
        };
        script.onerror = function() {
            console.error('Failed to load AI Chat Widget');
        };
        document.head.appendChild(script);
    });
</script>
