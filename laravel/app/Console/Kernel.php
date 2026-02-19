<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\ResyncFaqsToQdrant::class,
        \App\Console\Commands\PaypalCaptureOrder::class,
        \App\Console\Commands\ResendUnopenedEmails::class,
        \App\Console\Commands\SendScheduledEmailCampaigns::class,
        \App\Console\Commands\SyncWhatsappTemplates::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('email:resend-unopened')->hourly();
        $schedule->command('email:send-scheduled')->everyMinute();
        $schedule->command('whatsapp:sync-templates')->dailyAt('02:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
