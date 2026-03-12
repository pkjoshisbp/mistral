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
        'description',
        // Attribute extraction fields
        'page_type',
        'attribute_schema',
        'noise_selectors',
        'url_filter_pattern',
        'extraction_method',
        'extraction_prompt_override',
        'qdrant_data_type',
    ];

    protected $casts = [
        'specific_pages' => 'array',
        'exclude_patterns' => 'array',
        'include_patterns' => 'array',
        'crawl_stats' => 'array',
        'noise_selectors' => 'array',
        'last_crawled_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Parse attribute_schema (comma-separated string) into a clean array.
     */
    public function getAttributeListAttribute(): array
    {
        if (empty($this->attribute_schema)) {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $this->attribute_schema))));
    }

    /**
     * Default noise selectors always stripped (plus client-configured ones).
     */
    public function getNoiseSelectorsWithDefaultsAttribute(): array
    {
        $defaults = ['nav', 'header', 'footer', 'script', 'style', 'iframe',
                     'noscript', '.cart', '.header', '.footer', '.navigation',
                     '.sidebar', '.breadcrumb', '.related', '.ads', '.cookie-banner',
                     '#cart', '#header', '#footer', '#navigation', '#sidebar'];
        $custom = $this->noise_selectors ?? [];
        return array_unique(array_merge($defaults, $custom));
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
