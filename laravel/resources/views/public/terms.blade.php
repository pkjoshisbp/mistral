<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $terms->title ?? 'Terms and Conditions' }} - AI Agent System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <i class="fas fa-robot me-2"></i>AI Agent System
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="{{ route('home') }}">Home</a>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                @if($terms)
                    <h1>{{ $terms->title }}</h1>
                    <hr>
                    <div class="content">
                        {!! nl2br(e($terms->content)) !!}
                    </div>
                    <hr>
                    <p class="text-muted">
                        <small>Last updated: {{ $terms->updated_at->format('F j, Y') }}</small>
                    </p>
                @else
                    <div class="text-center">
                        <h1>Terms and Conditions</h1>
                        <p class="lead">Terms and conditions have not been configured yet.</p>
                        <a href="{{ route('home') }}" class="btn btn-primary">Go Back Home</a>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container text-center">
            <p>&copy; {{ date('Y') }} AI Agent System. All rights reserved.</p>
            <div class="mt-2">
                <a href="{{ route('terms') }}" class="text-white me-3">Terms</a>
                <a href="{{ route('privacy') }}" class="text-white me-3">Privacy</a>
                <a href="{{ route('refund-policy') }}" class="text-white">Refund Policy</a>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
