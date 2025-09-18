<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AffiliateLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'link_code',
        'type',
        'target_url',
        'package_id',
        'title',
        'description',
        'is_active',
        'clicks',
        'conversions',
        'conversion_rate',
        'earnings'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'conversion_rate' => 'decimal:2',
        'earnings' => 'decimal:2'
    ];

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->link_code)) {
                $model->link_code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode()
    {
        do {
            $code = strtolower(Str::random(10));
        } while (static::where('link_code', $code)->exists());
        
        return $code;
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(AffiliateVisit::class);
    }

    public function getFullUrlAttribute()
    {
        return url($this->target_url . '?ref=' . $this->link_code);
    }

    public function incrementClick()
    {
        $this->increment('clicks');
        $this->affiliate->increment('total_clicks');
    }

    public function incrementConversion($earnings = 0)
    {
        $this->increment('conversions');
        $this->increment('earnings', $earnings);
        $this->update(['conversion_rate' => ($this->conversions / max($this->clicks, 1)) * 100]);
    }

    public function getTypeDisplayAttribute()
    {
        return match($this->type) {
            'registration' => 'Registration',
            'subscription' => 'Subscription',
            'credit_package' => 'Credit Package',
            default => ucfirst($this->type)
        };
    }
}
