<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalHostRedirect
{
    /**
     * Redirect to the canonical scheme/host defined in config('app.url').
     */
    public function handle(Request $request, Closure $next): Response
    {
        $appUrl = (string) config('app.url');
        if ($appUrl === '') {
            return $next($request);
        }

        $parsed = parse_url($appUrl);
        $targetHost = $parsed['host'] ?? null;
        if (!$targetHost) {
            return $next($request);
        }

        $targetScheme = $parsed['scheme'] ?? $request->getScheme();
        $targetPort = $parsed['port'] ?? null;
        $targetAuthority = $targetHost . ($targetPort ? ':' . $targetPort : '');

        $currentHost = $request->getHost();
        $currentScheme = $request->getScheme();

        if ($currentHost === $targetHost && $currentScheme === $targetScheme) {
            return $next($request);
        }

        $path = $request->path();
        $path = ($path === '/' || $path === '') ? '' : '/' . ltrim($path, '/');
        $query = $request->getQueryString();

        $targetUrl = $targetScheme . '://' . $targetAuthority . $path;
        if ($query) {
            $targetUrl .= '?' . $query;
        }

        return redirect()->to($targetUrl, 301);
    }
}
