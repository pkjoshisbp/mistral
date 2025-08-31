<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $blog->title }} - AI Chat Support Blog</title>
    <meta name="description" content="{{ $blog->excerpt }}">
    <meta name="keywords" content="{{ implode(', ', $blog->tags) }}">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="{{ $blog->title }}">
    <meta property="og:description" content="{{ $blog->excerpt }}">
    <meta property="og:image" content="{{ $blog->featured_image }}">
    <meta property="og:url" content="{{ route('blog.show', $blog->slug) }}">
    <meta property="og:type" content="article">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $blog->title }}">
    <meta name="twitter:description" content="{{ $blog->excerpt }}">
    <meta name="twitter:image" content="{{ $blog->featured_image }}">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Prism.js for code highlighting -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css" rel="stylesheet">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 50px;
        }
        
        .blog-content {
            font-size: 1.1rem;
            line-height: 1.8;
        }
        
        .blog-content h3 {
            color: #333;
            margin-top: 2rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .blog-content h4 {
            color: #444;
            margin-top: 1.5rem;
            margin-bottom: 0.8rem;
            font-weight: 500;
        }
        
        .blog-content ul, .blog-content ol {
            margin: 1.5rem 0;
            padding-left: 2rem;
        }
        
        .blog-content li {
            margin-bottom: 0.5rem;
        }
        
        .blog-content p {
            margin-bottom: 1.5rem;
        }
        
        .blog-content strong {
            color: #333;
        }
        
        .blog-content em {
            background-color: #f8f9fa;
            padding: 0.5rem;
            border-left: 4px solid #667eea;
            display: block;
            margin: 1rem 0;
            font-style: normal;
        }
        
        .tag-badge {
            background-color: #667eea;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
            margin: 0.2rem;
        }
        
        .tag-badge:hover {
            background-color: #764ba2;
            color: white;
            text-decoration: none;
        }
        
        .related-post-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            height: 100%;
        }
        
        .related-post-card:hover {
            transform: translateY(-3px);
        }
        
        .related-post-card img {
            height: 200px;
            object-fit: cover;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .social-share {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin: 2rem 0;
        }
        
        .social-share a {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin: 0.25rem;
            border-radius: 25px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-share .twitter { background-color: #1da1f2; }
        .social-share .facebook { background-color: #3b5998; }
        .social-share .linkedin { background-color: #0077b5; }
        .social-share .email { background-color: #6c757d; }
        
        .social-share a:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .blog-meta {
            background-color: #f8f9fa;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .breadcrumb {
            background: none;
            padding: 0;
        }
        
        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="{{ route('home') }}">
                <i class="fas fa-robot me-2"></i>AI Chat Support
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('about') }}">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('features') }}">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="{{ route('blog.index') }}">Blog</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('contact') }}">Contact</a>
                    </li>
                    @auth
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                {{ Auth::user()->name }}
                            </a>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}">Dashboard</a></li>
                                <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item">Logout</button>
                                    </form>
                                </li>
                            </ul>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white px-3 ms-2" href="{{ route('login') }}">Login</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Blog</a></li>
                    <li class="breadcrumb-item active">{{ $blog->title }}</li>
                </ol>
            </nav>
            
            <div class="row align-items-center">
                <div class="col-lg-8">
                    <h1 class="display-5 fw-bold mb-3">{{ $blog->title }}</h1>
                    <p class="lead mb-4">{{ $blog->excerpt }}</p>
                    
                    <div class="d-flex flex-wrap align-items-center text-white-50">
                        <span class="me-4">
                            <i class="fas fa-calendar-alt me-2"></i>
                            {{ $blog->published_at->format('F j, Y') }}
                        </span>
                        <span class="me-4">
                            <i class="fas fa-clock me-2"></i>
                            {{ $blog->reading_time }}
                        </span>
                        <span>
                            <i class="fas fa-tags me-2"></i>
                            @foreach($blog->tags as $tag)
                                <span class="tag-badge">{{ $tag }}</span>
                            @endforeach
                        </span>
                    </div>
                </div>
                <div class="col-lg-4 text-center">
                    <img src="{{ $blog->featured_image }}" alt="{{ $blog->title }}" class="img-fluid rounded shadow" style="max-height: 300px;">
                </div>
            </div>
        </div>
    </section>

    <!-- Blog Content -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Blog Content -->
                    <div class="blog-content">
                        {!! $blog->content !!}
                    </div>

                    <!-- Social Share -->
                    <div class="social-share text-center">
                        <h5 class="mb-3"><i class="fas fa-share-alt me-2"></i>Share this article</h5>
                        <a href="https://twitter.com/intent/tweet?text={{ urlencode($blog->title) }}&url={{ urlencode(route('blog.show', $blog->slug)) }}" target="_blank" class="twitter">
                            <i class="fab fa-twitter me-2"></i>Twitter
                        </a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('blog.show', $blog->slug)) }}" target="_blank" class="facebook">
                            <i class="fab fa-facebook me-2"></i>Facebook
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(route('blog.show', $blog->slug)) }}" target="_blank" class="linkedin">
                            <i class="fab fa-linkedin me-2"></i>LinkedIn
                        </a>
                        <a href="mailto:?subject={{ urlencode($blog->title) }}&body={{ urlencode($blog->excerpt . ' ' . route('blog.show', $blog->slug)) }}" class="email">
                            <i class="fas fa-envelope me-2"></i>Email
                        </a>
                    </div>

                    <!-- Back to Blog -->
                    <div class="text-center mt-4">
                        <a href="{{ route('blog.index') }}" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Blog
                        </a>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-lg-4">
                    <!-- Blog Meta -->
                    <div class="blog-meta">
                        <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>Article Info</h5>
                        <p class="mb-2"><strong>Published:</strong> {{ $blog->published_at->format('F j, Y') }}</p>
                        <p class="mb-2"><strong>Reading Time:</strong> {{ $blog->reading_time }}</p>
                        <p class="mb-2"><strong>Category:</strong> AI Customer Support</p>
                        <div>
                            <strong>Tags:</strong><br>
                            @foreach($blog->tags as $tag)
                                <span class="tag-badge">{{ $tag }}</span>
                            @endforeach
                        </div>
                    </div>

                    <!-- Newsletter Signup -->
                    <div class="card border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-white text-center">
                            <h5 class="card-title">Stay Updated</h5>
                            <p class="card-text">Get the latest AI customer support insights delivered to your inbox.</p>
                            <div class="mb-3">
                                <input type="email" class="form-control" placeholder="Your email address">
                            </div>
                            <button class="btn btn-light">Subscribe</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Related Posts -->
    @if($relatedPosts->count() > 0)
    <section class="py-5 bg-light">
        <div class="container">
            <h3 class="text-center mb-5">Related Articles</h3>
            <div class="row g-4">
                @foreach($relatedPosts as $relatedPost)
                    <div class="col-lg-4">
                        <div class="card related-post-card">
                            <img src="{{ $relatedPost->featured_image }}" class="card-img-top" alt="{{ $relatedPost->title }}">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">{{ $relatedPost->title }}</h5>
                                <p class="card-text flex-grow-1">{{ Str::limit($relatedPost->excerpt, 100) }}</p>
                                
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <small class="text-muted">
                                        {{ $relatedPost->published_at->format('M d, Y') }}
                                    </small>
                                    <a href="{{ route('blog.show', $relatedPost->slug) }}" class="btn btn-sm btn-primary">Read More</a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Footer -->
    <footer class="bg-dark text-light py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-3">
                    <h5 class="fw-bold mb-3">
                        <i class="fas fa-robot me-2"></i>AI Chat Support
                    </h5>
                    <p class="text-muted">Revolutionizing customer support with intelligent AI solutions that help businesses provide exceptional service 24/7.</p>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Product</h6>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('features') }}" class="text-muted text-decoration-none">Features</a></li>
                        <li><a href="{{ route('blog.index') }}" class="text-muted text-decoration-none">Blog</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Pricing</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">API Docs</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Company</h6>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('about') }}" class="text-muted text-decoration-none">About Us</a></li>
                        <li><a href="{{ route('contact') }}" class="text-muted text-decoration-none">Contact</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Careers</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Press</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-2">
                    <h6 class="fw-bold mb-3">Legal</h6>
                    <ul class="list-unstyled">
                        <li><a href="{{ route('terms') }}" class="text-muted text-decoration-none">Terms of Service</a></li>
                        <li><a href="{{ route('privacy') }}" class="text-muted text-decoration-none">Privacy Policy</a></li>
                        <li><a href="{{ route('refund-policy') }}" class="text-muted text-decoration-none">Refund Policy</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Cookies</a></li>
                    </ul>
                </div>
                
                <div class="col-lg-3">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">Help Center</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Community</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Status</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Security</a></li>
                    </ul>
                    
                    <div class="mt-3">
                        <h6 class="fw-bold mb-3">Follow Us</h6>
                        <a href="#" class="text-muted me-3"><i class="fab fa-twitter fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-linkedin fa-lg"></i></a>
                        <a href="#" class="text-muted me-3"><i class="fab fa-github fa-lg"></i></a>
                        <a href="#" class="text-muted"><i class="fab fa-facebook fa-lg"></i></a>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; {{ date('Y') }} AI Chat Support. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="text-muted mb-0">Made with <i class="fas fa-heart text-danger"></i> for better customer experiences</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Prism.js for code highlighting -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js"></script>
</body>
</html>
