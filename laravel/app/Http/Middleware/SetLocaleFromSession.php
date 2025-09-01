<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocaleFromSession
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('app_locale')) {
            app()->setLocale(session('app_locale'));
        }
        return $next($request);
    }
}
