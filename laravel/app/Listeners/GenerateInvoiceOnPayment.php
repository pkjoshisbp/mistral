<?php

namespace App\Listeners;

use App\Events\PaymentProcessed;
use App\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceOnPayment implements ShouldQueue
{
    use InteractsWithQueue;

    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentProcessed $event): void
    {
        try {
            $invoiceService = new InvoiceService();
            
            // Check if an invoice already exists for this period
            $existingInvoice = $event->subscription->invoices()
                ->where('period_start', $event->subscription->current_period_start)
                ->where('period_end', $event->subscription->current_period_end)
                ->first();
            
            if ($existingInvoice) {
                Log::info("Invoice already exists for subscription {$event->subscription->id}");
                return;
            }
            
            // Generate new invoice
            $invoice = $invoiceService->generateInvoice($event->subscription, $event->paymentData);
            
            // Send invoice email
            $invoiceService->sendInvoiceEmail($invoice);
            
            Log::info("Invoice {$invoice->invoice_number} generated and sent for subscription {$event->subscription->id}");
            
        } catch (\Exception $e) {
            Log::error("Failed to generate invoice for subscription {$event->subscription->id}: " . $e->getMessage());
            throw $e;
        }
    }
}
