<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalAssistantProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'preferred_language',
        'tts_provider',
        'custom_vocabulary',
        'correction_map',
        'training_samples',
        'settings',
        'last_used_at',
    ];

    protected $casts = [
        'custom_vocabulary' => 'array',
        'correction_map' => 'array',
        'training_samples' => 'array',
        'settings' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
