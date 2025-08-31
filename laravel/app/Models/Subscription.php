<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'subscription_plan_id',
        'paypal_subscription_id',
        'razorpay_subscription_id',
        'razorpay_payment_id',
        'billing_cycle',
        'status',
        'current_period_start',
        'current_period_end',
        'tokens_used_this_period',
        'overage_charges',
        'cancelled_at'
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'overage_charges' => 'decimal:2'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function tokenUsageLogs()
    {
        return $this->hasMany(TokenUsageLog::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               $this->current_period_end && 
               $this->current_period_end->isFuture();
    }

    public function getRemainingTokensAttribute()
    {
        if (!$this->subscriptionPlan) return 0;
        
        return max(0, $this->subscriptionPlan->token_cap_monthly - $this->tokens_used_this_period);
    }

    public function getUsagePercentageAttribute()
    {
        if (!$this->subscriptionPlan || $this->subscriptionPlan->token_cap_monthly == 0) return 0;
        
        return min(100, ($this->tokens_used_this_period / $this->subscriptionPlan->token_cap_monthly) * 100);
    }
}
