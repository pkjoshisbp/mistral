<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
    'website', // canonical website field used in forms; legacy website_url displayed read-only where present
        'contact_email',
        'contact_phone',
        'timezone',
        'api_key',
        'settings',
        'api_endpoints',
        'api_token',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'api_endpoints' => 'array',
        'is_active' => 'boolean'
    ];

    public function organizationData()
    {
        return $this->hasMany(OrganizationData::class);
    }

    public function users()
    {
        // Canonical many-to-many list of users
        return $this->belongsToMany(User::class, 'organization_user');
    }

    /**
     * Legacy direct hasMany (via users.organization_id) still supported where needed.
     */
    public function legacyUsers()
    {
        return $this->hasMany(User::class);
    }

    public function dataSources()
    {
        return $this->hasMany(DataSource::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function tokenUsageLogs()
    {
        return $this->hasMany(TokenUsageLog::class);
    }

    public function chatSessions()
    {
        return $this->hasMany(ChatSession::class);
    }

    public function chatConversations()
    {
        return $this->hasMany(ChatConversation::class);
    }

    public function integrations()
    {
        return $this->hasMany(Integration::class);
    }

    public function getCollectionNameAttribute()
    {
        return str_replace('-', '_', $this->slug);
    }

    public function customerReviews()
    {
        return $this->hasMany(CustomerReview::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(CustomerReview::class)->approved();
    }

    public function featuredReviews()
    {
        return $this->hasMany(CustomerReview::class)->approved()->featured();
    }

    public function getAverageRatingAttribute()
    {
        return $this->approvedReviews()->avg('rating') ?: 0;
    }

    public function getReviewsCountAttribute()
    {
        return $this->approvedReviews()->count();
    }
}
