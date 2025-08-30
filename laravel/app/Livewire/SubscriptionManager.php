<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TokenUsageLog;
use Illuminate\Support\Facades\Auth;

class SubscriptionManager extends Component
{
    public $currentSubscription;
    public $availablePlans;
    public $tokenUsageCurrentPeriod = 0;
    public $tokenUsageHistory = [];

    public function mount()
    {
        $this->loadSubscriptionData();
        $this->loadAvailablePlans();
        $this->loadTokenUsage();
    }

    public function loadSubscriptionData()
    {
        $this->currentSubscription = Auth::user()->subscription;
    }

    public function loadAvailablePlans()
    {
        $this->availablePlans = SubscriptionPlan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();
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
        if (!$this->currentSubscription || $this->currentSubscription->subscriptionPlan->token_cap_monthly <= 0) {
            return 0;
        }

        return min(100, ($this->tokenUsageCurrentPeriod / $this->currentSubscription->subscriptionPlan->token_cap_monthly) * 100);
    }

    public function getRemainingTokens()
    {
        if (!$this->currentSubscription || $this->currentSubscription->subscriptionPlan->token_cap_monthly <= 0) {
            return 'Unlimited';
        }

        $remaining = $this->currentSubscription->subscriptionPlan->token_cap_monthly - $this->tokenUsageCurrentPeriod;
        return max(0, $remaining);
    }

    public function getOverageTokens()
    {
        if (!$this->currentSubscription || $this->currentSubscription->subscriptionPlan->token_cap_monthly <= 0) {
            return 0;
        }

        return max(0, $this->tokenUsageCurrentPeriod - $this->currentSubscription->subscriptionPlan->token_cap_monthly);
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
        // This would integrate with PayPal to cancel the subscription
        // For now, we'll just update the local status
        if ($this->currentSubscription) {
            $this->currentSubscription->update(['status' => 'cancelled']);
            $this->loadSubscriptionData();
            
            session()->flash('success', 'Subscription cancelled successfully. You can continue using the service until the end of your current billing period.');
        }
    }

    public function render()
    {
        return view('livewire.subscription-manager');
    }
}
