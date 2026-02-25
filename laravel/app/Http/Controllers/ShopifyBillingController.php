<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ShopifyBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopifyBillingController extends Controller
{
    public function __construct(private ShopifyBillingService $shopifyBillingService)
    {
    }

    public function subscribe(Request $request, PricingPlan $plan)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($plan->plan_type !== 'subscription' || !$plan->is_active) {
            return redirect()->route('customer.subscription')->with('error', 'Selected plan is not available.');
        }

        $integration = $this->findShopifyIntegrationForUser($user);

        if (!$integration) {
            return redirect()->route('customer.setup-organization')
                ->with('error', 'Please connect a Shopify organization first.');
        }

        if ((float) $plan->price <= 0) {
            $alreadyUsedFreeTrial = Subscription::where('user_id', $user->id)
                ->where('organization_id', $integration->organization_id)
                ->whereHas('subscriptionPlan', function ($query) {
                    $query->where('slug', 'like', 'free%');
                })
                ->exists();

            $alreadyOnFreePlan = Subscription::where('user_id', $user->id)
                ->where('organization_id', $integration->organization_id)
                ->where('subscription_plan_id', $plan->id)
                ->where('status', 'active')
                ->exists();

            if ($alreadyUsedFreeTrial && !$alreadyOnFreePlan) {
                return redirect()->route('customer.subscription')
                    ->with('error', 'Free trial can only be activated one time. Please choose a paid plan to continue.');
            }

            Subscription::where('user_id', $user->id)
                ->where('organization_id', $integration->organization_id)
                ->where('status', 'active')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                ]);

            Subscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'organization_id' => $integration->organization_id,
                    'subscription_plan_id' => $plan->id,
                    'payment_provider' => 'shopify',
                ],
                [
                    'billing_cycle' => 'monthly',
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addDays(30),
                    'tokens_used_this_period' => 0,
                    'overage_charges' => 0,
                    'paypal_subscription_id' => null,
                    'razorpay_subscription_id' => null,
                    'razorpay_payment_id' => null,
                    'shopify_subscription_gid' => null,
                    'shopify_shop_domain' => $integration->shop,
                    'cancelled_at' => null,
                ]
            );

            return redirect()->route('customer.subscription')
                ->with('success', 'Free plan activated successfully.');
        }

        $returnUrl = route('shopify.billing.callback', [
            'plan_id' => $plan->id,
            'shop' => $integration->shop,
        ]);

        $result = $this->shopifyBillingService->createAppSubscription($integration, $plan, $returnUrl);

        if (!($result['ok'] ?? false)) {
            return redirect()->route('customer.subscription')
                ->with('error', $result['message'] ?? 'Unable to initiate Shopify billing.');
        }

        return redirect()->away($result['confirmation_url']);
    }

    public function callback(Request $request)
    {
        $shop = strtolower((string) $request->query('shop', ''));
        $planId = (int) $request->query('plan_id');
        $chargeId = $request->query('charge_id');

        if ($shop === '' || $planId <= 0) {
            return redirect()->route('customer.subscription')
                ->with('error', 'Invalid Shopify billing callback.');
        }

        $integration = Integration::where('provider', 'shopify')
            ->where('shop', $shop)
            ->where('active', true)
            ->first();

        $plan = PricingPlan::where('id', $planId)
            ->where('plan_type', 'subscription')
            ->where('is_active', true)
            ->first();

        if (!$integration || !$plan) {
            return redirect()->route('customer.subscription')
                ->with('error', 'Unable to validate Shopify billing callback.');
        }

        $user = Auth::user();
        if (!$user) {
            $user = $integration->organization?->users()->first();
            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
            }
        }

        if (!$user instanceof User) {
            return redirect()->route('login')->with('error', 'Please login to complete billing setup.');
        }

        $subscription = $this->shopifyBillingService->syncSubscriptionFromShopify(
            $integration,
            $user,
            $plan,
            $chargeId ? (string) $chargeId : null
        );

        if (!$subscription) {
            return redirect()->route('customer.subscription')
                ->with('error', 'Shopify charge approval was not found. Please try again.');
        }

        return redirect()->route('customer.subscription')
            ->with('success', 'Shopify subscription updated successfully.');
    }

    public function cancel(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return redirect()->route('login');
        }

        $subscription = $user->activeSubscription;
        if (!$subscription) {
            return redirect()->route('customer.subscription')->with('error', 'No active subscription found.');
        }

        if ($subscription->payment_provider !== 'shopify' || !$subscription->shopify_subscription_gid) {
            return redirect()->route('customer.subscription')->with('error', 'This subscription is not managed by Shopify.');
        }

        $integration = $this->findShopifyIntegrationForUser($user);

        if (!$integration) {
            return redirect()->route('customer.subscription')->with('error', 'Shopify integration not found.');
        }

        $cancelled = $this->shopifyBillingService->cancelAppSubscription($integration, $subscription->shopify_subscription_gid);

        if (!$cancelled) {
            return redirect()->route('customer.subscription')
                ->with('error', 'Unable to cancel Shopify subscription right now.');
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return redirect()->route('customer.subscription')->with('success', 'Shopify subscription cancelled.');
    }

    private function findShopifyIntegrationForUser(User $user): ?Integration
    {
        $organizationIds = $user->organizations()->pluck('organizations.id');

        if ($organizationIds->isEmpty()) {
            $primary = $user->primaryOrganization();
            if ($primary) {
                $organizationIds = collect([$primary->id]);
            }
        }

        if ($organizationIds->isEmpty()) {
            return null;
        }

        return Integration::whereIn('organization_id', $organizationIds)
            ->where('provider', 'shopify')
            ->where('active', true)
            ->orderByDesc('id')
            ->first();
    }
}
