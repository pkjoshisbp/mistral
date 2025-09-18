<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

                // Content Security Policy
        $csp = [
            "default-src 'self'",
            // Added googletagmanager + google analytics collection, and split into explicit script-src-elem / attr for clarity
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com https://www.googletagmanager.com https://www.google-analytics.com",
            "script-src-elem 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://code.jquery.com https://www.googletagmanager.com https://www.google-analytics.com",
            "script-src-attr 'self' 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https: http: https://www.google-analytics.com",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net https://cdnjs.cloudflare.com",
            "connect-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://fonts.googleapis.com https://fonts.gstatic.com https://code.jquery.com https://www.googletagmanager.com https://www.google-analytics.com",
            "frame-src 'self' https://www.googletagmanager.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'"
        ];

        $response->headers->set('Content-Security-Policy', implode('; ', $csp));
        
        // Additional security headers
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        
        // HSTS (HTTP Strict Transport Security)
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        return $response;
    }
}
