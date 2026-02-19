<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\PricingPlan;
use App\Models\UserCredit;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpayController extends Controller
{
    private $razorpayId;
    private $razorpaySecret;
    private $razorpayWebhookSecret;
    
    public function __construct()
    {
        $this->razorpayId = env('RAZORPAY_KEY_ID');
    $this->razorpaySecret = env('RAZORPAY_KEY_SECRET');
    $this->razorpayWebhookSecret = env('RAZORPAY_WEBHOOK_SECRET');
    }

    /**
     * Create Razorpay subscription or credit payment
     */
    public function createSubscription(Request $request)
    {
        try {
            // Check if this is a credit package instead of subscription plan
            if ($request->has('credit_package_id')) {
                return $this->createCreditPayment($request);
            }

            $plan = PricingPlan::subscriptions()->findOrFail($request->plan_id);
            $user = Auth::user();
            $locationService = app(\App\Services\LocationService::class);
            
            // Get billing cycle from request (default to monthly)
            $billingCycle = $plan->billing_period ?: $request->input('billing_cycle', 'monthly');

            // Initialize Razorpay API
            $api = new Api($this->razorpayId, $this->razorpaySecret);

            // Get price based on billing cycle and convert to INR paise
            $price = $plan->price;
            $priceINR = $locationService->convertToINR($price);
            $amountInPaise = $priceINR * 100; // Convert to paise

            // Calculate period end date
            $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

            // Create Razorpay subscription plan if not exists
            $razorpayPlanId = $this->createOrGetRazorpayPlan($api, $plan, $amountInPaise, $billingCycle);

            // Create subscription
            $subscription = $api->subscription->create([
                'plan_id' => $razorpayPlanId,
                'customer_notify' => 1,
                'quantity' => 1,
                'total_count' => $billingCycle === 'yearly' ? 1 : 12, // 1 year payment or 12 monthly payments
                'addons' => [],
                'notes' => [
                    'user_id' => $user->id,
                    'plan_name' => $plan->name,
                    'billing_cycle' => $billingCycle
                ]
            ]);

            // Create local subscription record
            $localSubscription = Subscription::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id ?? null,
                'subscription_plan_id' => $plan->id,
                'razorpay_subscription_id' => $subscription['id'],
                'status' => 'pending',
                'billing_cycle' => $billingCycle,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'tokens_used_this_period' => 0
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
            Log::error('Razorpay subscription creation failed', [
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create one-time payment order for subscription plans
     */
    public function createOnetimePayment(Request $request)
    {
        try {
            $plan = PricingPlan::subscriptions()->findOrFail($request->plan_id);
            $user = Auth::user();
            $locationService = app(\App\Services\LocationService::class);
            
            // Get billing cycle from request (default to monthly)
            $billingCycle = $plan->billing_period ?: $request->input('billing_cycle', 'monthly');

            // Initialize Razorpay API
            $api = new Api($this->razorpayId, $this->razorpaySecret);

            // Get price based on billing cycle and convert to INR paise
            $price = $plan->price;
            $priceINR = $locationService->convertToINR($price);
            $amountInPaise = $priceINR * 100; // Convert to paise

            // Calculate period end date
            $periodEnd = $billingCycle === 'yearly' ? now()->addYear() : now()->addMonth();

            // Create Razorpay order for one-time payment
            $order = $api->order->create([
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => 'order_' . time() . '_' . $user->id,
                'notes' => [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'billing_cycle' => $billingCycle,
                    'subscription_type' => 'onetime'
                ]
            ]);

            // Create local subscription record as pending
            $localSubscription = Subscription::create([
                'user_id' => $user->id,
                'organization_id' => $user->organization_id ?? null,
                'subscription_plan_id' => $plan->id,
                'razorpay_payment_id' => $order['id'], // Store order ID temporarily
                'status' => 'pending',
                'billing_cycle' => $billingCycle,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
                'tokens_used_this_period' => 0
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'razorpay_key' => $this->razorpayId,
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'name' => config('app.name'),
                'description' => $plan->name . ' Subscription (' . ucfirst($billingCycle) . ' - One-time Payment)',
                'subscription_type' => 'onetime',
                'prefill' => [
                    'email' => $user->email,
                    'contact' => $user->phone ?? '',
                    'name' => $user->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay one-time payment creation failed', [
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create or get Razorpay plan
     */
    private function createOrGetRazorpayPlan($api, $plan, $amountInPaise, $billingCycle = 'monthly')
    {
        $planId = 'plan_' . $plan->slug . '_' . $billingCycle . '_inr';
        
        try {
            // Try to fetch existing plan
            $existingPlan = $api->plan->fetch($planId);
            return $existingPlan['id'];
        } catch (\Exception $e) {
            // Plan doesn't exist, create new one
            $razorpayPlan = $api->plan->create([
                'period' => $billingCycle,
                'interval' => 1,
                'item' => [
                    'name' => $plan->name . ' (' . ucfirst($billingCycle) . ')',
                    'amount' => $amountInPaise,
                    'currency' => 'INR',
                    'description' => $plan->description . ' - ' . ucfirst($billingCycle) . ' billing'
                ],
                'notes' => [
                    'local_plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'tokens_cap' => $plan->token_cap
                ]
            ]);
            
            return $razorpayPlan['id'];
        }
    }

    /**
     * Create Razorpay credit payment
     */
    public function createCreditPayment(Request $request)
    {
        try {
            $creditPackage = PricingPlan::credits()->findOrFail($request->credit_package_id);
            $user = Auth::user();
            $locationService = app(\App\Services\LocationService::class);
            
            // Initialize Razorpay API
            $api = new Api($this->razorpayId, $this->razorpaySecret);

            // Always use configured INR price for credit packages (no USD conversion)
            $priceINR = $creditPackage->metadata['inr_price'] ?? null;
            if ($priceINR === null || $priceINR <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'INR price is not configured for this credit package.'
                ], 422);
            }
            $amountInPaise = (int) round($priceINR * 100); // INR -> paise

            if ($amountInPaise < 100) { // Razorpay minimum = ₹1.00 (100 paise)
                return response()->json([
                    'success' => false,
                    'message' => 'Order amount less than minimum amount allowed'
                ], 422);
            }

            // Create Razorpay order for one-time payment
            $order = $api->order->create([
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'receipt' => 'credit_' . time() . '_' . $user->id,
                'notes' => [
                    'user_id' => $user->id,
                    'credit_package_id' => $creditPackage->id,
                    'package_name' => $creditPackage->name,
                    'tokens' => $creditPackage->credits,
                    'payment_type' => 'credit'
                ]
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'razorpay_key' => $this->razorpayId,
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'name' => config('app.name'),
                'description' => $creditPackage->name . ' Credit Package',
                'prefill' => [
                    'email' => $user->email,
                    'contact' => $user->phone ?? '',
                    'name' => $user->name
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Razorpay credit payment creation failed', [
                'user_id' => Auth::id(),
                'credit_package_id' => $request->credit_package_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the credit payment: ' . $e->getMessage()
            ], 500);
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
     * Handle successful one-time payment (subscriptions or credits)
     */
    public function handleOnetimeSuccess(Request $request)
    {
        try {
            $paymentId = $request->razorpay_payment_id;
            $orderId = $request->razorpay_order_id;
            $signature = $request->razorpay_signature;

            // Verify signature
            $api = new Api($this->razorpayId, $this->razorpaySecret);
            
            $attributes = [
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // First check if this is a subscription payment
            $subscription = Subscription::where('razorpay_payment_id', $orderId)->first();
            if ($subscription) {
                $subscription->update([
                    'status' => 'active',
                    'razorpay_payment_id' => $paymentId,
                ]);

                // Send confirmation email
                $subscription->user->notify(new \App\Notifications\SubscriptionCreated($subscription));

                return redirect()->route('customer.dashboard')
                    ->with('success', 'Payment successful! Your subscription is now active.');
            }

            // If not a subscription, check if it's a credit purchase by fetching order details
            $order = $api->order->fetch($orderId);
            $notes = $order['notes'] ?? [];

            if (isset($notes['payment_type']) && $notes['payment_type'] === 'credit') {
                // Handle credit purchase
                $this->handleCreditPurchaseSuccess($order, $paymentId);
                return redirect()->route('customer.dashboard')
                    ->with('success', 'Credits purchased successfully!');
            }
            
            return redirect()->route('customer.dashboard')
                ->with('error', 'Subscription not found');
                
        } catch (\Exception $e) {
            Log::error('Razorpay one-time payment verification failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            
            return redirect()->route('customer.dashboard')
                ->with('error', 'Payment verification failed');
        }
    }

    /**
     * Handle successful credit purchase
     */
    private function handleCreditPurchaseSuccess($order, $paymentId)
    {
        try {
            $notes = $order['notes'];
            $userId = $notes['user_id'] ?? null;
            $creditPackageId = $notes['credit_package_id'] ?? null;
            $tokens = $notes['tokens'] ?? null;

            if (!$userId || !$creditPackageId || !$tokens) {
                Log::error('Incomplete credit purchase data', ['notes' => $notes]);
                return;
            }

            $user = User::find($userId);
            $creditPackage = PricingPlan::credits()->find($creditPackageId);

            if (!$user || !$creditPackage) {
                Log::error('User or credit package not found', [
                    'user_id' => $userId,
                    'credit_package_id' => $creditPackageId
                ]);
                return;
            }

            // Add credits to user account via central model method
            $userCredit = UserCredit::getOrCreateForUser($userId);
            $userCredit->addCredits($tokens, 'Credit package purchase (Razorpay)', [
                'credit_package_id' => $creditPackageId,
                'credits' => $tokens,
                'payment_method' => 'razorpay',
                'razorpay_payment_id' => $paymentId,
                'reference_id' => $order['id'] ?? null,
                'notes' => 'Package: ' . ($creditPackage->name ?? 'N/A') . ' | INR ' . number_format((float)($creditPackage->metadata['inr_price'] ?? 0), 2)
            ]);

            Log::info('Credit purchase completed', [
                'user_id' => $userId,
                'credits_added' => $tokens,
                'payment_id' => $paymentId
            ]);

        } catch (\Exception $e) {
            Log::error('Credit purchase processing failed', [
                'error' => $e->getMessage(),
                'order_id' => $order['id'] ?? null
            ]);
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
            
            // Check if signature is provided
            if (empty($signature)) {
                \Log::warning('Razorpay webhook: missing signature header');
                return response()->json(['status' => 'missing signature'], 400);
            }
            
            // Verify webhook signature using webhook secret
            $expectedSignature = hash_hmac('sha256', $payload, $this->razorpayWebhookSecret);
            if (!hash_equals($expectedSignature, (string)$signature)) {
                // Parse event to check if it's a downtime notification (which we can ignore)
                $eventData = json_decode($payload, true);
                $eventType = $eventData['event'] ?? '';
                
                if (strpos($eventType, 'payment.downtime') !== false) {
                    // Log downtime events as info instead of warning since they're just system status
                    \Log::info('Razorpay downtime notification (signature validation skipped)', [
                        'event' => $eventType,
                        'account_id' => $eventData['account_id'] ?? null
                    ]);
                    return response()->json(['status' => 'downtime notification received'], 200);
                }
                
                \Log::warning('Razorpay webhook: invalid signature', [
                    'expected' => $expectedSignature,
                    'received' => $signature,
                    'event_type' => $eventType,
                    'payload' => substr($payload, 0, 200) . '...' // Truncate payload for cleaner logs
                ]);
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
                    
                case 'payment.downtime.started':
                case 'payment.downtime.resolved':
                    // Just log downtime events for monitoring, no action needed
                    Log::info('Razorpay payment downtime event', [
                        'event' => $event['event'],
                        'method' => $event['payload']['payment.downtime']['entity']['method'] ?? 'unknown',
                        'status' => $event['payload']['payment.downtime']['entity']['status'] ?? 'unknown'
                    ]);
                    break;
                    
                default:
                    Log::info('Unhandled Razorpay webhook event', ['event' => $event['event']]);
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
            
            // Send subscription confirmation email
            try {
                \Mail::to($subscription->user->email)->send(new \App\Mail\SubscriptionConfirmation($subscription->user, $subscription));
                \Log::info('Subscription confirmation email sent', [
                    'subscription_id' => $subscription->id,
                    'user_email' => $subscription->user->email
                ]);
            } catch (\Exception $e) {
                \Log::error('Failed to send subscription confirmation email', [
                    'subscription_id' => $subscription->id,
                    'user_email' => $subscription->user->email,
                    'error' => $e->getMessage()
                ]);
            }
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
        $payment = $event['payload']['payment']['entity'];
        $subscriptionId = $payment['subscription_id'] ?? null;
        $orderId = $payment['order_id'] ?? null;
        $notes = $payment['notes'] ?? [];
        
        if ($subscriptionId) {
            // Handle subscription payments
            $subscription = Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            if ($subscription) {
                // Reset token usage for new billing period
                $subscription->update([
                    'tokens_used_this_period' => 0,
                    'current_period_start' => now(),
                    'current_period_end' => now()->addMonth()
                ]);
            }
        } elseif ($orderId && isset($notes['payment_type']) && $notes['payment_type'] === 'credit') {
            // Handle credit payments
            $this->handleCreditPurchase($payment, $notes);
        }
    }

    private function handleCreditPurchase($payment, $notes)
    {
        try {
            $userId = $notes['user_id'] ?? null;
            $creditPackageId = $notes['credit_package_id'] ?? null;
            $tokens = $notes['tokens'] ?? null;

            if (!$userId || !$creditPackageId || !$tokens) {
                Log::error('Incomplete credit purchase data in webhook', ['notes' => $notes]);
                return;
            }

            $user = User::find($userId);
            $creditPackage = PricingPlan::credits()->find($creditPackageId);

            if (!$user || !$creditPackage) {
                Log::error('User or credit package not found', [
                    'user_id' => $userId,
                    'credit_package_id' => $creditPackageId
                ]);
                return;
            }

            // Add credits to user account via central model method
            $userCredit = UserCredit::getOrCreateForUser($userId);
            $userCredit->addCredits($tokens, 'Credit package purchase (Razorpay webhook)', [
                'credit_package_id' => $creditPackageId,
                'credits' => $tokens,
                'payment_method' => 'razorpay',
                'razorpay_payment_id' => $payment['id'] ?? null,
                'reference_id' => $payment['order_id'] ?? null,
                'notes' => 'Package: ' . ($creditPackage->name ?? 'N/A') . ' | INR ' . number_format((float)($creditPackage->metadata['inr_price'] ?? 0), 2)
            ]);

            Log::info('Credit purchase completed via webhook', [
                'user_id' => $userId,
                'credits_added' => $tokens,
                'payment_id' => $payment['id']
            ]);

        } catch (\Exception $e) {
            Log::error('Credit purchase processing failed in webhook', [
                'error' => $e->getMessage(),
                'payment_id' => $payment['id'] ?? null
            ]);
        }
    }
}
