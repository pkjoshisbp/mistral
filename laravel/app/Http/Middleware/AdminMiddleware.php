<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Check if user has admin role
        if (auth()->user()->role !== 'admin') {
            // If customer, redirect to customer dashboard
            if (auth()->user()->role === 'customer') {
                return redirect()->route('customer.dashboard');
            }
            
            // Otherwise, unauthorized
            abort(403, 'Access denied. Administrator privileges required.');
        }

        return $next($request);
    }
}
