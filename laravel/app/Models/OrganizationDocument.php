<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'title',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'category',
        'description',
        'keywords',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
