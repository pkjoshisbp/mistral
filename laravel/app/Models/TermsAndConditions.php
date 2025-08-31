<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsAndConditions extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'title', 
        'content',
        'is_active',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getTerms()
    {
        return self::where('type', 'terms')->where('is_active', true)->first();
    }

    public static function getPrivacyPolicy()
    {
        return self::where('type', 'privacy')->where('is_active', true)->first();
    }

    public static function getRefundPolicy()
    {
        return self::where('type', 'refund')->where('is_active', true)->first();
    }
}
