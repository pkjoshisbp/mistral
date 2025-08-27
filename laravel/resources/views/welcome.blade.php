<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AI Agent System - Multi-Organization Support</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-robot me-2"></i>AI Agent System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('home') }}">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#features">Features</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#about">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contact">Contact</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    @auth
                        @if(auth()->user()->role === 'admin')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                                    <i class="fas fa-cog me-1"></i>Admin Panel
                                </a>
                            </li>
                        @elseif(auth()->user()->role === 'customer')
                            <li class="nav-item">
                                <a class="nav-link" href="{{ route('customer.dashboard') }}">
                                    <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                                </a>
                            </li>
                        @endif
                        <li class="nav-item">
                            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-link nav-link" style="border: none; background: none;">
                                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                                </button>
                            </form>
                        </li>
                    @else
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('login') }}">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="{{ route('register') }}">Register</a>
                        </li>
                    @endauth
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">AI-Powered Support Agent</h1>
            <p class="lead mb-5">Multi-organization AI support system with advanced data integration capabilities</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-light btn-lg me-3">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Get Started
                        </a>
                    @else
                        @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-cog me-2"></i>Go to Admin Panel
                            </a>
                        @elseif(auth()->user()->role === 'customer')
                            <a href="{{ route('customer.dashboard') }}" class="btn btn-light btn-lg">
                                <i class="fas fa-tachometer-alt me-2"></i>Go to Dashboard
                            </a>
                        @endif
                    @endguest
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5" id="features">
        <div class="container">
            <div class="text-center mb-5">
                <h2>Powerful Features</h2>
                <p class="text-muted">Everything you need to power your AI support system</p>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-robot fa-3x text-primary mb-3"></i>
                            <h5>AI-Powered Chat</h5>
                            <p class="text-muted">Advanced AI using Mistral 7B for natural language understanding</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-database fa-3x text-success mb-3"></i>
                            <h5>Multiple Data Sources</h5>
                            <p class="text-muted">Website crawling, file uploads, Google Sheets, database connections</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-code fa-3x text-warning mb-3"></i>
                            <h5>Embeddable Widget</h5>
                            <p class="text-muted">Easy-to-integrate JavaScript widget for any website</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-building fa-3x text-info mb-3"></i>
                            <h5>Multi-Organization</h5>
                            <p class="text-muted">Support multiple organizations with isolated data</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-search fa-3x text-danger mb-3"></i>
                            <h5>Vector Search</h5>
                            <p class="text-muted">Powered by Qdrant for semantic search and retrieval</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <i class="fas fa-sync fa-3x text-secondary mb-3"></i>
                            <h5>Real-time Sync</h5>
                            <p class="text-muted">Automatic data synchronization and updates</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-light py-5">
        <div class="container text-center">
            <h3>Ready to Get Started?</h3>
            <p class="mb-4">Join organizations already using our AI support system</p>
            @guest
                <a href="{{ route('register') }}" class="btn btn-primary btn-lg">
                    <i class="fas fa-rocket me-2"></i>Start Free Trial
                </a>
            @endguest
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container text-center">
            <p>&copy; {{ date('Y') }} AI Agent System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
