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
            
            // If user has an active subscription, allow them to continue to organization setup
            // Otherwise, redirect them to subscription page first
            if (!$user->activeSubscription) {
                return redirect()->route('customer.subscription')
                    ->with('error', 'Please select a subscription plan first, then set up your organization.');
            }
            
            // If they have a subscription but no organization, redirect to setup
            return redirect()->route('customer.setup-organization')
                ->with('info', 'Please set up your organization to access dashboard features.');
        }

        return $next($request);
    }
}
