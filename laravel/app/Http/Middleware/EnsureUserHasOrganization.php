<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();
        
        // Check if user has any organizations
        if (!$user || $user->organizations()->count() === 0) {
            return redirect()->route('customer.setup-organization')
                ->with('error', 'You need to create or join an organization to access this area.');
        }
        
        return $next($request);
    }
}
