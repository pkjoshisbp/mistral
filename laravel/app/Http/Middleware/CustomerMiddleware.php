<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomerMiddleware
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

        // Check if user has customer role
        if (auth()->user()->role !== 'customer') {
            // If admin, redirect to admin dashboard
            if (auth()->user()->role === 'admin') {
                return redirect()->route('dashboard');
            }
            
            // Otherwise, unauthorized
            abort(403, 'Access denied. Customer role required.');
        }

        return $next($request);
    }
}
