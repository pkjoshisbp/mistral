<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemoveTrailingSlash
{
    /**
     * Handle an incoming request.
     * Redirects URLs with trailing slashes to their non-trailing slash equivalent.
     * This prevents duplicate content issues in search engines.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        
        // If the path has a trailing slash and is not the root path
        if ($path !== '/' && str_ends_with($path, '/')) {
            // Remove trailing slash and redirect permanently (301)
            $newPath = rtrim($path, '/');
            $query = $request->getQueryString();
            $newUrl = $newPath . ($query ? '?' . $query : '');
            
            return redirect($newUrl, 301);
        }
        
        return $next($request);
    }
}
