@extends('layouts.public')

@section('title', 'Blog - AI Chat Support')
@section('meta_description', 'Stay updated with the latest insights, tips, and trends in AI customer support. Expert advice to help your business thrive.')

@section('styles')
<style>
    .text-gradient {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    
    .hero-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 100px 0 80px;
    }
    
    .blog-card {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border: none;
        border-radius: 15px;
        overflow: hidden;
        height: 100%;
    }
    
    .blog-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.1);
    }
    
    .blog-meta {
        color: #6c757d;
        font-size: 0.9em;
    }
    
    .blog-meta i {
        margin-right: 5px;
    }
    
    .read-more-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        border-radius: 25px;
        padding: 10px 25px;
        color: white;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s ease;
    }
    
    .read-more-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        color: white;
        text-decoration: none;
    }
    
    .newsletter-section {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    
    .newsletter-section .form-control {
        border: none;
        border-radius: 25px;
        padding: 12px 20px;
    }
    
    .newsletter-section .btn {
        background: rgba(255,255,255,0.2);
        border: 2px solid white;
        border-radius: 25px;
        padding: 10px 30px;
        color: white;
        margin-left: 10px;
    }
    
    .newsletter-section .btn:hover {
        background: white;
        color: #667eea;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
    }
</style>
@endsection

@section('content')
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0;
        }
        
        .blog-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
        }
        
        .blog-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .blog-card img {
            height: 250px;
            object-fit: cover;
            width: 100%;
        }
        
        .blog-meta {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .tag-badge {
            background-color: #667eea;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
            margin: 0.2rem;
        }
        
        .tag-badge:hover {
            background-color: #764ba2;
            color: white;
            text-decoration: none;
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
        
        .pagination .page-link {
            border-radius: 50px;
            margin: 0 5px;
            border: none;
            color: #667eea;
        }
        
        .pagination .page-item.active .page-link {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
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
                                @auth
                                    @if(auth()->user()->role === 'admin')
                                        <li><a class="dropdown-item" href="{{ route('admin.dashboard') }}">Admin Dashboard</a></li>
                                    @elseif(auth()->user()->role === 'customer')
                                        <li><a class="dropdown-item" href="{{ route('customer.dashboard') }}">Customer Dashboard</a></li>
                                    @else
                                        <li><a class="dropdown-item" href="{{ route('login') }}">Login</a></li>
                                    @endif
                                @else
                                    <li><a class="dropdown-item" href="{{ route('login') }}">Login</a></li>
                                @endauth
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
    @section('content')
    
    <!-- Hero Section -->

    <!-- Blog Posts -->
    <section class="py-5">
        <div class="container">
            @if($blogs->count() > 0)
                <div class="row g-4">
                    @foreach($blogs as $blog)
                        <div class="col-lg-4 col-md-6">
                            <div class="card blog-card">
                                <img src="{{ $blog->featured_image }}" class="card-img-top" alt="{{ $blog->title }}">
                                <div class="card-body d-flex flex-column">
                                    <div class="mb-3">
                                        @foreach($blog->tags as $tag)
                                            <span class="tag-badge">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                    
                                    <h5 class="card-title">{{ $blog->title }}</h5>
                                    <p class="card-text flex-grow-1">{{ $blog->excerpt }}</p>
                                    
                                    <div class="blog-meta mb-3">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        {{ $blog->published_at->format('M d, Y') }}
                                        <span class="ms-3">
                                            <i class="fas fa-clock me-2"></i>
                                            {{ $blog->reading_time }}
                                        </span>
                                    </div>
                                    
                                    <a href="{{ route('blog.show', $blog->slug) }}" class="btn btn-primary">
                                        Read More <i class="fas fa-arrow-right ms-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($blogs->hasPages())
                    <div class="d-flex justify-content-center mt-5">
                        {{ $blogs->links() }}
                    </div>
                @endif
            @else
                <div class="text-center py-5">
                    <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                    <h3>No blog posts yet</h3>
                    <p class="text-muted">Check back soon for interesting articles about AI customer support!</p>
                </div>
            @endif
        </div>
    </section>

    <!-- Newsletter Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8 text-center">
                    <h3 class="fw-bold mb-3">Stay Updated</h3>
                    <p class="text-muted mb-4">Subscribe to our newsletter for the latest AI customer support insights and tips</p>
                    <div class="row g-3 justify-content-center">
                        <div class="col-md-6">
                            <input type="email" class="form-control" placeholder="Enter your email">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-primary">Subscribe</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>


@endsection
