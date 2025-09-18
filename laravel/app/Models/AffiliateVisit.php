<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AffiliateVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'affiliate_id',
        'affiliate_link_id',
        'user_id',
        'visitor_id',
        'ip_address',
        'user_agent',
        'referrer',
        'landing_page',
        'first_visit_at',
        'last_visit_at',
        'expires_at',
        'converted',
        'converted_at',
        'conversion_type',
        'conversion_value'
    ];

    protected $casts = [
        'first_visit_at' => 'datetime',
        'last_visit_at' => 'datetime',
        'expires_at' => 'datetime',
        'converted' => 'boolean',
        'converted_at' => 'datetime',
        'conversion_value' => 'decimal:2'
    ];

    public static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->visitor_id)) {
                $model->visitor_id = static::generateVisitorId();
            }
            
            $now = now();
            if (empty($model->first_visit_at)) {
                $model->first_visit_at = $now;
            }
            if (empty($model->last_visit_at)) {
                $model->last_visit_at = $now;
            }
            if (empty($model->expires_at)) {
                $model->expires_at = $now->addDays(15); // 15-day attribution window
            }
        });
    }

    public static function generateVisitorId()
    {
        return Str::random(64);
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function affiliateLink(): BelongsTo
    {
        return $this->belongsTo(AffiliateLink::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->converted;
    }

    public function markConverted($type, $value = 0)
    {
        $this->update([
            'converted' => true,
            'converted_at' => now(),
            'conversion_type' => $type,
            'conversion_value' => $value
        ]);

        // Update affiliate statistics
        if ($type === 'registration') {
            $this->affiliate->increment('total_registrations');
        } elseif ($type === 'purchase') {
            $this->affiliate->increment('total_purchases');
        }
    }

    public static function findValidVisit($visitorId, $affiliateId = null)
    {
        $query = static::where('visitor_id', $visitorId)
            ->where('expires_at', '>', now())
            ->where('converted', false);

        if ($affiliateId) {
            $query->where('affiliate_id', $affiliateId);
        }

        return $query->first();
    }

    public static function createOrUpdateVisit($affiliateId, $linkId, $visitorData)
    {
        $visit = static::where('visitor_id', $visitorData['visitor_id'])
            ->where('affiliate_id', $affiliateId)
            ->first();

        if ($visit) {
            // Update existing visit
            $visit->update([
                'last_visit_at' => now(),
                'landing_page' => $visitorData['landing_page'],
                'user_agent' => $visitorData['user_agent'],
                'referrer' => $visitorData['referrer'] ?? $visit->referrer
            ]);
        } else {
            // Create new visit
            $visit = static::create(array_merge($visitorData, [
                'affiliate_id' => $affiliateId,
                'affiliate_link_id' => $linkId
            ]));
        }

        return $visit;
    }
}
