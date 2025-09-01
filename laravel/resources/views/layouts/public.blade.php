<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- SEO Meta Tags -->
    <title>@yield('title', __('common.hero_intro'))</title>
    <meta name="description" content="@yield('description', __('common.hero_sub'))">
    <meta name="keywords" content="@yield('keywords', 'AI chat support, customer service automation, chatbot, artificial intelligence, live chat, customer support software')">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="@yield('og_title', __('common.hero_intro'))">
    <meta property="og:description" content="@yield('og_description', __('common.hero_sub'))">
    <meta property="og:image" content="@yield('og_image', asset('images/ai-chat-og-image.jpg'))">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', __('common.hero_intro'))">
    <meta name="twitter:description" content="@yield('twitter_description', __('common.hero_sub'))">

    <!-- Hreflang for alternate locales -->
    @php($availableLocales = ['en','de','fr','it','pt','hi','es','th'])
    @foreach($availableLocales as $loc)
        <link rel="alternate" hreflang="{{ $loc }}" href="{{ url()->current() }}?lang={{ $loc }}" />
    @endforeach
    <link rel="alternate" hreflang="x-default" href="{{ url()->current() }}" />
    <meta name="twitter:image" content="@yield('twitter_image', asset('images/ai-chat-twitter-image.jpg'))">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    <!-- Auth Status for Widget -->
    @auth
    <meta name="user-authenticated" content="true">
    @endauth
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #8b5cf6;
            --accent-color: #06b6d4;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --bg-light: #f8fafc;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
        }
        
        .gradient-bg {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .section-padding {
            padding: 5rem 0;
        }
        
        .text-gradient {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .card-hover {
            transition: all 0.3s ease;
        }
        
        .card-hover:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        footer {
            background: var(--text-dark);
            color: white;
        }
        
        footer a {
            color: #cbd5e1;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        footer a:hover {
            color: white;
        }
    </style>
    
    @yield('styles')
</head>
<body>
    @include('partials.header')

    <main style="margin-top: 76px;">
        @yield('content')
    </main>

    @include('partials.footer')

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    @yield('scripts')
    
    <!-- AI Chat Widget -->
    <script>
        (function() {
            // Widget will be loaded here for organization ID 3 (ai-chat-support)
            const orgId = 3;
            const script = document.createElement('script');
            script.src = `{{ config('app.url') }}/widget/${orgId}/script.js`;
            script.async = true;
            document.head.appendChild(script);
        })();
    </script>
</body>
</html>
