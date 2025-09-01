<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;

class InvoiceService
{
    public function generateInvoice(Subscription $subscription, array $invoiceData = [])
    {
        $invoice = new Invoice();
        $invoice->invoice_number = $invoice->generateInvoiceNumber();
        $invoice->subscription_id = $subscription->id;
        $invoice->user_id = $subscription->user_id;
        $invoice->organization_id = $subscription->organization_id;
        
        // Set billing period
        $startDate = $subscription->current_period_start;
        $endDate = $subscription->current_period_end;
        $invoice->period_start = $startDate;
        $invoice->period_end = $endDate;
        $invoice->billing_period = $startDate->format('F Y');
        
        // Calculate amounts
        $planPrice = $subscription->billing_cycle === 'yearly' 
            ? $subscription->subscriptionPlan->yearly_price 
            : $subscription->subscriptionPlan->monthly_price;
            
        $invoice->subtotal = $planPrice;
        $invoice->overage_charges = $subscription->overage_charges ?? 0;
        $invoice->total_amount = $invoice->subtotal + $invoice->overage_charges;
        
        // Set payment details
        $invoice->status = $invoiceData['status'] ?? 'paid';
        $invoice->payment_date = $invoiceData['payment_date'] ?? Carbon::now();
        $invoice->payment_method = $invoiceData['payment_method'] ?? 'PayPal';
        $invoice->payment_transaction_id = $invoiceData['transaction_id'] ?? null;
        
        // Store detailed invoice data
        $invoice->invoice_data = [
            'plan_name' => $subscription->subscriptionPlan->name,
            'plan_features' => $subscription->subscriptionPlan->features,
            'tokens_used' => $subscription->tokens_used_this_period,
            'token_cap' => $subscription->subscriptionPlan->token_cap_monthly,
            'billing_cycle' => $subscription->billing_cycle,
            'organization_name' => $subscription->organization->name ?? 'N/A',
            'user_email' => $subscription->user->email,
            'user_name' => $subscription->user->name,
        ];
        
        $invoice->save();
        
        return $invoice;
    }
    
    public function generatePDF(Invoice $invoice)
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        $html = View::make('emails.invoice-pdf', compact('invoice'))->render();
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return $dompdf->output();
    }
    
    public function sendInvoiceEmail(Invoice $invoice)
    {
        $pdfContent = $this->generatePDF($invoice);
        
        $emailData = [
            'invoice' => $invoice,
            'user_name' => $invoice->user->name,
            'period_start' => $invoice->period_start->format('F d, Y'),
            'period_end' => $invoice->period_end->format('F d, Y'),
            'total_amount' => $invoice->getFormattedTotalAttribute(),
        ];
        
        Mail::send('emails.invoice', $emailData, function ($message) use ($invoice, $pdfContent) {
            $message->to($invoice->user->email, $invoice->user->name)
                ->subject('Invoice/Receipt for your AI Support subscription')
                ->attachData($pdfContent, "invoice-{$invoice->invoice_number}.pdf", [
                    'mime' => 'application/pdf',
                ]);
        });
        
        return true;
    }
}
