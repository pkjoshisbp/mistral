<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'widget/*',
        'paypal/webhook',
        'razorpay/webhook',
        'razorpay/webhook-test',
        'email/webhooks/*',
        'analytics/track', // Public analytics endpoint (widget + site events) - stateless
        'shopify/webhooks', // Shopify webhooks verified via HMAC
    ];
}
