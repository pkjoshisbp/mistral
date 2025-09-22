<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Console\Events\CommandStarting;

class ConsoleSafetyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // no-op
    }

    public function boot(): void
    {
        // Intercept dangerous console commands to prevent accidental data loss on non-test DBs
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            $dangerous = [
                'migrate:fresh',
                'migrate:reset',
                'migrate:refresh',
                'db:wipe',
            ];

            $command = $event->command;
            if (!$command || !in_array($command, $dangerous, true)) {
                return;
            }

            // Allow override via explicit env flag
            if (env('ALLOW_DANGEROUS_CONSOLE') === '1') {
                return;
            }

            $default = config('database.default');
            $driver = $default;
            $conn = config("database.connections.$default");
            if (is_array($conn) && isset($conn['driver'])) {
                $driver = $conn['driver'];
            }

            // Block if we're not using sqlite (i.e., likely a real database)
            if ($driver !== 'sqlite') {
                fwrite(STDERR, "\n[ABORTED] Command '$command' is disabled on non-sqlite connections to protect data. Set ALLOW_DANGEROUS_CONSOLE=1 to override intentionally.\n");
                exit(1);
            }
        });
    }
}
