<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'action_id',
        'action_type',
        'source_type',
        'status',
        'attempts',
        'duration_ms',
        'params',
        'result_meta',
        'error_message',
    ];

    protected $casts = [
        'params' => 'array',
        'result_meta' => 'array',
        'attempts' => 'integer',
        'duration_ms' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function action()
    {
        return $this->belongsTo(OrganizationAction::class, 'action_id');
    }
}
