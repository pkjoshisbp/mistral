# Invoice/Receipt System Documentation

## Overview
The invoice system automatically generates PDF invoices and receipts for subscription payments. It serves as both an invoice (for advance payments) and a receipt (for completed payments).

## Features
- **Automatic Invoice Generation**: Generates invoices when payments are processed
- **PDF Generation**: Creates professional PDF invoices with company branding
- **Email Delivery**: Automatically sends invoice PDFs via email
- **Multi-language Support**: Supports the same languages as the main application
- **Admin Management**: Complete admin interface for viewing and managing invoices

## Invoice Structure
Each invoice includes:
- Invoice number (format: INV-YYYYMM-XXXX)
- Customer information (name, email, organization)
- Subscription details (plan, billing cycle, period)
- Usage summary (tokens used, overage charges)
- Payment information (date, method, transaction ID)
- Professional company branding

## Usage

### Automatic Generation
Invoices are automatically generated when:
1. PaymentProcessed event is fired
2. Manual command is run
3. Scheduled billing occurs

### Manual Generation
Generate invoices manually using the Artisan command:

```bash
# Generate for all subscriptions needing invoices
php artisan invoices:generate

# Generate for specific subscription
php artisan invoices:generate --subscription_id=1

# Generate and send email
php artisan invoices:generate --subscription_id=1 --send-email
```

### Admin Interface
Access the invoice management interface at:
- URL: `/admin/invoices`
- Features:
  - View all invoices
  - Search and filter
  - Download PDFs
  - Resend emails
  - View detailed invoice information

## Event Integration
Fire the PaymentProcessed event in your payment controllers:

```php
use App\Events\PaymentProcessed;

// After successful payment processing
event(new PaymentProcessed($subscription, [
    'status' => 'paid',
    'payment_date' => now(),
    'payment_method' => 'PayPal',
    'transaction_id' => $paypalTransactionId
]));
```

## Email Template
The system sends professional emails with:
- PDF invoice attachment
- Email body with payment confirmation
- Period information (start date - end date)
- Thank you message
- Company branding and contact information

## Database Tables
- `invoices`: Stores invoice data
- Related to: `subscriptions`, `users`, `organizations`

## Configuration
Email templates are located in:
- `resources/views/emails/invoice.blade.php` - Email body
- `resources/views/emails/invoice-pdf.blade.php` - PDF template

## Security
- Admin-only access to invoice management
- User data is protected and properly validated
- PDF generation is secure and doesn't expose sensitive data

## Troubleshooting
- Check SMTP configuration for email delivery
- Verify dompdf package is installed
- Ensure proper file permissions for PDF generation
- Check logs for detailed error messages
