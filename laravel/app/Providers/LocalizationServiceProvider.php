<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class LocalizationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Add localized route helper
        URL::macro('localized', function ($path = null, $locale = null) {
            $locale = $locale ?? app()->getLocale();
            $availableLocales = ['en', 'de', 'fr', 'it', 'pt', 'hi', 'es', 'th'];
            
            if (!in_array($locale, $availableLocales)) {
                $locale = 'en';
            }
            
            $path = $path ?? request()->getPathInfo();
            
            // Remove existing locale from path
            $segments = array_filter(explode('/', $path));
            if (!empty($segments) && in_array($segments[0], $availableLocales)) {
                array_shift($segments);
                $path = '/' . implode('/', $segments);
            }
            
            // Add new locale prefix (except for English)
            $localizedPath = $locale === 'en' ? $path : '/' . $locale . $path;
            
            return url($localizedPath);
        });
    }
}
