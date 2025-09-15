<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LocalizationMiddleware
{
    protected $availableLocales = ['en', 'de', 'fr', 'it', 'pt', 'hi', 'es', 'th'];
    protected $defaultLocale = 'en';

    public function handle(Request $request, Closure $next)
    {
        // Get locale from URL path
        $segments = $request->segments();
        $locale = $this->defaultLocale;
        
        if (!empty($segments) && in_array($segments[0], $this->availableLocales)) {
            $locale = $segments[0];
        }

        // Set the locale
        App::setLocale($locale);
        
        // Store in session for future requests
        session(['app_locale' => $locale]);

        return $next($request);
    }
}
