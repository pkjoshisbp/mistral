<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- SEO Meta Tags -->
    <title>@yield('title', __('common.hero_intro'))</title>
    <meta name="description" content="@yield('description', __('common.hero_sub'))">
    <meta name="keywords" content="@yield('keywords', 'AI chatbot for website, automated WhatsApp replies, 24/7 customer support AI, AI chatbot for business websites, AI support bot for customer service, AI chatbot solutions for websites, WhatsApp chatbot for business support, AI chatbot automation tools, AI chatbot software for small business, 24/7 live chat support automation, AI-powered virtual assistant for customers, chatbot integration with website, automated lead generation chatbot, WhatsApp automation for business, WhatsApp marketing automation tools, automated WhatsApp responses for companies, WhatsApp bulk messaging solutions, WhatsApp auto reply for customer enquiries, WhatsApp API automation services, WhatsApp chatbot integration, reduce support cost with chatbot, improve customer engagement with AI, lead generation through messaging automation, automate customer communications, chatbot for instant query response, AI customer support automation tools')">
    
    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="@yield('og_title', __('common.hero_intro'))">
    <meta property="og:description" content="@yield('og_description', __('common.hero_sub'))">
    <meta property="og:image" content="@yield('og_image', asset('images/ai-chat-og-image.jpg'))">
    <meta property="og:url" content="{{ rtrim(config('app.url'), '/') . request()->getPathInfo() }}">
    <meta property="og:type" content="website">
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('twitter_title', __('common.hero_intro'))">
    <meta name="twitter:description" content="@yield('twitter_description', __('common.hero_sub'))">

    <!-- SEO: Hreflang for language versions -->
    @php($currentPath = request()->getPathInfo())
    @php($currentSegments = array_values(array_filter(explode('/', $currentPath))))
    @php($supportedLocales = ['de','fr','es','it','pt','hi','th'])
    @php($isLocalized = !empty($currentSegments) && in_array($currentSegments[0], $supportedLocales))
    @php($basePath = $isLocalized && count($currentSegments) > 1 ? '/' . implode('/', array_slice($currentSegments, 1)) : ($isLocalized ? '' : $currentPath))
    @php($baseUrl = rtrim(config('app.url'), '/'))
    @php($buildUrl = function (string $path) use ($baseUrl) {
        $clean = ltrim($path, '/');
        return $clean === '' ? $baseUrl : $baseUrl . '/' . $clean;
    })
    
    <!-- English version (no prefix, no trailing slash) -->
    <link rel="alternate" hreflang="en" href="{{ rtrim($buildUrl($basePath ?: '/'), '/') ?: $baseUrl }}" />
    
    <!-- Localized versions (no trailing slashes) -->
    @foreach($supportedLocales as $loc)
        <link rel="alternate" hreflang="{{ $loc }}" href="{{ rtrim($buildUrl('/' . $loc . $basePath), '/') }}" />
    @endforeach
    
    <!-- Default language (no trailing slash) -->
    <link rel="alternate" hreflang="x-default" href="{{ rtrim($buildUrl($basePath ?: '/'), '/') ?: $baseUrl }}" />
    
    <!-- Canonical URL (current page, no trailing slash) -->
    @php($currentLocale = app()->getLocale())
    @php($canonicalPath = $currentLocale === 'en' ? ($basePath ?: '/') : '/' . $currentLocale . $basePath)
    <link rel="canonical" href="{{ rtrim($buildUrl($canonicalPath), '/') ?: $baseUrl }}" />
    
    <meta name="twitter:image" content="@yield('twitter_image', asset('images/ai-chat-twitter-image.jpg'))">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('favicon.ico') }}">
    
    <!-- Auth Status for Widget -->
    @auth
    <meta name="user-authenticated" content="true">
    @endauth
    
        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-7VXTLYKR25"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', 'G-7VXTLYKR25');
        </script>

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Compiled Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            /* Provide safe defaults if not defined elsewhere */
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --text-dark: #0f172a; /* slate-900 */
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
            background: var(--text-dark, #0f172a);
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
    @livewireStyles
</head>
<body>
    @include('partials.header')

    <main style="margin-top: var(--navbar-height, 76px);">
        {{-- Render Livewire component slot if present, otherwise fall back to traditional @section content --}}
        @if (isset($slot))
            {{ $slot }}
        @else
            @yield('content')
        @endif
    </main>

    @include('partials.footer')

    @yield('scripts')
    @livewireScripts
    <script>
        (function() {
            function setNavbarOffset() {
                var nav = document.querySelector('.navbar.fixed-top');
                if (!nav) return;
                var h = nav.getBoundingClientRect().height;
                document.documentElement.style.setProperty('--navbar-height', Math.ceil(h) + 'px');
            }
            window.addEventListener('load', setNavbarOffset, { once: true });
            window.addEventListener('resize', setNavbarOffset);
            document.addEventListener('DOMContentLoaded', setNavbarOffset);
        })();
    </script>
    
</body>
</html>
