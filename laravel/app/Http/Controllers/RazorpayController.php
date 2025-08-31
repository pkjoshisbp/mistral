<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
    private $razorpayId;
    private $razorpaySecret;
    
    public function __construct()
    {
        $this->razorpayId = env('RAZORPAY_KEY_ID');
        $this->razorpaySecret = env('RAZORPAY_KEY_SECRET');
    }

    /**
     * Create Razorpay subscription
     */
    public function createSubscription(Request $request)
    {
        try {
            $plan = SubscriptionPlan::findOrFail($request->plan_id);
            $user = Auth::user();
            $locationService = app(\App\Services\LocationService::class);

            // Initialize Razorpay API
            $api = new Api($this->razorpayId, $this->razorpaySecret);

            // Convert price to INR paise (Razorpay uses paise)
            $monthlyPriceINR = $locationService->convertToINR($plan->monthly_price);
            $amountInPaise = $monthlyPriceINR * 100; // Convert to paise

            // Create Razorpay subscription plan if not exists
            $razorpayPlanId = $this->createOrGetRazorpayPlan($api, $plan, $amountInPaise);

            // Create subscription
            $subscription = $api->subscription->create([
                'plan_id' => $razorpayPlanId,
                'customer_notify' => 1,
                'quantity' => 1,
                'total_count' => 12, // 12 months
                'addons' => [],
                'notes' => [
                    'user_id' => $user->id,
                    'plan_name' => $plan->name
                ]
            ]);

            // Create local subscription record
            $localSubscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'razorpay_subscription_id' => $subscription['id'],
                'status' => 'created',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
                'tokens_used_current_period' => 0
            ]);

            return response()->json([
                'success' => true,
                'subscription_id' => $subscription['id'],
                'razorpay_key' => $this->razorpayId,
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'name' => config('app.name'),
                'description' => $plan->name . ' Subscription',
                'prefill' => [
                    'email' => $user->email,
                    'contact' => $user->phone ?? '',
                    'name' => $user->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay subscription creation failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the subscription'
            ], 500);
        }
    }

    /**
     * Create or get Razorpay plan
     */
    private function createOrGetRazorpayPlan($api, $plan, $amountInPaise)
    {
        $planId = 'plan_' . $plan->slug . '_inr';
        
        try {
            // Try to fetch existing plan
            $existingPlan = $api->plan->fetch($planId);
            return $existingPlan['id'];
        } catch (\Exception $e) {
            // Plan doesn't exist, create new one
            $razorpayPlan = $api->plan->create([
                'period' => 'monthly',
                'interval' => 1,
                'item' => [
                    'name' => $plan->name,
                    'amount' => $amountInPaise,
                    'currency' => 'INR',
                    'description' => $plan->description
                ],
                'notes' => [
                    'local_plan_id' => $plan->id,
                    'tokens_cap' => $plan->token_cap_monthly
                ]
            ]);
            
            return $razorpayPlan['id'];
        }
    }

    /**
     * Handle successful payment
     */
    public function handleSuccess(Request $request)
    {
        try {
            $subscriptionId = $request->razorpay_subscription_id;
            $paymentId = $request->razorpay_payment_id;
            $signature = $request->razorpay_signature;

            // Verify signature
            $api = new Api($this->razorpayId, $this->razorpaySecret);
            
            $attributes = [
                'razorpay_subscription_id' => $subscriptionId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // Update local subscription
            $subscription = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'razorpay_payment_id' => $paymentId
                ]);
            }

            return redirect()->route('customer.dashboard')
                ->with('success', 'Subscription activated successfully!');

        } catch (\Exception $e) {
            Log::error('Razorpay success handling failed: ' . $e->getMessage());
            
            return redirect()->route('customer.dashboard')
                ->with('error', 'Payment verification failed');
        }
    }

    /**
     * Handle failed payment
     */
    public function handleFailure(Request $request)
    {
        return redirect()->route('customer.dashboard')
            ->with('error', 'Payment failed. Please try again.');
    }

    /**
     * Handle Razorpay webhooks
     */
    public function handleWebhook(Request $request)
    {
        try {
            $payload = $request->getContent();
            $signature = $request->header('X-Razorpay-Signature');
            
            // Verify webhook signature
            $expectedSignature = hash_hmac('sha256', $payload, $this->razorpaySecret);
            
            if (!hash_equals($expectedSignature, $signature)) {
                return response()->json(['status' => 'invalid signature'], 400);
            }

            $event = json_decode($payload, true);
            
            Log::info('Razorpay webhook received', $event);

            switch ($event['event']) {
                case 'subscription.activated':
                    $this->handleSubscriptionActivated($event);
                    break;
                    
                case 'subscription.cancelled':
                    $this->handleSubscriptionCancelled($event);
                    break;
                    
                case 'payment.captured':
                    $this->handlePaymentCaptured($event);
                    break;
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Razorpay webhook processing failed: ' . $e->getMessage());
            
            return response()->json(['status' => 'error'], 500);
        }
    }

    private function handleSubscriptionActivated($event)
    {
        $subscriptionId = $event['payload']['subscription']['entity']['id'];
        
        $subscription = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update(['status' => 'active']);
        }
    }

    private function handleSubscriptionCancelled($event)
    {
        $subscriptionId = $event['payload']['subscription']['entity']['id'];
        
        $subscription = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
        if ($subscription) {
            $subscription->update(['status' => 'cancelled']);
        }
    }

    private function handlePaymentCaptured($event)
    {
        $subscriptionId = $event['payload']['payment']['entity']['subscription_id'] ?? null;
        
        if ($subscriptionId) {
            $subscription = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
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
