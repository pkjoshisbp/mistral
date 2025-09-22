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

                $response = Http::withToken($accessToken)
                    ->post($this->paypalBaseUrl . '/v1/billing/subscriptions', $subscriptionData);

                if ($response->successful()) {
                    $paypalSubscription = $response->json();
                    
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

            // For PAYG, create one-time payment for credits
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
                    'return_url' => route('paypal.success') . '?plan_id=' . $plan->id,
                    'cancel_url' => route('paypal.cancel')
                ]
            ];

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders', $paymentData);

            if ($response->successful()) {
                $paypalOrder = $response->json();
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
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

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders', $paymentData);

            if ($response->successful()) {
                $paypalOrder = $response->json();
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
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
     * Handle successful payment
     */
    public function handleSuccess(Request $request)
    {
        try {
            $token = $request->token; // PayPal order token
            $planId = $request->plan_id;
            $billingCycle = $request->input('billing_cycle', 'monthly');
            $accessToken = $this->getAccessToken();

            // Capture the payment
            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v2/checkout/orders/' . $token . '/capture');

            if ($response->successful()) {
                $paypalOrder = $response->json();
                
                if ($paypalOrder['status'] === 'COMPLETED') {
                    $plan = SubscriptionPlan::findOrFail($planId);
                    $user = Auth::user();
                    
                    // Calculate period end date based on billing cycle
                    $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();
                    
                    // Create local subscription record
                    $subscription = Subscription::create([
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

                    return redirect()->route('customer.dashboard')
                        ->with('success', 'Payment successful! Your ' . $plan->name . ' plan (' . ucfirst($billingCycle) . ') has been activated.');
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
     * Handle successful credit purchase
     */
    public function handleCreditSuccess(Request $request)
    {
        try {
            $orderId = $request->get('order_id');
            
            if (!$orderId) {
                return redirect()->route('customer.dashboard')->with('error', 'Missing order ID.');
            }

            return redirect()->route('customer.dashboard')->with('success', 'Credits purchased successfully!');
        } catch (Exception $e) {
            Log::error('PayPal Credit Success Error: ' . $e->getMessage());
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
        // custom_id format: user_{userId}_plan_{planId}_{cycle}
        if (preg_match('/user_(\d+)_plan_(\d+)_(\w+)/', $customId, $matches)) {
            $userId = $matches[1];
            $planId = $matches[2];
            $cycle = $matches[3];
            $user = \App\Models\User::find($userId);
            $plan = \App\Models\SubscriptionPlan::find($planId);
            if ($user && $plan) {
                // Create or update subscription
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
            } else {
                Log::error('PayPal webhook: user or plan not found for custom_id', ['custom_id' => $customId]);
            }
        } else {
            Log::error('PayPal webhook: custom_id format invalid', ['custom_id' => $customId]);
        }
    }

    /**
     * Handle PayPal CHECKOUT.ORDER.COMPLETED event for credit purchases
     */
    private function handleCheckoutOrderCompleted($event)
    {
        $resource = $event['resource'];
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
            } else {
                Log::error('PayPal webhook: user or package not found for credit purchase', ['custom_id' => $customId]);
            }
        } else {
            Log::error('PayPal webhook: custom_id format invalid for credit purchase', ['custom_id' => $customId]);
        }
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
