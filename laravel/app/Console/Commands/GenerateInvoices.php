<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Carbon\Carbon;

class GenerateInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'invoices:generate {--subscription_id=} {--send-email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate invoices for active subscriptions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $invoiceService = new InvoiceService();
        $subscriptionId = $this->option('subscription_id');
        $sendEmail = $this->option('send-email');
        
        if ($subscriptionId) {
            // Generate invoice for specific subscription
            $subscription = Subscription::with(['user', 'organization', 'subscriptionPlan'])
                ->findOrFail($subscriptionId);
            
            $this->generateForSubscription($subscription, $invoiceService, $sendEmail);
        } else {
            // Generate invoices for all active subscriptions that need billing
            $subscriptions = Subscription::with(['user', 'organization', 'subscriptionPlan'])
                ->where('status', 'active')
                ->where('current_period_end', '<=', Carbon::now()->addDays(1)) // Due for billing
                ->whereDoesntHave('invoices', function ($query) {
                    $query->where('period_start', '>=', Carbon::now()->startOfMonth())
                          ->where('period_end', '<=', Carbon::now()->endOfMonth());
                })
                ->get();
            
            $this->info("Found {$subscriptions->count()} subscriptions needing invoices");
            
            foreach ($subscriptions as $subscription) {
                $this->generateForSubscription($subscription, $invoiceService, $sendEmail);
            }
        }
        
        $this->info('Invoice generation completed!');
    }
    
    private function generateForSubscription(Subscription $subscription, InvoiceService $invoiceService, bool $sendEmail)
    {
        try {
            $this->info("Generating invoice for subscription {$subscription->id} (User: {$subscription->user->email})");
            
            $invoice = $invoiceService->generateInvoice($subscription, [
                'status' => 'paid',
                'payment_date' => Carbon::now(),
                'payment_method' => 'PayPal',
            ]);
            
            $this->info("✓ Invoice {$invoice->invoice_number} generated successfully");
            
            if ($sendEmail) {
                $invoiceService->sendInvoiceEmail($invoice);
                $this->info("✓ Invoice email sent to {$subscription->user->email}");
            }
            
        } catch (\Exception $e) {
            $this->error("✗ Failed to generate invoice for subscription {$subscription->id}: " . $e->getMessage());
        }
    }
}
