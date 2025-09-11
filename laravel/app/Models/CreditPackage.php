<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug', 
        'description',
        'usd_price',
        'inr_price',
        'tokens',
        'features',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'tokens' => 'integer',
        'usd_price' => 'decimal:2',
        'inr_price' => 'decimal:2'
    ];

    public function getFormattedTokensAttribute()
    {
        if ($this->tokens >= 1000000) {
            return number_format($this->tokens / 1000000, 1) . 'M';
        } elseif ($this->tokens >= 1000) {
            return number_format($this->tokens / 1000) . 'K';
        }
        return number_format($this->tokens);
    }

    public function getPriceForCurrency($currency)
    {
        return $currency === 'INR' ? $this->inr_price : $this->usd_price;
    }

    public function getCurrencySymbol($currency)
    {
        return $currency === 'INR' ? '₹' : '$';
    }
}
