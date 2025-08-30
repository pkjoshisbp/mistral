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

            // Create PayPal subscription
            $accessToken = $this->getAccessToken();
            
            $subscriptionData = [
                'plan_id' => $plan->paypal_plan_id, // We'll need to add this field
                'subscriber' => [
                    'name' => [
                        'given_name' => $user->name,
                        'surname' => $user->name
                    ],
                    'email_address' => $user->email
                ],
                'application_context' => [
                    'brand_name' => config('app.name'),
                    'locale' => 'en-US',
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'payment_method' => [
                        'payer_selected' => 'PAYPAL',
                        'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'
                    ],
                    'return_url' => route('paypal.success'),
                    'cancel_url' => route('paypal.cancel')
                ]
            ];

            $response = Http::withToken($accessToken)
                ->post($this->paypalBaseUrl . '/v1/billing/subscriptions', $subscriptionData);

            if ($response->successful()) {
                $paypalSubscription = $response->json();
                
                // Create local subscription record
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_plan_id' => $plan->id,
                    'paypal_subscription_id' => $paypalSubscription['id'],
                    'status' => 'pending',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth(),
                    'tokens_used_current_period' => 0
                ]);

                // Return approval URL
                foreach ($paypalSubscription['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        return response()->json([
                            'success' => true,
                            'approval_url' => $link['href']
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create PayPal subscription'
            ], 500);

        } catch (\Exception $e) {
            Log::error('PayPal subscription creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the subscription'
            ], 500);
        }
    }

    /**
     * Handle successful subscription
     */
    public function handleSuccess(Request $request)
    {
        try {
            $subscriptionId = $request->subscription_id;
            $accessToken = $this->getAccessToken();

            // Get subscription details from PayPal
            $response = Http::withToken($accessToken)
                ->get($this->paypalBaseUrl . '/v1/billing/subscriptions/' . $subscriptionId);

            if ($response->successful()) {
                $paypalSubscription = $response->json();
                
                // Update local subscription
                $subscription = Subscription::where('paypal_subscription_id', $subscriptionId)->first();
                if ($subscription) {
                    $subscription->update([
                        'status' => strtolower($paypalSubscription['status']),
                        'paypal_data' => $paypalSubscription
                    ]);
                }

                return redirect()->route('customer.dashboard')
                    ->with('success', 'Subscription activated successfully!');
            }

            return redirect()->route('customer.dashboard')
                ->with('error', 'Failed to verify subscription');

        } catch (\Exception $e) {
            Log::error('PayPal success handling failed: ' . $e->getMessage());
            
            return redirect()->route('customer.dashboard')
                ->with('error', 'An error occurred while processing your subscription');
        }
    }

    /**
     * Handle cancelled subscription
     */
    public function handleCancel(Request $request)
    {
        return redirect()->route('customer.dashboard')
            ->with('info', 'Subscription setup was cancelled');
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
