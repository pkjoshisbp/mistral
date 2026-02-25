<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Subscription;
use App\Models\PricingPlan;
use App\Models\TokenUsageLog;
use Illuminate\Support\Facades\Auth;
use App\Services\ShopifyBillingService;

class SubscriptionManager extends Component
{
    public $currentSubscription;
    public $availablePlans;
    public $tokenUsageCurrentPeriod = 0;
    public $tokenUsageHistory = [];
    public $hasShopifyIntegration = false;
    public $shopifyShopDomain = null;
    public $shopifyOrganizationId = null;

    public function mount()
    {
        $this->detectShopifyIntegration();
        $this->loadSubscriptionData();
        $this->loadAvailablePlans();
        $this->loadTokenUsage();
    }

    public function detectShopifyIntegration()
    {
        $user = Auth::user();
        $organizations = $user?->organizations;

        if (!$organizations || $organizations->isEmpty()) {
            $this->hasShopifyIntegration = false;
            $this->shopifyShopDomain = null;
            $this->shopifyOrganizationId = null;
            return;
        }

        $shopifyOrg = null;
        $integration = null;

        foreach ($organizations as $organization) {
            $candidate = $organization->integrations()
                ->where('provider', 'shopify')
                ->where('active', true)
                ->first();

            if ($candidate) {
                $shopifyOrg = $organization;
                $integration = $candidate;
                break;
            }
        }

        $this->hasShopifyIntegration = (bool) $integration;
        $this->shopifyShopDomain = $integration?->shop;
        $this->shopifyOrganizationId = $shopifyOrg?->id;
    }

    public function loadSubscriptionData()
    {
        $this->currentSubscription = Auth::user()->activeSubscription;
    }

    public function loadAvailablePlans()
    {
        $rows = PricingPlan::active()
            ->subscriptions()
            ->orderBy('sort_order')
            ->orderBy('billing_period')
            ->get();

        $grouped = [];
        foreach ($rows as $plan) {
            $meta = is_array($plan->metadata) ? $plan->metadata : [];
            $baseSlug = $meta['original_slug'] ?? $plan->slug;

            if ($this->hasShopifyIntegration) {
                $shopifyAvailable = array_key_exists('shopify_available', $meta)
                    ? (bool) $meta['shopify_available']
                    : !in_array($baseSlug, ['enterprise', 'payg'], true);

                if (!$shopifyAvailable) {
                    continue;
                }
            }

            $key = $baseSlug ?: $plan->name;

            if (!isset($grouped[$key])) {
                $tokenCap = (int) ($plan->token_cap ?? 0);
                $grouped[$key] = (object) [
                    'id' => $plan->id,
                    'monthly_id' => null,
                    'yearly_id' => null,
                    'name' => $plan->name,
                    'slug' => $baseSlug ?: $plan->slug,
                    'description' => $plan->description,
                    'monthly_price' => null,
                    'yearly_price' => null,
                    'token_cap_monthly' => $tokenCap,
                    'overage_price_per_100k' => $plan->overage_price_per_100k,
                    'features' => $meta['features'] ?? [],
                    'formatted_token_cap' => PricingPlan::formatTokenCap($tokenCap),
                ];
            }

            if ($plan->billing_period === 'monthly') {
                $grouped[$key]->monthly_price = $plan->price;
                $grouped[$key]->monthly_id = $plan->id;
                $grouped[$key]->id = $plan->id;
            }
            if ($plan->billing_period === 'yearly') {
                $grouped[$key]->yearly_price = $plan->price;
                $grouped[$key]->yearly_id = $plan->id;
            }
        }

        $this->availablePlans = collect(array_values($grouped));
    }

    public function loadTokenUsage()
    {
        $user = Auth::user();
        
        if ($this->currentSubscription) {
            // Current period usage
            $this->tokenUsageCurrentPeriod = TokenUsageLog::where('user_id', $user->id)
                ->whereBetween('created_at', [
                    $this->currentSubscription->current_period_start,
                    $this->currentSubscription->current_period_end
                ])
                ->sum('tokens_used');

            // Usage history for the last 6 months
            $this->tokenUsageHistory = TokenUsageLog::where('user_id', $user->id)
                ->selectRaw('DATE(created_at) as date, SUM(tokens_used) as total_tokens')
                ->groupBy('date')
                ->orderBy('date', 'desc')
                ->limit(30)
                ->get();
        }
    }

    public function getUsagePercentage()
    {
        if (!$this->currentSubscription || (int) $this->currentSubscription->subscriptionPlan->token_cap <= 0) {
            return 0;
        }

        return min(100, ($this->tokenUsageCurrentPeriod / (int) $this->currentSubscription->subscriptionPlan->token_cap) * 100);
    }

    public function getRemainingTokens()
    {
        if (!$this->currentSubscription || (int) $this->currentSubscription->subscriptionPlan->token_cap <= 0) {
            return 'Unlimited';
        }

        $remaining = (int) $this->currentSubscription->subscriptionPlan->token_cap - $this->tokenUsageCurrentPeriod;
        return max(0, $remaining);
    }

    public function getOverageTokens()
    {
        if (!$this->currentSubscription || (int) $this->currentSubscription->subscriptionPlan->token_cap <= 0) {
            return 0;
        }

        return max(0, $this->tokenUsageCurrentPeriod - (int) $this->currentSubscription->subscriptionPlan->token_cap);
    }

    public function getOverageCost()
    {
        $overageTokens = $this->getOverageTokens();
        if ($overageTokens <= 0) {
            return 0;
        }

        $pricePerToken = $this->currentSubscription->subscriptionPlan->overage_price_per_100k / 100000;
        return $overageTokens * $pricePerToken;
    }

    public function cancelSubscription()
    {
        if ($this->currentSubscription) {
            if ($this->hasShopifyIntegration && $this->currentSubscription->payment_provider === 'shopify' && $this->currentSubscription->shopify_subscription_gid) {
                $user = Auth::user();
                $organizationIds = $user?->organizations()->pluck('organizations.id');

                if ($organizationIds && $organizationIds->isEmpty()) {
                    $primary = $user?->primaryOrganization();
                    $organizationIds = $primary ? collect([$primary->id]) : collect();
                }

                $integration = $organizationIds && $organizationIds->isNotEmpty()
                    ? \App\Models\Integration::whereIn('organization_id', $organizationIds)
                    ->where('provider', 'shopify')
                    ->where('active', true)
                    ->orderByDesc('id')
                    ->first()
                    : null;

                if (!$integration) {
                    session()->flash('error', 'Shopify integration not found.');
                    return;
                }

                $cancelled = app(ShopifyBillingService::class)
                    ->cancelAppSubscription($integration, $this->currentSubscription->shopify_subscription_gid);

                if (!$cancelled) {
                    session()->flash('error', 'Unable to cancel Shopify subscription right now.');
                    return;
                }
            }

            $this->currentSubscription->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);
            $this->loadSubscriptionData();
            
            session()->flash('success', 'Subscription cancelled successfully. You can continue using the service until the end of your current billing period.');
        }
    }

    public function render()
    {
        return view('livewire.subscription-manager');
    }
}
