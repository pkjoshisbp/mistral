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
        'website', // unified field used in forms
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

    public function getCollectionNameAttribute()
    {
        return str_replace('-', '_', $this->slug);
    }
}
