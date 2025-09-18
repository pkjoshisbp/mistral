<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AffiliateCommission extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'affiliate_visit_id',
        'user_id',
        'parent_commission_id',
        'commissionable_type',
        'commissionable_id',
        'commission_type',
        'commission_rate',
        'order_value',
        'commission_amount',
        'status',
        'approved_at',
        'paid_at',
        'rejected_at',
        'rejection_reason',
        'commission_start_date',
        'commission_end_date',
        'is_recurring',
        'recurring_period',
        'payout_batch_id',
        'transaction_id',
        'payout_details'
    ];

    protected $casts = [
        'commission_rate' => 'decimal:2',
        'order_value' => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'rejected_at' => 'datetime',
        'commission_start_date' => 'datetime',
        'commission_end_date' => 'datetime',
        'is_recurring' => 'boolean',
        'payout_details' => 'array'
    ];

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            // Set commission dates for ongoing commissions
            if ($model->commission_type === 'ongoing') {
                $model->commission_start_date = now();
                $model->commission_end_date = now()->addYears(3); // 3-year limit
                $model->is_recurring = true;
            }
        });

        static::created(function ($model) {
            // Update affiliate earnings
            $model->affiliate->increment('pending_earnings', $model->commission_amount);
            $model->affiliate->increment('total_earnings', $model->commission_amount);
        });
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateVisit(): BelongsTo
    {
        return $this->belongsTo(AffiliateVisit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function commissionable(): MorphTo
    {
        return $this->morphTo();
    }

    public function parentCommission(): BelongsTo
    {
        return $this->belongsTo(AffiliateCommission::class, 'parent_commission_id');
    }

    public function childCommissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'parent_commission_id');
    }

    public function approve()
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now()
        ]);
    }

    public function markPaid($transactionId = null, $batchId = null, $details = [])
    {
        $oldAmount = $this->commission_amount;
        
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'transaction_id' => $transactionId,
            'payout_batch_id' => $batchId,
            'payout_details' => $details
        ]);

        // Update affiliate earnings
        $this->affiliate->decrement('pending_earnings', $oldAmount);
        $this->affiliate->increment('paid_earnings', $oldAmount);
        $this->affiliate->update(['last_payout_at' => now()]);
    }

    public function cancel()
    {
        $oldAmount = $this->commission_amount;
        
        $this->update(['status' => 'cancelled']);

        // Revert affiliate earnings
        $this->affiliate->decrement('pending_earnings', $oldAmount);
        $this->affiliate->decrement('total_earnings', $oldAmount);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'info',
            'paid' => 'success',
            'cancelled' => 'danger',
            default => 'secondary'
        };
    }

    public function getStatusDisplayAttribute()
    {
        return ucfirst($this->status);
    }

    public function isExpired(): bool
    {
        return $this->commission_end_date && $this->commission_end_date < now();
    }

    public static function createCommission($affiliateVisit, $commissionable, $orderValue)
    {
        $affiliate = $affiliateVisit->affiliate;
        $commissionRate = $affiliate->commission_rate;
        $commissionAmount = $affiliate->calculateCommission($orderValue);

        return static::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_visit_id' => $affiliateVisit->id,
            'user_id' => $affiliateVisit->user_id ?? $commissionable->user_id ?? null,
            'commissionable_type' => get_class($commissionable),
            'commissionable_id' => $commissionable->id,
            'commission_type' => $affiliate->commission_type,
            'commission_rate' => $commissionRate,
            'order_value' => $orderValue,
            'commission_amount' => $commissionAmount,
            'status' => 'pending'
        ]);
    }
}
