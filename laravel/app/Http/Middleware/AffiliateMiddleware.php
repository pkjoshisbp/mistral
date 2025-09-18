<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;

class AffiliateMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        if ($user->role !== 'affiliate') {
            abort(403, 'Access denied. Affiliate role required.');
        }

        // Check if affiliate profile exists and is approved
        if (!$user->affiliate) {
            abort(403, 'Access denied. No affiliate profile found.');
        }

        if ($user->affiliate->status !== 'approved') {
            return redirect()->route('home')->with('error', 'Your affiliate application is still pending approval.');
        }

        return $next($request);
    }
}
