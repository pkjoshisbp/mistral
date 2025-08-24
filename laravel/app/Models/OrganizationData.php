<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationData extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'description',
        'content',
        'metadata',
        'is_synced',
        'last_synced_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_synced' => 'boolean',
        'last_synced_at' => 'datetime'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
