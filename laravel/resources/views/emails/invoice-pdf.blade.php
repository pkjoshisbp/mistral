<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #2563eb;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }
        .invoice-info {
            width: 100%;
            margin-bottom: 30px;
        }
        .invoice-info td {
            padding: 5px 0;
            vertical-align: top;
        }
        .invoice-info .label {
            font-weight: bold;
            width: 150px;
        }
        .billing-details {
            width: 100%;
            margin-bottom: 30px;
        }
        .billing-details td {
            padding: 10px;
            border: 1px solid #ddd;
            vertical-align: top;
        }
        .billing-details th {
            background: #f8f9fa;
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
            font-weight: bold;
        }
        .amount-table {
            width: 100%;
            margin-top: 20px;
        }
        .amount-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        .amount-table .label {
            text-align: right;
            font-weight: bold;
            width: 70%;
        }
        .amount-table .amount {
            text-align: right;
            width: 30%;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
            font-size: 14px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .status-paid {
            background: #d4edda;
            color: #155724;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            display: inline-block;
        }
        .usage-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">AI Support System</div>
        <div>Professional AI-Powered Customer Support</div>
        <div style="margin-top: 10px;">https://ai-chat.support</div>
    </div>

    <div class="invoice-title">INVOICE / RECEIPT</div>

    <table class="invoice-info">
        <tr>
            <td class="label">Invoice Number:</td>
            <td>{{ $invoice->invoice_number }}</td>
            <td class="label" style="text-align: right;">Status:</td>
            <td style="text-align: right;">
                <span class="status-paid">{{ strtoupper($invoice->status) }}</span>
            </td>
        </tr>
        <tr>
            <td class="label">Invoice Date:</td>
            <td>{{ $invoice->created_at->format('F d, Y') }}</td>
            <td class="label" style="text-align: right;">Payment Date:</td>
            <td style="text-align: right;">{{ $invoice->payment_date ? $invoice->payment_date->format('F d, Y') : 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Billing Period:</td>
            <td colspan="3">{{ $invoice->period_start->format('F d, Y') }} - {{ $invoice->period_end->format('F d, Y') }}</td>
        </tr>
    </table>

    <table class="billing-details">
        <tr>
            <th style="width: 50%;">Bill To</th>
            <th style="width: 50%;">Service Details</th>
        </tr>
        <tr>
            <td>
                <strong>{{ $invoice->invoice_data['user_name'] ?? $invoice->user->name }}</strong><br>
                {{ $invoice->invoice_data['user_email'] ?? $invoice->user->email }}<br>
                @if(isset($invoice->invoice_data['organization_name']))
                    <br><strong>Organization:</strong><br>
                    {{ $invoice->invoice_data['organization_name'] }}
                @endif
            </td>
            <td>
                <strong>{{ $invoice->invoice_data['plan_name'] ?? 'N/A' }}</strong><br>
                Billing Cycle: {{ ucfirst($invoice->invoice_data['billing_cycle'] ?? 'monthly') }}<br>
                @if($invoice->payment_method)
                    Payment Method: {{ $invoice->payment_method }}<br>
                @endif
                @if($invoice->payment_transaction_id)
                    Transaction ID: {{ $invoice->payment_transaction_id }}
                @endif
            </td>
        </tr>
    </table>

    @if(isset($invoice->invoice_data['tokens_used']) && $invoice->invoice_data['tokens_used'] > 0)
    <div class="usage-info">
        <strong>Usage Summary for {{ $invoice->billing_period }}</strong><br>
        Tokens Used: {{ number_format($invoice->invoice_data['tokens_used']) }} / {{ number_format($invoice->invoice_data['token_cap'] ?? 0) }}<br>
        @if($invoice->overage_charges > 0)
            Overage Tokens: {{ number_format(max(0, $invoice->invoice_data['tokens_used'] - $invoice->invoice_data['token_cap'])) }}<br>
        @endif
    </div>
    @endif

    <table class="amount-table">
        <tr>
            <td class="label">Subscription ({{ $invoice->billing_period }}):</td>
            <td class="amount">${{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        @if($invoice->overage_charges > 0)
        <tr>
            <td class="label">Overage Charges:</td>
            <td class="amount">${{ number_format($invoice->overage_charges, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="label">Total Amount:</td>
            <td class="amount">${{ number_format($invoice->total_amount, 2) }}</td>
        </tr>
    </table>

    @if(isset($invoice->invoice_data['plan_features']) && is_array($invoice->invoice_data['plan_features']))
    <div style="margin-top: 30px;">
        <strong>Plan Features:</strong>
        <ul style="margin-top: 10px;">
            @foreach($invoice->invoice_data['plan_features'] as $feature)
                <li>{{ $feature }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <div class="footer">
        <p>Thank you for your business!</p>
        <p>For support inquiries, please contact us at support@ai-chat.support</p>
        <p>AI Support System - Revolutionizing customer support with intelligent AI conversations.</p>
    </div>
</body>
</html>
