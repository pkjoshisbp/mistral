<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function organization()
    {
        // Legacy single organization reference (organization_id column)
        return $this->belongsTo(Organization::class);
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'organization_user');
    }

    /**
     * Helper to consistently fetch the primary organization for user (prefers pivot, falls back).
     */
    public function primaryOrganization()
    {
        if ($this->relationLoaded('organizations')) {
            $org = $this->organizations->first();
            if ($org) return $org;
        } else {
            $org = $this->organizations()->first();
            if ($org) return $org;
        }
        return $this->organization; // fallback to legacy column
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription()
    {
        return $this->hasOne(Subscription::class)
                    ->where('status', 'active')
                    ->where('current_period_end', '>', now());
    }

    public function tokenUsageLogs()
    {
        return $this->hasMany(TokenUsageLog::class);
    }

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    public function userCredit()
    {
        return $this->hasOne(UserCredit::class);
    }

    public function creditTransactions()
    {
        return $this->hasMany(CreditTransaction::class);
    }

    /**
     * Get user's credit balance (create record if doesn't exist)
     */
    public function getCreditBalance()
    {
        $credit = $this->userCredit ?? UserCredit::getOrCreateForUser($this->id);
        return $credit->balance;
    }

    /**
     * Check if user can access premium features
     * Returns true if user has active subscription OR sufficient credits
     */
    public function canAccessPremiumFeatures($minimumCredits = 1.0)
    {
        // Admins always have access
        if ($this->isAdmin()) {
            return true;
        }

        // Check for active subscription first
        if ($this->activeSubscription) {
            return true;
        }

        // Check for sufficient credits
        return $this->getCreditBalance() >= $minimumCredits;
    }

    /**
     * Check if user has any form of access (subscription or credits)
     * Used for basic feature access checks
     */
    public function hasAnyAccess()
    {
        return $this->canAccessPremiumFeatures(0.1); // Minimum 0.1 credits or active subscription
    }

    // Affiliate relationships
    public function affiliate()
    {
        return $this->hasOne(Affiliate::class);
    }

    public function referredByAffiliate()
    {
        return $this->belongsTo(Affiliate::class, 'referred_by_affiliate_id');
    }

    public function affiliateVisits()
    {
        return $this->hasMany(AffiliateVisit::class);
    }

    public function affiliateCommissions()
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function isAffiliate(): bool
    {
        return $this->role === 'affiliate' && $this->affiliate !== null;
    }

    public function customerReviews()
    {
        return $this->hasMany(CustomerReview::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(CustomerReview::class, 'approved_by');
    }
}
