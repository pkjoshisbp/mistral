<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionConfirmation;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestSubscriptionEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:test-subscription {user_id? : The user ID to test with}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test subscription confirmation email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('user_id') ?? 1;
        
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return;
        }
        
        // Get or create a test subscription
        $subscription = Subscription::where('user_id', $user->id)->first();
        if (!$subscription) {
            $this->error("No subscription found for user {$user->name} (ID: {$userId})");
            $this->line("Available users with subscriptions:");
            $usersWithSubs = User::whereHas('subscriptions')->with('subscriptions')->get();
            foreach ($usersWithSubs as $u) {
                $this->line("- {$u->name} (ID: {$u->id}) - Email: {$u->email}");
            }
            return;
        }
        
        $this->info("Testing subscription confirmation email...");
        $this->line("User: {$user->name} ({$user->email})");
        $this->line("Subscription ID: {$subscription->id}");
        $this->line("Plan: " . ($subscription->subscriptionPlan->name ?? 'N/A'));
        
        try {
            Mail::to($user->email)->send(new SubscriptionConfirmation($user, $subscription));
            
            $this->info('✅ Subscription confirmation email sent successfully!');
            $this->line('Check the Laravel log for the email content (since MAIL_MAILER=log).');
            
        } catch (\Exception $e) {
            $this->error('❌ Failed to send subscription confirmation email:');
            $this->error($e->getMessage());
        }
    }
}
