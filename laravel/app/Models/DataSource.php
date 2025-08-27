<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSource extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'name',
        'description',
        'config',
        'status',
        'last_synced_at',
        'sync_stats',
        'error_message'
    ];

    protected $casts = [
        'config' => 'array',
        'sync_stats' => 'array',
        'last_synced_at' => 'datetime'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function getCollectionNameAttribute()
    {
        return "org_{$this->organization_id}_{$this->type}";
    }

    public function isActive()
    {
        return $this->status === 'active';
    }

    public function isSyncing()
    {
        return $this->status === 'syncing';
    }
}
