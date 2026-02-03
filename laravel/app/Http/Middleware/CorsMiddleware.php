<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Organization;
use Illuminate\Support\Str;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Determine requested origin
        $origin = $request->headers->get('Origin');
        $allowedOrigin = '*';
        $logContext = [
            'path' => $request->path(),
            'method' => $request->getMethod(),
            'origin' => $origin,
        ];

        // Match widget or analytics tracking paths to derive organization id
        // Patterns: /widget/{orgId}/... or /analytics/track with org_id param
        $orgId = null;
        if ($request->is('widget/*')) {
            $segments = $request->segments(); // ['widget', '{orgId}', '...']
            if (isset($segments[1]) && is_numeric($segments[1])) {
                $orgId = (int) $segments[1];
            }
        } elseif ($request->is('analytics/track') && $request->has(['organization_id'])) {
            $orgId = (int) $request->input('organization_id');
        }

        if ($orgId && $origin) {
            $organization = Organization::find($orgId);
            if ($organization && $organization->website) {
                // Normalize stored website (ensure scheme-less host compare)
                $storedHost = parse_url($organization->website, PHP_URL_HOST) ?: $organization->website;
                $originHost = parse_url($origin, PHP_URL_HOST) ?: $origin;
                $normalizedStored = Str::lower($storedHost);
                $normalizedOrigin = Str::lower($originHost);
                $variants = [];
                if (str_starts_with($normalizedStored, 'www.')) {
                    $variants[] = substr($normalizedStored, 4); // apex
                } else {
                    $variants[] = 'www.' . $normalizedStored; // www variant
                }
                if ($normalizedStored === $normalizedOrigin || in_array($normalizedOrigin, $variants, true)) {
                    $allowedOrigin = $origin; // Reflect validated origin
                    $logContext['match'] = $normalizedStored === $normalizedOrigin ? 'exact-website-host' : 'www-apex-alias';
                } else {
                    $logContext['website_host_mismatch'] = [$storedHost, $originHost];
                }
            } elseif ($origin) {
                // If no website stored, optimistically allow the requesting origin and recommend storing later
                $allowedOrigin = $origin;
                $logContext['match'] = 'no-website-stored-allow-origin';
            }
        } elseif ($origin) {
            // Non widget/analytics request with origin - keep permissive wildcard for now
            $allowedOrigin = $origin;
            $logContext['match'] = 'generic-non-widget';
        }

        // Handle preflight early
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 204);
        } else {
            $response = $next($request);
        }

        $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
        $response->headers->set('Vary', 'Origin');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept, Authorization, X-Requested-With, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('X-Debug-CORS', '1');

        $logContext['allowed_origin'] = $allowedOrigin;
        $logContext['status'] = $response->getStatusCode();
        \Log::info('CORS Middleware Debug', $logContext);

        return $response;
    }
}
