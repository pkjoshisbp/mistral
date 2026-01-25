<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to your application's "home" route.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/dashboard';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Widget chat rate limiting: 5 requests per minute per session
        RateLimiter::for('widget_chat', function (Request $request) {
            $sessionId = $request->input('session_id', $request->ip());
            return Limit::perMinute(5)
                ->by('widget_chat:' . $sessionId)
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'error' => 'Too many messages. Please wait a moment before sending another message.',
                        'retry_after' => $headers['Retry-After'] ?? 60
                    ], 429);
                });
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
