<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CrawlJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id', 'crawler_id', 'status',
        'total_urls', 'processed_count', 'failed_count',
        'batch_size', 'current_offset', 'current_url',
        'all_urls', 'failed_urls', 'crawl_log',
        'started_at', 'completed_at', 'error_message',
    ];

    protected $casts = [
        'all_urls'    => 'array',
        'failed_urls' => 'array',
        'crawl_log'   => 'array',
        'started_at'  => 'datetime',
        'completed_at'=> 'datetime',
    ];

    public function crawler()
    {
        return $this->belongsTo(WebsiteCrawler::class, 'crawler_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_urls <= 0) return 0;
        return min(100, (int) round(($this->processed_count + $this->failed_count) / $this->total_urls * 100));
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->total_urls - $this->processed_count - $this->failed_count);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['pending', 'running']);
    }

    /** Append a message to the crawl log (keeps last 50) */
    public function appendLog(string $message): void
    {
        $log = $this->crawl_log ?? [];
        $log[] = '[' . now()->format('H:i:s') . '] ' . $message;
        if (count($log) > 50) $log = array_slice($log, -50);
        $this->update(['crawl_log' => $log]);
    }
}
