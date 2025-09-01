<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'question',
        'answer', 
        'category',
        'sort_order',
        'is_active'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
