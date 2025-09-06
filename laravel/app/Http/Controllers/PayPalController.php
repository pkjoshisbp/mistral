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

            // If a PayPal billing plan ID exists, create a recurring subscription
            if ($plan->paypal_plan_id) {
                $subscriptionPayload = [
                    'plan_id' => $plan->paypal_plan_id,
                    'custom_id' => 'user_' . $user->id . '_plan_' . $plan->id . '_' . $billingCycle,
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'locale' => 'en-US',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'SUBSCRIBE_NOW',
                        'return_url' => route('paypal.success') . '?plan_id=' . $plan->id . '&billing_cycle=' . $billingCycle,
                        'cancel_url' => route('paypal.cancel')
                    ]
                ];

                $response = Http::withToken($accessToken)
                    ->post($this->paypalBaseUrl . '/v1/billing/subscriptions', $subscriptionPayload);

                if ($response->successful()) {
                    $data = $response->json();
                    foreach ($data['links'] as $link) {
                        if ($link['rel'] === 'approve') {
                            return response()->json([
                                'success' => true,
                                'approval_url' => $link['href'],
                                'mode' => 'recurring'
                            ]);
                        }
                    }
                } else {
                    Log::error('PayPal subscription create error', ['body' => $response->body()]);
                }
            }

            // Fallback: one-time order (until billing plan IDs are provisioned)
            $paymentData = [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => 'USD',
                            'value' => number_format($price, 2, '.', '')
                        ],
                        'description' => $plan->name . ' Plan - ' . $plan->description . ' (' . ucfirst($billingCycle) . ')',
                        'custom_id' => 'user_' . $user->id . '_plan_' . $plan->id . '_' . $billingCycle
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
                            'mode' => 'one_time'
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate PayPal payment/subscription.'
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
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('PayPal webhook processing failed: ' . $e->getMessage());
            
            return response()->json(['status' => 'error'], 500);
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
