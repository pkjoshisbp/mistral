<!-- Footer -->
<footer class="section-padding">
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <h5 class="text-gradient mb-3">
                    <i class="fas fa-robot me-2"></i>AI Chat Support
                </h5>
                <p class="text-light">
                    Revolutionizing customer support with AI-powered solutions. 
                    Provide instant, intelligent assistance to your customers 24/7.
                </p>
                <div class="social-links">
                    <a href="#" class="me-3"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-linkedin"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-github"></i></a>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Product</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('home') }}#features">Features</a></li>
                    <li><a href="{{ route('home') }}#pricing">Pricing</a></li>
                    <li><a href="#">Integration</a></li>
                    <li><a href="#">API Docs</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Company</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('about') }}">About Us</a></li>
                    <li><a href="{{ route('blog.index') }}">Blog</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Support</h6>
                <ul class="list-unstyled">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Documentation</a></li>
                    <li><a href="#">Community</a></li>
                    <li><a href="#">Status</a></li>
                </ul>
            </div>
            
            <div class="col-lg-2 col-md-6 mb-4">
                <h6 class="fw-bold mb-3">Legal</h6>
                <ul class="list-unstyled">
                    <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('terms') }}">Terms of Service</a></li>
                    <li><a href="{{ route('refund-policy') }}">Refund Policy</a></li>
                </ul>
            </div>
        </div>
        
        <hr class="my-4 border-secondary">
        
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="mb-0">&copy; {{ date('Y') }} AI Chat Support. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="text-muted me-3">Villa No.10, Sriram Villa, AN Guha Lane, Sambalpur - 768001</span>
            </div>
        </div>
    </div>
</footer>
