<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireActiveSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();
        
        // Allow admins to bypass subscription requirements
        if ($user && $user->role === 'admin') {
            return $next($request);
        }
        
        // Check if user has an active subscription
        if (!$user || !$user->activeSubscription) {
            // If this is an AJAX request or API call, return JSON error
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => 'Active subscription required to access this feature.',
                    'redirect' => route('customer.subscription')
                ], 402); // 402 Payment Required
            }
            
            // For regular web requests, redirect to subscription page with message
            return redirect()->route('customer.subscription')
                ->with('warning', 'An active subscription is required to access this feature. Please subscribe to continue.');
        }

        return $next($request);
    }
}