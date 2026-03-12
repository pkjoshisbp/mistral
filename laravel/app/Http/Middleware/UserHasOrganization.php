<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UserHasOrganization
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Check if user has access (either active subscription or some usable credits)
        if (!$user || !$user->hasAnyAccess()) {
            return redirect()->route('customer.subscription')
                ->with('error', 'Access requires an active subscription or available credits. Please subscribe or purchase credits to continue.');
        }
        
        // Then check if user has an organization assigned (using the many-to-many relationship)
        if ($user->organizations()->count() === 0) {
            // If they have access but no organization, redirect to setup
            return redirect()->route('customer.setup-organization')
                ->with('info', 'Please set up your organization to access dashboard features.');
        }

        return $next($request);
    }
}
