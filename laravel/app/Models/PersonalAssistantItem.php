<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PersonalAssistantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'type',
        'title',
        'content',
        'due_at',
        'status',
        'meta',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'meta' => 'array',
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
