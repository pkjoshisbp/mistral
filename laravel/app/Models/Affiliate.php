<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Affiliate extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'affiliate_code',
        'name',
        'email', 
        'phone',
        'company',
        'description',
        'status',
        'commission_type',
        'one_time_rate',
        'ongoing_rate',
        'payment_method',
        'bank_name',
        'account_number',
        'ifsc_code',
        'account_holder_name',
        'upi_id',
        'total_clicks',
        'total_registrations', 
        'total_purchases',
        'total_earnings',
        'paid_earnings',
        'pending_earnings',
        'approved_at',
        'last_payout_at',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'approved_at' => 'datetime',
        'last_payout_at' => 'datetime',
        'one_time_rate' => 'decimal:2',
        'ongoing_rate' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'paid_earnings' => 'decimal:2',
        'pending_earnings' => 'decimal:2'
    ];

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->affiliate_code)) {
                $model->affiliate_code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode()
    {
        do {
            $code = 'AFF' . strtoupper(Str::random(8));
        } while (static::where('affiliate_code', $code)->exists());
        
        return $code;
    }

    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function visits(): HasMany  
    {
        return $this->hasMany(AffiliateVisit::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referredUsers(): HasMany
    {
        return $this->hasMany(User::class, 'referred_by_affiliate_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function approve()
    {
        $this->update([
            'status' => 'active',
            'approved_at' => now()
        ]);
    }

    public function getCommissionRateAttribute()
    {
        return $this->commission_type === 'one_time' ? $this->one_time_rate : $this->ongoing_rate;
    }

    public function calculateCommission($orderValue)
    {
        return ($orderValue * $this->commission_rate) / 100;
    }
}
