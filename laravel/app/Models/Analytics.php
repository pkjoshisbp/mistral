<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'visitor_id',
        'session_id',
        'event_type',
        'page_url',
        'page_title',
        'referrer',
        'user_agent',
        'ip_address',
        'country',
        'region',
        'city',
        'event_data',
        'time_on_page'
    ];

    protected $casts = [
        'event_data' => 'array'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
