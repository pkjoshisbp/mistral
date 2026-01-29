<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'source', // e.g. 'chat', 'widget', etc.
        'organization_id',
        'user_id', // if captured after login
        'session_id',
        'location_data',
        'intent',
        'intent_confidence',
        'priority',
        'status',
        'last_message',
        'last_intent_at'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
