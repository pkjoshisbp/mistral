<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice/Receipt</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #2563eb;">AI Support System</h1>
            <p style="color: #666;">Thank you for your payment!</p>
        </div>
        
        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="margin-top: 0;">Hello {{ $user_name }},</h2>
            <p>Thank you for your payment! Please find your invoice/receipt attached to this email for the period from <strong>{{ $period_start }}</strong> to <strong>{{ $period_end }}</strong>.</p>
            
            <div style="margin: 20px 0;">
                <p><strong>Invoice Number:</strong> {{ $invoice->invoice_number }}</p>
                <p><strong>Amount Paid:</strong> {{ $total_amount }}</p>
                <p><strong>Payment Date:</strong> {{ $invoice->payment_date->format('F d, Y') }}</p>
                <p><strong>Payment Method:</strong> {{ $invoice->payment_method }}</p>
            </div>
        </div>
        
        <div style="margin: 30px 0;">
            <h3>Subscription Details</h3>
            <p><strong>Plan:</strong> {{ $invoice->invoice_data['plan_name'] ?? 'N/A' }}</p>
            <p><strong>Billing Cycle:</strong> {{ ucfirst($invoice->invoice_data['billing_cycle'] ?? 'monthly') }}</p>
            <p><strong>Organization:</strong> {{ $invoice->invoice_data['organization_name'] ?? 'N/A' }}</p>
        </div>
        
        @if($invoice->overage_charges > 0)
        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
            <h4 style="margin-top: 0; color: #856404;">Usage Summary</h4>
            <p>Tokens Used: {{ number_format($invoice->invoice_data['tokens_used'] ?? 0) }} / {{ number_format($invoice->invoice_data['token_cap'] ?? 0) }}</p>
            <p>Overage Charges: ${{ number_format($invoice->overage_charges, 2) }}</p>
        </div>
        @endif
        
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <p>If you have any questions about this invoice, please don't hesitate to contact our support team.</p>
            <p>Thank you for choosing AI Support System!</p>
        </div>
        
        <div style="text-align: center; margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
            <p style="margin: 0; font-size: 14px; color: #666;">
                AI Support System<br>
                <a href="https://ai-chat.support" style="color: #2563eb;">ai-chat.support</a>
            </p>
        </div>
    </div>
</body>
</html>
