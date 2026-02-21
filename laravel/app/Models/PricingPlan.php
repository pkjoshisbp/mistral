<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PricingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'plan_type',
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_period',
        'token_cap',
        'overage_price_per_100k',
        'credits',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'overage_price_per_100k' => 'decimal:2',
        'token_cap' => 'integer',
        'credits' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSubscriptions($query)
    {
        return $query->where('plan_type', 'subscription');
    }

    public function scopeCredits($query)
    {
        return $query->where('plan_type', 'credit');
    }

    public function getFormattedPriceAttribute(): string
    {
        $currency = $this->currency ?: 'USD';
        $symbol = strtoupper($currency) === 'INR' ? '₹' : '$';
        $amount = $this->price ?? 0;

        return $symbol . number_format($amount, 0);
    }

    public function getPriceForCurrency(string $currency): ?float
    {
        $currency = strtoupper($currency);
        if ($currency === 'INR') {
            $inr = $this->metadata['inr_price'] ?? null;
            return $inr !== null ? (float) $inr : null;
        }

        return $this->price !== null ? (float) $this->price : null;
    }

    public function getFormattedTokenCapAttribute(): ?string
    {
        if ($this->token_cap === null) {
            return null;
        }

        return self::formatTokenCap((int) $this->token_cap);
    }

    public static function formatTokenCap(int $tokenCap): string
    {
        if ($tokenCap >= 1000000) {
            $value = $tokenCap / 1000000;
            $formatted = fmod($value, 1.0) === 0.0
                ? number_format($value, 0)
                : rtrim(rtrim(number_format($value, 1), '0'), '.');
            return $formatted . 'M';
        }

        if ($tokenCap >= 1000) {
            $value = $tokenCap / 1000;
            $formatted = fmod($value, 1.0) === 0.0
                ? number_format($value, 0)
                : rtrim(rtrim(number_format($value, 1), '0'), '.');
            return $formatted . 'K';
        }

        return (string) $tokenCap;
    }

    public function getFormattedCreditsAttribute(): ?string
    {
        if ($this->credits === null) {
            return null;
        }

        if ($this->credits >= 1000000) {
            return number_format($this->credits / 1000000, 1) . 'M';
        }

        if ($this->credits >= 1000) {
            return number_format($this->credits / 1000) . 'K';
        }

        return number_format($this->credits);
    }
}
