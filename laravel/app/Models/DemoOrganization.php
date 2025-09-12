<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemoOrganization extends Model
{
    use HasFactory;

    protected $fillable = [
        'industry',
        'name',
        'description',
        'features',
        'sample_questions',
        'ai_responses',
        'is_active'
    ];

    protected $casts = [
        'features' => 'array',
        'sample_questions' => 'array',
        'ai_responses' => 'array',
        'is_active' => 'boolean'
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByIndustry($query, $industry)
    {
        return $query->where('industry', $industry);
    }
}
