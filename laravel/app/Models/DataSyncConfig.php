<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DataSyncConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'type',
        'query',
        'table_config',
        'field_mapping',
        'sync_frequency',
        'last_synced_at',
        'is_active',
        'description'
    ];

    protected $casts = [
        'table_config' => 'array',
        'field_mapping' => 'array',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
