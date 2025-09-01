<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\Events\TranslationMissing;
use Illuminate\Support\Facades\Event;

class LogMissingTranslations
{
    public function handle($request, Closure $next)
    {
        if (app()->environment('local')) {
            // Register listener once per request (cheap) for missing translation events.
            Event::listen(TranslationMissing::class, function (TranslationMissing $event) {
                Log::warning('Missing translation', [
                    'locale' => $event->locale,
                    'key' => $event->key,
                    'namespace' => $event->namespace,
                    'group' => $event->group,
                    'replace' => $event->replace,
                ]);
            });
        }

        return $next($request);
    }
}
