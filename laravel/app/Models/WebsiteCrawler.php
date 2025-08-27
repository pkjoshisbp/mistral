<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteCrawler extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'website_url',
        'sitemap_url',
        'specific_pages',
        'exclude_patterns',
        'include_patterns',
        'max_depth',
        'max_pages',
        'crawl_frequency',
        'last_crawled_at',
        'crawl_stats',
        'is_active',
        'description'
    ];

    protected $casts = [
        'specific_pages' => 'array',
        'exclude_patterns' => 'array',
        'include_patterns' => 'array',
        'crawl_stats' => 'array',
        'last_crawled_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
