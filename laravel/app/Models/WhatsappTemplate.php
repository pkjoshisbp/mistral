<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'language',
        'category',
        'status',
        'header_type',
        'header_text',
        'header_media_url',
        'body_text',
        'footer_text',
        'body_variable_count',
        'buttons',
        'raw_components',
        'raw_payload',
        'waba_id',
        'is_active',
    ];

    protected $casts = [
        'buttons' => 'array',
        'raw_components' => 'array',
        'raw_payload' => 'array',
        'is_active' => 'boolean',
        'body_variable_count' => 'integer',
    ];

    public function scopeApproved($query)
    {
        return $query->where('status', 'APPROVED');
    }
}
