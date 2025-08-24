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
        'database_name',
        'api_key',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    public function organizationData()
    {
        return $this->hasMany(OrganizationData::class);
    }

    public function getCollectionNameAttribute()
    {
        return "org_{$this->id}";
    }
}
