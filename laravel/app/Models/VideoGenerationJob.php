<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoGenerationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'created_by',
        'title',
        'status',
        'target_duration_seconds',
        'aspect_ratio',
        'language',
        'speaker',
        'scenes',
        'settings',
        'backend_job_id',
        'backend_response',
        'output_video_path',
        'output_video_url',
        'error_message',
        'requested_at',
        'completed_at',
    ];

    protected $casts = [
        'scenes' => 'array',
        'settings' => 'array',
        'backend_response' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
