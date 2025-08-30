<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'paypal_plan_id',
        'description',
        'monthly_price',
        'yearly_price',
        'token_cap_monthly',
        'overage_price_per_100k',
        'features',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'features' => 'array',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'overage_price_per_100k' => 'decimal:2',
        'is_active' => 'boolean'
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function getFormattedMonthlyPriceAttribute()
    {
        return '$' . number_format($this->monthly_price, 0);
    }

    public function getFormattedYearlyPriceAttribute()
    {
        return '$' . number_format($this->yearly_price, 0);
    }

    public function getFormattedTokenCapAttribute()
    {
        if ($this->token_cap_monthly >= 1000000) {
            return (number_format($this->token_cap_monthly / 1000000, 0)) . 'M';
        }
        return number_format($this->token_cap_monthly / 1000, 0) . 'K';
    }
}
