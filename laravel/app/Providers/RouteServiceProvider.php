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

        // Widget chat rate limiting: session cap plus IP caps so session rotation cannot bypass it.
        RateLimiter::for('widget_chat', function (Request $request) {
            $orgId = trim((string) $request->route('orgId', 'global'));
            $sessionId = trim((string) $request->input('session_id', ''));
            $sessionKey = $sessionId !== '' ? hash('sha256', $sessionId) : 'missing';
            $ipKey = hash('sha256', (string) $request->ip());
            $response = function (Request $request, array $headers) {
                $retryAfter = (int) ($headers['Retry-After'] ?? 60);

                return response()->json([
                    'error' => 'Too many messages. Please wait a moment before sending another message.',
                    'message' => 'Too many messages. Please wait a moment before sending another message.',
                    'retry_after' => $retryAfter,
                ], 429);
            };

            return [
                Limit::perMinute(5)
                    ->by("widget_chat:session:{$orgId}:{$sessionKey}")
                    ->response($response),
                Limit::perMinute(15)
                    ->by("widget_chat:ip_minute:{$orgId}:{$ipKey}")
                    ->response($response),
                Limit::perHour(120)
                    ->by("widget_chat:ip_hour:{$orgId}:{$ipKey}")
                    ->response($response),
            ];
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
