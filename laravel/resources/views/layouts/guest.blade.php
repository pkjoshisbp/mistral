<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="user-authenticated" content="false">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Hreflang Tags for Multilingual SEO -->
        @php
            $currentPath = ltrim(request()->path(), '/');
            $currentLocale = app()->getLocale();
            $supportedLocales = ['en', 'de', 'fr', 'es', 'it', 'pt', 'hi', 'th'];
            $baseUrl = rtrim(config('app.url'), '/');
            $buildUrl = function (string $path) use ($baseUrl) {
                $clean = ltrim($path, '/');
                return $clean === '' ? $baseUrl : $baseUrl . '/' . $clean;
            };
            
            // Remove locale prefix from path if present
            foreach ($supportedLocales as $loc) {
                if (str_starts_with($currentPath, $loc . '/')) {
                    $currentPath = substr($currentPath, strlen($loc) + 1);
                    break;
                }
            }
        @endphp
        
        @foreach ($supportedLocales as $locale)
            @php
                $hrefUrl = $locale === 'en'
                    ? $buildUrl('/' . $currentPath)
                    : $buildUrl('/' . $locale . '/' . $currentPath);
            @endphp
            <link rel="alternate" hreflang="{{ $locale }}" href="{{ rtrim($hrefUrl, '/') }}" />
        @endforeach
        
        <!-- x-default points to English version -->
        <link rel="alternate" hreflang="x-default" href="{{ rtrim($buildUrl('/' . $currentPath), '/') }}" />
        
        <!-- Canonical URL (always without trailing slash) -->
        <link rel="canonical" href="{{ rtrim($buildUrl('/' . $currentPath), '/') }}" />

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        @include('partials.header')

        <div class="py-5" style="background: #f5f7fb; min-height: 70vh;">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-12 col-md-8 col-lg-6">
                        <div class="card shadow-sm">
                            <div class="card-body p-4 p-md-5">
                                {{ $slot }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @include('partials.footer')
    </body>
</html>
