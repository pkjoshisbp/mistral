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
        
        // Check if user has an organization assigned (using the many-to-many relationship)
        if (!$user || $user->organizations()->count() === 0) {
            // Redirect to setup organization page instead of denying access
            return redirect()->route('customer.setup-organization')
                ->with('error', 'Please set up your organization to access this feature.');
        }

        return $next($request);
    }
}
