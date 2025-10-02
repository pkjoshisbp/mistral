<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalController extends Controller
{
    private $paypalBaseUrl;
    private $clientId;
    private $clientSecret;

    public function __construct()
    {
        $this->paypalBaseUrl = env('PAYPAL_MODE', 'sandbox') === 'live' 
            ? 'https://api.paypal.com' 
            : 'https://api.sandbox.paypal.com';
        $this->clientId = env('PAYPAL_CLIENT_ID');
        $this->clientSecret = env('PAYPAL_CLIENT_SECRET');
    }

    /**
     * Get PayPal access token
     */
    private function getAccessToken()
    {
        $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
            ->asForm()
            ->post($this->paypalBaseUrl . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials'
            ]);

        if ($response->successful()) {
            return $response->json()['access_token'];
        }

        throw new \Exception('Failed to get PayPal access token');
    }

    /**
     * Create PayPal subscription
     */
    public function createSubscription(Request $request)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            $user = Auth::user();
            $accessToken = $this->getAccessToken();
            
            // Get billing cycle from request (default to monthly)
            $billingCycle = $request->input('billing_cycle', 'monthly');
            
            // Get price based on billing cycle
            $price = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

            // Debug logging: what we are about to send (no secrets)
            Log::info('PayPal createSubscription init', [
                'mode' => env('PAYPAL_MODE', 'sandbox'),
                'base_url' => $this->paypalBaseUrl,
                'client_id_tail' => substr((string)env('PAYPAL_CLIENT_ID'), -6),
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_slug' => $plan->slug,
                'billing_cycle' => $billingCycle,
                'calculated_price' => $price,
            ]);

            // For subscription plans (not PAYG), create proper recurring PayPal subscription
            if ($plan->slug !== 'payg' && $plan->paypal_plan_id) {
                // Create PayPal subscription using the billing plan
                $subscriptionData = [
                    'plan_id' => $plan->paypal_plan_id,
                    'start_time' => now()->addMinute()->toISOString(), // Start 1 minute from now
                    'quantity' => '1',
                    'shipping_amount' => [
                        'currency_code' => 'USD',
                        'value' => '0.00'
                    ],
                    'subscriber' => [
                        'name' => [
                            'given_name' => explode(' ', $user->name)[0] ?? $user->name,
                            'surname' => explode(' ', $user->name, 2)[1] ?? ''
                        ],
                        'email_address' => $user->email
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'locale' => 'en-US',
                        'landing_page' => 'BILLING',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'SUBSCRIBE_NOW',
                        'payment_method' => [
                            'payer_selected' => 'PAYPAL',
                            'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                        ],
                        'return_url' => route('paypal.success') . '?plan_id=' . $plan->id . '&billing_cycle=' . $billingCycle,
                        'cancel_url' => route('paypal.cancel')
                    ],
                    'custom_id' => 'user_' . $user->id . '_plan_' . $plan->id . '_' . $billingCycle
                ];

                // Log a sanitized preview of the subscription payload
                Log::info('PayPal subscription payload preview', [
                    'plan_id' => $subscriptionData['plan_id'] ?? null,
                    'subscriber_email' => $subscriptionData['subscriber']['email_address'] ?? null,
                    'application_context' => $subscriptionData['application_context'] ?? null,
                    'custom_id' => $subscriptionData['custom_id'] ?? null,
                ]);

                $response = Http::withToken($accessToken)
                    ->post($this->paypalBaseUrl . '/v1/billing/subscriptions', $subscriptionData);

                if ($response->successful()) {
                    $paypalSubscription = $response->json();
                    Log::info('PayPal subscription created', [
                        'id' => $paypalSubscription['id'] ?? null,
                        'links' => collect($paypalSubscription['links'] ?? [])->pluck('rel')->toArray(),
                    ]);
                    
                    // Create local subscription record in pending status
                    $localSubscription = Subscription::create([
                        'user_id' => $user->id,
                        'organization_id' => $user->organization_id ?? 3,
                        'subscription_plan_id' => $plan->id,
                        'paypal_subscription_id' => $paypalSubscription['id'],
                        'status' => 'pending',
                        'billing_cycle' => $billingCycle,
                        'current_period_start' => now(),
                        'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                        'tokens_used_this_period' => 0
                    ]);

                    foreach ($paypalSubscription['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            Log::info('PayPal subscription approve URL', ['href' => $link['href']]);
                            return response()->json([
                                'success' => true,
                                'approval_url' => $link['href'],
                                'mode' => 'subscription'
                            ]);
                        }
                    }
                } else {
                    Log::error('PayPal subscription create error', ['body' => $response->body()]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to create PayPal subscription. Please try again or contact support.'
                    ], 500);
                }
            }

            // For PAYG, create one-time payment for credits and treat as credit purchase
            $paymentData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($price, 2, '.', '')
                        ],
                        'description' => $plan->name . ' - Credit Purchase',
                        'custom_id' => 'user_' . $user->id . '_payg_' . $plan->id
                    ]
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    // Return to credit-success to allocate credits immediately like credit packages
                    'return_url' => route('paypal.credit-success') . '?payg_plan_id=' . $plan->id,
                    'cancel_url' => route('paypal.cancel')
                ]
            ];

            Log::info('PayPal createSubscription (PAYG) order payload preview', [
                'purchase_units_0' => [
                    'amount' => $paymentData['purchase_units'][0]['amount'],
                    'description' => $paymentData['purchase_units'][0]['description'],
                    'custom_id' => $paymentData['purchase_units'][0]['custom_id'],
                ],
                'application_context' => $paymentData['application_context'],
            ]);

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders', $paymentData);

            if ($response->successful()) {
                $paypalOrder = $response->json();
                Log::info('PayPal PAYG order created', [
                    'id' => $paypalOrder['id'] ?? null,
                    'custom_id' => $paymentData['purchase_units'][0]['custom_id'] ?? null,
                ]);
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        Log::info('PayPal PAYG approve URL', ['href' => $link['href']]);
                        return response()->json([
                            'success' => true,
                            'approval_url' => $link['href'],
                            'mode' => 'payg_credits'
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate PayPal payment.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('PayPal payment creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create PayPal one-time payment for credit packages
     */
    public function createCreditPayment(Request $request)
    {
        try {
            $package = \App\Models\CreditPackage::findOrFail($request->package_id);
            $user = Auth::user();
            $accessToken = $this->getAccessToken();
            
            Log::info('PayPal createCreditPayment init', [
                'mode' => env('PAYPAL_MODE', 'sandbox'),
                'base_url' => $this->paypalBaseUrl,
                'client_id_tail' => substr((string)env('PAYPAL_CLIENT_ID'), -6),
                'user_id' => $user->id,
                'package_id' => $package->id,
                'usd_price' => $package->usd_price,
                'tokens' => $package->tokens,
            ]);

            // Create one-time payment order
            $paymentData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($package->usd_price, 2, '.', '')
                        ],
                        'description' => $package->name . ' - Credit Package',
                        'custom_id' => 'user_' . $user->id . '_credit_' . $package->id
                    ]
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('paypal.credit-success') . '?package_id=' . $package->id,
                    'cancel_url' => route('paypal.cancel')
                ]
            ];

            Log::info('PayPal createCreditPayment order payload preview', [
                'purchase_units_0' => [
                    'amount' => $paymentData['purchase_units'][0]['amount'],
                    'description' => $paymentData['purchase_units'][0]['description'],
                    'custom_id' => $paymentData['purchase_units'][0]['custom_id'],
                ],
                'application_context' => $paymentData['application_context'],
            ]);

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders', $paymentData);

            if ($response->successful()) {
                $paypalOrder = $response->json();
                Log::info('PayPal credit order created', [
                    'id' => $paypalOrder['id'] ?? null,
                    'custom_id' => $paymentData['purchase_units'][0]['custom_id'] ?? null,
                ]);
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        Log::info('PayPal credit approve URL', ['href' => $link['href']]);
                        return response()->json([
                            'success' => true,
                            'approval_url' => $link['href'],
                            'mode' => 'credit_purchase'
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate PayPal credit payment.'
            ], 500);
        } catch (\Exception $e) {
            Log::error('PayPal credit payment creation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Server-side redirect flow to PayPal approval for credit packages (JS fallback)
     */
    public function creditCheckoutRedirect(Request $request, int $packageId)
    {
        try {
            $package = \App\Models\CreditPackage::findOrFail($packageId);
            $user = Auth::user();
            $accessToken = $this->getAccessToken();

            Log::info('PayPal creditCheckoutRedirect init', [
                'user_id' => optional($user)->id,
                'package_id' => $package->id,
                'usd_price' => $package->usd_price,
            ]);

            $paymentData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($package->usd_price, 2, '.', '')
                        ],
                        'description' => $package->name . ' - Credit Package',
                        'custom_id' => 'user_' . $user->id . '_credit_' . $package->id
                    ]
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'landing_page' => 'BILLING',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'PAY_NOW',
                    'return_url' => route('paypal.credit-success') . '?package_id=' . $package->id,
                    'cancel_url' => route('paypal.cancel')
                ]
            ];

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders', $paymentData);

            if ($response->successful()) {
                $paypalOrder = $response->json();
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        Log::info('PayPal credit redirecting to approval', ['href' => $link['href']]);
                        return redirect()->away($link['href']);
                    }
                }
            } else {
                Log::error('PayPal creditCheckoutRedirect create order failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return redirect()->route('credits-and-services')
                ->with('error', 'Unable to start PayPal payment. Please try again.');
        } catch (\Throwable $e) {
            Log::error('PayPal creditCheckoutRedirect error: ' . $e->getMessage());
            return redirect()->route('credits-and-services')
                ->with('error', 'Unable to start PayPal payment.');
        }
    }

    /**
     * Handle successful payment
     */
    public function handleSuccess(Request $request)
    {
        try {
            $token = $request->token; // Could be order id or subscription token
            $planId = $request->plan_id;
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $accessToken = $this->getAccessToken();

            // First, handle Subscription API returns
            $subscriptionId = $request->get('subscription_id');
            if (!$subscriptionId && $token && str_starts_with((string)$token, 'I-')) {
                // PayPal subscriptions often return token like I-XXXX
                $subscriptionId = $token;
            }

            if ($subscriptionId) {
                Log::info('PayPal subscription success return received', [
                    'subscription_id' => $subscriptionId,
                    'plan_id' => $planId,
                    'billing_cycle' => $billingCycle,
                ]);

                $subRes = Http::withToken($accessToken)
                    ->get($this->paypalBaseUrl . '/v1/billing/subscriptions/' . $subscriptionId);

                if ($subRes->successful()) {
                    $data = $subRes->json();
                    $status = $data['status'] ?? 'APPROVAL_PENDING';

                    $user = Auth::user();
                    $plan = $planId ? SubscriptionPlan::find($planId) : null;

                    // Update the pending local record created at createSubscription()
                    $local = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
                    if (!$local && $user && $plan) {
                        $local = Subscription::create([
                            'user_id' => $user->id,
                            'organization_id' => $user->organization_id ?? 3,
                            'subscription_plan_id' => $plan->id,
                            'paypal_subscription_id' => $subscriptionId,
                            'status' => strtolower($status),
                            'billing_cycle' => $billingCycle,
                            'current_period_start' => now(),
                            'current_period_end' => $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                            'tokens_used_this_period' => 0
                        ]);
                    } elseif ($local) {
                        $local->update(['status' => strtolower($status)]);
                    }

                    if ($status === 'ACTIVE') {
                        return redirect()->route('customer.dashboard')
                            ->with('success', 'Subscription activated successfully.');
                    }
                    return redirect()->route('customer.dashboard')
                        ->with('info', 'Subscription approval received. Activation will complete shortly.');
                }

                Log::error('Failed to fetch PayPal subscription after success return', [
                    'subscription_id' => $subscriptionId,
                    'status' => $subRes->status(),
                    'body' => $subRes->body(),
                ]);
                return redirect()->route('customer.subscription')
                    ->with('info', 'Subscription submitted. You will receive confirmation shortly.');
            }

            // Otherwise, fallback to order capture (legacy/safety)
            if (!$token) {
                return redirect()->route('customer.subscription')
                    ->with('error', 'Missing PayPal token.');
            }

            $response = Http::withToken($accessToken)
                ->withBody('{}', 'application/json')
                ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $token . '/capture');

            if ($response->successful()) {
                $paypalOrder = $response->json();
                if (($paypalOrder['status'] ?? null) === 'COMPLETED') {
                    $plan = $planId ? SubscriptionPlan::find($planId) : null;
                    if ($plan) {
                        $user = Auth::user();
                        $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();
                        Subscription::create([
                            'user_id' => $user->id,
                            'organization_id' => $user->organization_id ?? 3,
                            'subscription_plan_id' => $plan->id,
                            'paypal_subscription_id' => $paypalOrder['id'],
                            'status' => 'active',
                            'billing_cycle' => $billingCycle,
                            'current_period_start' => now(),
                            'current_period_end' => $periodEnd,
                            'tokens_used_this_period' => 0
                        ]);
                    }
                    return redirect()->route('customer.dashboard')
                        ->with('success', 'Payment successful! Your plan has been activated.');
                }
            }
            return redirect()->route('customer.subscription')
                ->with('error', 'Payment could not be completed. Please try again.');
        } catch (\Exception $e) {
            Log::error('PayPal payment completion failed: ' . $e->getMessage());
            return redirect()->route('customer.subscription')
                ->with('error', 'An error occurred while completing your payment.');
        }
    }

    /**
     * Handle cancelled payment
     */
    public function handleCancel(Request $request)
    {
        return redirect()->route('customer.subscription')
            ->with('info', 'Payment was cancelled. You can try again anytime.');
    }

    /**
     * Admin-only: manually capture a PayPal order and allocate credits.
     * Useful when the buyer doesn't return to credit-success and webhook is delayed.
     */
    public function adminCapture(Request $request)
    {
        try {
            $user = Auth::user();
            // Route is already protected by auth+admin middleware

            $orderId = (string) $request->input('order_id');
            if (!$orderId) {
                return response()->json(['success' => false, 'message' => 'order_id is required'], 422);
            }

            // If already credited, exit early
            if (\App\Models\CreditTransaction::where('reference_id', $orderId)->exists()) {
                Log::warning('Admin manual capture: order already processed', ['order_id' => $orderId]);
                return response()->json(['success' => true, 'message' => 'Order already processed; no action taken']);
            }

            $accessToken = $this->getAccessToken();

            // Try to capture first
            $captureRes = Http::withToken($accessToken)
                ->withBody('{}', 'application/json')
                ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId . '/capture');

            $order = null;
            if ($captureRes->successful()) {
                $order = $captureRes->json();
            } else {
                // Maybe already completed; fetch order details
                Log::warning('Admin manual capture: capture failed, fetching order', [
                    'order_id' => $orderId,
                    'status' => $captureRes->status(),
                    'body' => $captureRes->body(),
                ]);
                $getRes = Http::withToken($accessToken)
                    ->get($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId);
                if (!$getRes->successful()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to capture or fetch PayPal order',
                        'status' => $getRes->status(),
                        'body' => $getRes->body(),
                    ], 502);
                }
                $order = $getRes->json();
            }

            $status = $order['status'] ?? null;
            Log::info('Admin manual capture: order status', ['order_id' => $orderId, 'status' => $status]);
            if ($status !== 'COMPLETED') {
                $approveUrl = null;
                try {
                    foreach (($order['links'] ?? []) as $lnk) {
                        if (($lnk['rel'] ?? '') === 'approve') { $approveUrl = $lnk['href'] ?? null; break; }
                    }
                } catch (\Throwable $e) { /* ignore */ }
                return response()->json([
                    'success' => false,
                    'message' => 'Order not completed: ' . ($status ?? 'UNKNOWN'),
                    'order_status' => $status,
                    'approve_url' => $approveUrl,
                ], 400);
            }

            // Allocate credits based on custom_id pattern (credit package or PAYG)
            $purchaseUnit = $order['purchase_units'][0] ?? [];
            $customId = $purchaseUnit['custom_id'] ?? '';

            $creditPackage = null; $paygPlan = null; $targetUser = null; $tokensToAdd = 0;

            if ($customId && preg_match('/user_(\d+)_credit_(\d+)/', $customId, $m)) {
                $targetUser = \App\Models\User::find((int)$m[1]);
                $creditPackage = \App\Models\CreditPackage::find((int)$m[2]);
                if ($creditPackage) { $tokensToAdd = (int)$creditPackage->tokens; }
            } elseif ($customId && preg_match('/user_(\d+)_payg_(\d+)/', $customId, $m2)) {
                $targetUser = \App\Models\User::find((int)$m2[1]);
                $paygPlan = \App\Models\SubscriptionPlan::find((int)$m2[2]);
                if ($paygPlan) { $tokensToAdd = (int)($paygPlan->token_cap_monthly ?: 100000); }
            }

            if (!$targetUser) { $targetUser = $user; }

            if (!$targetUser || ($tokensToAdd <= 0)) {
                Log::error('Admin manual capture: unable to determine credit allocation', [
                    'order_id' => $orderId,
                    'custom_id' => $customId,
                ]);
                return response()->json(['success' => false, 'message' => 'Unable to determine credits to allocate'], 400);
            }

            $uc = \App\Models\UserCredit::getOrCreateForUser($targetUser->id);
            $uc->addCredits($tokensToAdd, 'Manual capture credit allocation (PayPal)', [
                'credit_package_id' => $creditPackage->id ?? null,
                'credits' => $tokensToAdd,
                'payment_method' => 'paypal',
                'reference_id' => $orderId,
                'notes' => $creditPackage ? ('Package: ' . ($creditPackage->name ?? '')) : ($paygPlan ? ('PAYG Plan: ' . ($paygPlan->name ?? '')) : 'Manual allocation'),
            ]);

            Log::info('Admin manual capture: credits allocated', [
                'order_id' => $orderId,
                'user_id' => $targetUser->id,
                'tokens' => $tokensToAdd,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Credits allocated successfully',
                'order_status' => $status,
                'user_id' => $targetUser->id,
                'tokens' => $tokensToAdd,
                'custom_id' => $customId,
            ]);
        } catch (\Throwable $e) {
            Log::error('Admin manual capture error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => 'Internal error'], 500);
        }
    }

    /**
     * Handle successful credit purchase
     */
    public function handleCreditSuccess(Request $request)
    {
        try {
            // PayPal returns ?token={ORDER_ID} for v2 checkout orders
            $orderId = $request->get('token') ?: $request->get('order_id');
            $packageId = $request->get('package_id');
            $paygPlanId = $request->get('payg_plan_id');

            Log::info('PayPal credit-success return received', [
                'token' => $orderId,
                'package_id' => $packageId,
                'query' => $request->query(),
            ]);

            if (!$orderId) {
                return redirect()->route('customer.dashboard')->with('error', 'Missing PayPal order token.');
            }

            // Idempotency: if we've already recorded a transaction with this order id, do not double-credit
            $alreadyCredited = \App\Models\CreditTransaction::where('reference_id', $orderId)->exists();
            if ($alreadyCredited) {
                Log::warning('PayPal credit already processed for order', ['order_id' => $orderId]);
                return redirect()->route('customer.dashboard')->with('success', 'Credits have been added to your account.');
            }

            $accessToken = $this->getAccessToken();

            // Capture the PayPal order
            $captureRes = Http::withToken($accessToken)
                ->withBody('{}', 'application/json')
                ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId . '/capture');

            $order = null;
            if ($captureRes->successful()) {
                $order = $captureRes->json();
            } else {
                // If capture fails (possibly already captured), try fetching order details
                Log::warning('PayPal capture failed, attempting to fetch order details', [
                    'order_id' => $orderId,
                    'status' => $captureRes->status(),
                    'body' => $captureRes->body(),
                ]);
                $getRes = Http::withToken($accessToken)
                    ->get($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId);
                if ($getRes->successful()) {
                    $order = $getRes->json();
                } else {
                    Log::error('PayPal order fetch failed after capture failure', [
                        'order_id' => $orderId,
                        'status' => $getRes->status(),
                        'body' => $getRes->body(),
                    ]);
                    return redirect()->route('customer.dashboard')->with('error', 'Payment capture failed. Please contact support.');
                }
            }
            Log::info('PayPal capture response (credit purchase)', [
                'order_id' => $orderId,
                'status' => $order['status'] ?? null,
            ]);

            if (($order['status'] ?? null) !== 'COMPLETED') {
                return redirect()->route('customer.dashboard')->with('error', 'Payment not completed.');
            }

            // Determine user and package from custom_id when available
            $purchaseUnit = $order['purchase_units'][0] ?? null;
            $customId = $purchaseUnit['custom_id'] ?? null;
            $user = Auth::user();
            $creditPackage = null;
            $tokensToAdd = null;

            if ($customId && preg_match('/user_(\d+)_credit_(\d+)/', $customId, $m)) {
                $userId = (int) $m[1];
                $pkgId = (int) $m[2];
                $user = \App\Models\User::find($userId) ?: $user;
                $creditPackage = \App\Models\CreditPackage::find($pkgId);
            }

            // PAYG from subscription_plans should behave like 100k credit allocation (or plan token cap)
            $paygPlan = null;
            if (!$creditPackage) {
                if ($customId && preg_match('/user_(\d+)_payg_(\d+)/', $customId, $m2)) {
                    $userId = (int) $m2[1];
                    $planIdFromCustom = (int) $m2[2];
                    $user = \App\Models\User::find($userId) ?: $user;
                    $paygPlan = \App\Models\SubscriptionPlan::find($planIdFromCustom);
                } elseif ($paygPlanId) {
                    $paygPlan = \App\Models\SubscriptionPlan::find($paygPlanId);
                }
            }

            // Fallback to package_id query param
            if (!$creditPackage && $packageId) {
                $creditPackage = \App\Models\CreditPackage::find($packageId);
            }

            if (!$user || (!$creditPackage && !$paygPlan)) {
                Log::error('PayPal credit-success: missing user or credit package after capture', [
                    'order_id' => $orderId,
                    'custom_id' => $customId,
                    'package_id' => $packageId,
                    'payg_plan_id' => $paygPlanId,
                    'user_id' => optional($user)->id,
                ]);
                return redirect()->route('customer.dashboard')->with('error', 'Payment completed, but we could not allocate credits. Please contact support with your PayPal receipt.');
            }

            if ($creditPackage) {
                $tokensToAdd = $creditPackage->tokens;
            } else {
                // Default PAYG allocation based on plan token cap or fallback to 100k tokens
                $tokensToAdd = $paygPlan && $paygPlan->token_cap_monthly ? (int)$paygPlan->token_cap_monthly : 100000;
                $creditPackage = (object) [
                    'id' => null,
                    'name' => $paygPlan ? ($paygPlan->name . ' (PAYG)') : 'PAYG Credits',
                    'usd_price' => $paygPlan ? $paygPlan->monthly_price : 5.00,
                ];
            }

            // Add credits idempotently
            $userCredit = \App\Models\UserCredit::getOrCreateForUser($user->id);
            $userCredit->addCredits($tokensToAdd, 'Credit package purchase (PayPal)', [
                'credit_package_id' => $creditPackage->id,
                'credits' => $tokensToAdd,
                'payment_method' => 'paypal',
                'reference_id' => $orderId,
                'notes' => 'Package: ' . ($creditPackage->name ?? 'N/A') . ' | USD ' . ($creditPackage->usd_price ?? '0')
            ]);

            Log::info('Credits added after PayPal capture', [
                'user_id' => $user->id,
                'package_id' => $creditPackage->id,
                'order_id' => $orderId,
                'tokens' => $tokensToAdd,
            ]);

            return redirect()->route('customer.dashboard')->with('success', 'Credits purchased successfully!');
        } catch (\Throwable $e) {
            Log::error('PayPal Credit Success Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('customer.dashboard')->with('error', 'Credit purchase processing error.');
        }
    }

    /**
     * Handle PayPal webhooks
     */
    public function handleWebhook(Request $request)
    {
        try {
            $event = $request->all();
            
            Log::info('PayPal webhook received', $event);

            switch ($event['event_type']) {
                case 'BILLING.SUBSCRIPTION.ACTIVATED':
                    $this->handleSubscriptionActivated($event);
                    break;
                case 'BILLING.SUBSCRIPTION.CANCELLED':
                    $this->handleSubscriptionCancelled($event);
                    break;
                case 'BILLING.SUBSCRIPTION.SUSPENDED':
                    $this->handleSubscriptionSuspended($event);
                    break;
                case 'PAYMENT.SALE.COMPLETED':
                    $this->handlePaymentCompleted($event);
                    break;
                case 'CHECKOUT.ORDER.APPROVED':
                    $this->handleCheckoutOrderApproved($event);
                    break;
                case 'CHECKOUT.ORDER.COMPLETED':
                    $this->handleCheckoutOrderCompleted($event);
                    break;
            }

            return response('OK');
        } catch (Exception $e) {
            Log::error('PayPal webhook processing failed: ' . $e->getMessage());
            return response('Error', 400);
        }
    }

    /**
     * Handle PayPal CHECKOUT.ORDER.APPROVED event for subscription purchase
     */
    private function handleCheckoutOrderApproved($event)
    {
        $resource = $event['resource'];
        $purchaseUnit = $resource['purchase_units'][0] ?? null;
        if (!$purchaseUnit || empty($purchaseUnit['custom_id'])) {
            Log::error('PayPal webhook: custom_id missing in CHECKOUT.ORDER.APPROVED');
            return;
        }
        $customId = $purchaseUnit['custom_id'];
        // Handle subscription approvals (legacy)
        if (preg_match('/user_(\d+)_plan_(\d+)_(\w+)/', $customId, $matches)) {
            $userId = $matches[1];
            $planId = $matches[2];
            $cycle = $matches[3];
            $user = \App\Models\User::find($userId);
            $plan = \App\Models\SubscriptionPlan::find($planId);
            if ($user && $plan) {
                $subscription = \App\Models\Subscription::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'subscription_plan_id' => $plan->id,
                        'paypal_subscription_id' => $resource['id'],
                    ],
                    [
                        'status' => 'active',
                        'current_period_start' => now(),
                        'current_period_end' => $cycle === 'yearly' ? now()->addYear() : now()->addMonth(),
                    ]
                );
                Log::info('PayPal subscription activated via CHECKOUT.ORDER.APPROVED', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'subscription_id' => $subscription->id,
                ]);
                return;
            }
            Log::error('PayPal webhook: user or plan not found for custom_id', ['custom_id' => $customId]);
            return;
        }

        // New: Capture credit or PAYG orders on APPROVED (buyer might not return to our site)
        $orderId = $resource['id'] ?? null;
        if (!$orderId) {
            Log::error('PayPal webhook: missing order id on APPROVED');
            return;
        }

        try {
            $already = \App\Models\CreditTransaction::where('reference_id', $orderId)->exists();
            if ($already) {
                Log::warning('Skipping capture on APPROVED; already processed', ['order_id' => $orderId]);
                return;
            }
        } catch (\Throwable $e) {
            // proceed anyway
        }

        // If custom id indicates credit package
        if (preg_match('/user_(\d+)_credit_(\d+)/', $customId, $m)) {
            $user = \App\Models\User::find((int)$m[1]);
            $package = \App\Models\CreditPackage::find((int)$m[2]);
            if (!$user || !$package) {
                Log::error('PayPal webhook APPROVED: user or package not found', ['custom_id' => $customId]);
                return;
            }
            try {
                $access = $this->getAccessToken();
                $capRes = Http::withToken($access)
                    ->withBody('{}', 'application/json')
                    ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
                if (!$capRes->successful()) {
                    Log::error('PayPal APPROVED capture failed (credit)', [
                        'order_id' => $orderId,
                        'status' => $capRes->status(),
                        'body' => $capRes->body(),
                    ]);
                    return;
                }
                $data = $capRes->json();
                if (($data['status'] ?? null) !== 'COMPLETED') {
                    Log::warning('PayPal APPROVED capture not completed (credit)', ['order_id' => $orderId, 'status' => $data['status'] ?? null]);
                    return;
                }
                $uc = \App\Models\UserCredit::getOrCreateForUser($user->id);
                $uc->addCredits((int)$package->tokens, 'Credit package purchase (PayPal webhook APPROVED)', [
                    'credit_package_id' => $package->id,
                    'credits' => (int)$package->tokens,
                    'payment_method' => 'paypal',
                    'reference_id' => $orderId,
                    'notes' => 'Package: ' . ($package->name ?? 'N/A') . ' | USD ' . ($package->usd_price ?? '0')
                ]);
                Log::info('PayPal credit captured via APPROVED webhook', ['order_id' => $orderId, 'user_id' => $user->id, 'package_id' => $package->id]);
                return;
            } catch (\Throwable $e) {
                Log::error('PayPal APPROVED processing error (credit): ' . $e->getMessage());
                return;
            }
        }

        // Or PAYG treated as credits
        if (preg_match('/user_(\d+)_payg_(\d+)/', $customId, $m2)) {
            $user = \App\Models\User::find((int)$m2[1]);
            $plan = \App\Models\SubscriptionPlan::find((int)$m2[2]);
            if (!$user || !$plan) {
                Log::error('PayPal webhook APPROVED: user or PAYG plan not found', ['custom_id' => $customId]);
                return;
            }
            try {
                $access = $this->getAccessToken();
                $capRes = Http::withToken($access)
                    ->withBody('{}', 'application/json')
                    ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $orderId . '/capture');
                if (!$capRes->successful()) {
                    Log::error('PayPal APPROVED capture failed (PAYG)', [
                        'order_id' => $orderId,
                        'status' => $capRes->status(),
                        'body' => $capRes->body(),
                    ]);
                    return;
                }
                $data = $capRes->json();
                if (($data['status'] ?? null) !== 'COMPLETED') {
                    Log::warning('PayPal APPROVED capture not completed (PAYG)', ['order_id' => $orderId, 'status' => $data['status'] ?? null]);
                    return;
                }
                $tokens = (int)($plan->token_cap_monthly ?: 100000);
                $uc = \App\Models\UserCredit::getOrCreateForUser($user->id);
                $uc->addCredits($tokens, 'PAYG credit allocation (PayPal webhook APPROVED)', [
                    'credit_package_id' => null,
                    'credits' => $tokens,
                    'payment_method' => 'paypal',
                    'reference_id' => $orderId,
                    'notes' => 'PAYG Plan: ' . ($plan->name ?? 'N/A') . ' | USD ' . ($plan->monthly_price ?? '0')
                ]);
                Log::info('PayPal PAYG captured via APPROVED webhook', ['order_id' => $orderId, 'user_id' => $user->id, 'plan_id' => $plan->id]);
                return;
            } catch (\Throwable $e) {
                Log::error('PayPal APPROVED processing error (PAYG): ' . $e->getMessage());
                return;
            }
        }

        Log::error('PayPal webhook: custom_id format invalid on APPROVED', ['custom_id' => $customId]);
    }

    /**
     * Handle PayPal CHECKOUT.ORDER.COMPLETED event for credit purchases
     */
    private function handleCheckoutOrderCompleted($event)
    {
        $resource = $event['resource'];
        // Idempotency: if we've already processed this order id as a credit transaction, skip
        try {
            if (!empty($resource['id'])) {
                $already = \App\Models\CreditTransaction::where('reference_id', $resource['id'])->exists();
                if ($already) {
                    Log::warning('Skipping duplicate PayPal credit webhook (already processed)', [
                        'order_id' => $resource['id']
                    ]);
                    return;
                }
            }
        } catch (\Throwable $e) {
            Log::error('Idempotency check failed for PayPal credit webhook', [
                'error' => $e->getMessage(),
            ]);
        }
        $purchaseUnit = $resource['purchase_units'][0] ?? null;
        if (!$purchaseUnit || empty($purchaseUnit['custom_id'])) {
            Log::error('PayPal webhook: custom_id missing in CHECKOUT.ORDER.COMPLETED');
            return;
        }
        
        $customId = $purchaseUnit['custom_id'];
        
        // Handle credit package purchases: user_{userId}_credit_{packageId}
        if (preg_match('/user_(\d+)_credit_(\d+)/', $customId, $matches)) {
            $userId = $matches[1];
            $packageId = $matches[2];
            $user = \App\Models\User::find($userId);
            $package = \App\Models\CreditPackage::find($packageId);
            
            if ($user && $package) {
                // Add credits to user account via central model method
                $userCredit = \App\Models\UserCredit::getOrCreateForUser($user->id);
                $tokens = $package->tokens;
                $userCredit->addCredits($tokens, 'Credit package purchase (PayPal)', [
                    'credit_package_id' => $package->id,
                    'credits' => $tokens,
                    'payment_method' => 'paypal',
                    'reference_id' => $resource['id'] ?? null,
                    'notes' => 'Package: ' . ($package->name ?? 'N/A') . ' | USD ' . ($package->usd_price ?? '0')
                ]);
                
                Log::info('PayPal credit purchase completed', [
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'credits_added' => $tokens,
                ]);
                return; // handled
            } else {
                Log::error('PayPal webhook: user or package not found for credit purchase', ['custom_id' => $customId]);
                return;
            }
        }

        // Also handle PAYG subscriptions treated as credits: user_{userId}_payg_{planId}
        if (preg_match('/user_(\d+)_payg_(\d+)/', $customId, $m2)) {
            $userId = (int)$m2[1];
            $planId = (int)$m2[2];
            $user = \App\Models\User::find($userId);
            $plan = \App\Models\SubscriptionPlan::find($planId);
            if ($user && $plan) {
                $tokens = $plan->token_cap_monthly ?: 100000;
                $userCredit = \App\Models\UserCredit::getOrCreateForUser($user->id);
                $userCredit->addCredits($tokens, 'PAYG credit allocation (PayPal)', [
                    'credit_package_id' => null,
                    'credits' => $tokens,
                    'payment_method' => 'paypal',
                    'reference_id' => $resource['id'] ?? null,
                    'notes' => 'PAYG Plan: ' . ($plan->name ?? 'N/A') . ' | USD ' . ($plan->monthly_price ?? '0')
                ]);
                Log::info('PayPal PAYG credit purchase completed via webhook', [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'credits_added' => $tokens,
                ]);
                return; // handled
            } else {
                Log::error('PayPal webhook: user or PAYG plan not found', ['custom_id' => $customId]);
                return;
            }
        }

        // If neither pattern matched, log once
        Log::error('PayPal webhook: custom_id format unrecognized', ['custom_id' => $customId]);
    }

    private function handleSubscriptionActivated($event)
    {
        $subscriptionId = $event['resource']['id'];
        
        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth()
            ]);
            
            // Send subscription confirmation email
            try {
                \Mail::to($subscription->user->email)->send(new \App\Mail\SubscriptionConfirmation($subscription->user, $subscription));
                \Log::info('Subscription confirmation email sent (PayPal)', [
                    'subscription_id' => $subscription->id,
                    'user_email' => $subscription->user->email
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send subscription confirmation email (PayPal)', [
                    'subscription_id' => $subscription->id,
                    'user_email' => $subscription->user->email,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    private function handleSubscriptionCancelled($event)
    {
        $subscriptionId = $event['resource']['id'];
        
        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);
        }
    }

    private function handleSubscriptionSuspended($event)
    {
        $subscriptionId = $event['resource']['id'];
        
        $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update(['status' => 'suspended']);
        }
    }

    private function handlePaymentCompleted($event)
    {
        // Handle successful payment
        $subscriptionId = $event['resource']['billing_agreement_id'] ?? null;
        
        if ($subscriptionId) {
            $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                // Reset token usage for new billing period
                $subscription->update([
                    'tokens_used_current_period' => 0,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth()
                ]);
            }
        }
    }
}
