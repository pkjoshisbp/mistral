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
        if (auth()->check() && auth()->user()->isCustomer()) {
            // If user doesn't have an organization, redirect to setup
            if (!auth()->user()->organization_id) {
                return redirect()->route('customer.setup-organization');
            }
        }

        return $next($request);
    }
}
