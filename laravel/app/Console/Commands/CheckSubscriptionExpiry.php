<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SubscriptionExpired;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckSubscriptionExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscription:check-expiry {--days=7 : Number of days before expiry to warn} {--notify : Send expiry notifications}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for expiring subscriptions and optionally send notifications';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $daysWarning = $this->option('days');
        $sendNotifications = $this->option('notify');
        
        $this->info("Checking subscriptions expiring within {$daysWarning} days...");

        // Find subscriptions expiring soon
        $expiringSoon = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', Carbon::now()->addDays($daysWarning))
            ->where('current_period_end', '>', Carbon::now())
            ->with(['user', 'organization', 'subscriptionPlan'])
            ->get();

        // Find already expired subscriptions
        $expired = Subscription::where('status', 'active')
            ->where('current_period_end', '<', Carbon::now())
            ->with(['user', 'organization', 'subscriptionPlan'])
            ->get();

        $this->table(
            ['Organization', 'User', 'Plan', 'Status', 'Expires', 'Days Left'],
            $expiringSoon->map(function ($sub) {
                return [
                    $sub->organization->name ?? 'N/A',
                    $sub->user->email ?? 'N/A',
                    $sub->subscriptionPlan->name ?? 'Unknown',
                    'Active',
                    $sub->current_period_end->format('Y-m-d H:i'),
                    $sub->current_period_end->diffInDays(Carbon::now())
                ];
            })->toArray()
        );

        if ($expired->count() > 0) {
            $this->error("Found {$expired->count()} expired subscriptions:");
            
            $this->table(
                ['Organization', 'User', 'Plan', 'Expired Date', 'Days Overdue'],
                $expired->map(function ($sub) {
                    return [
                        $sub->organization->name ?? 'N/A',
                        $sub->user->email ?? 'N/A',
                        $sub->subscriptionPlan->name ?? 'Unknown',
                        $sub->current_period_end->format('Y-m-d H:i'),
                        Carbon::now()->diffInDays($sub->current_period_end)
                    ];
                })->toArray()
            );

            // Deactivate expired subscriptions
            if ($this->confirm('Deactivate expired subscriptions?')) {
                $deactivatedCount = 0;
                foreach ($expired as $subscription) {
                    $subscription->update(['status' => 'expired']);
                    $deactivatedCount++;
                    
                    // Send expiry notification
                    if ($sendNotifications && $subscription->user) {
                        try {
                            $subscription->user->notify(new SubscriptionExpired($subscription));
                            Log::info("Sent expiry notification to user: {$subscription->user->email}");
                        } catch (\Exception $e) {
                            Log::error("Failed to send expiry notification: {$e->getMessage()}");
                        }
                    }
                }
                
                $this->info("Deactivated {$deactivatedCount} expired subscriptions");
            }
        }

        // Send warning notifications for expiring subscriptions
        if ($sendNotifications && $expiringSoon->count() > 0) {
            if ($this->confirm("Send expiry warning notifications to {$expiringSoon->count()} users?")) {
                $sentCount = 0;
                foreach ($expiringSoon as $subscription) {
                    if ($subscription->user) {
                        try {
                            // Create expiry warning notification
                            $subscription->user->notify(new SubscriptionExpired($subscription));
                            $sentCount++;
                            Log::info("Sent expiry warning to user: {$subscription->user->email}");
                        } catch (\Exception $e) {
                            Log::error("Failed to send expiry warning: {$e->getMessage()}");
                        }
                    }
                }
                $this->info("Sent {$sentCount} expiry warning notifications");
            }
        }

        if ($expiringSoon->isEmpty() && $expired->isEmpty()) {
            $this->info('No subscriptions require attention at this time.');
        }

        return 0;
    }
}
