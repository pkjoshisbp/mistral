<?php

namespace App\Console\Commands;

use App\Models\CrawlJob;
use App\Models\WebsiteCrawler;
use App\Services\AiAgentService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Console\Command;

class CrawlRunBatch extends Command
{
    protected $signature = 'crawl:run-batch {job_id : The CrawlJob ID to process}
                            {--batch-size=20 : Number of URLs to process in this batch}';

    protected $description = 'Process one batch of URLs for a crawl job, then chain the next batch';

    public function handle(): int
    {
        $jobId     = (int) $this->argument('job_id');
        $batchSize = (int) $this->option('batch-size');

        $crawlJob = CrawlJob::find($jobId);
        if (!$crawlJob) {
            $this->error("CrawlJob #{$jobId} not found.");
            return 1;
        }

        // Guard: only run if still in a runnable state
        if (!in_array($crawlJob->status, ['pending', 'running'])) {
            $this->info("CrawlJob #{$jobId} status={$crawlJob->status}, skipping.");
            return 0;
        }

        $crawler = WebsiteCrawler::find($crawlJob->crawler_id);
        if (!$crawler) {
            $crawlJob->update(['status' => 'failed', 'error_message' => 'Crawler config deleted.']);
            return 1;
        }

        // Mark as running on first batch
        if ($crawlJob->status === 'pending') {
            $crawlJob->update([
                'status'     => 'running',
                'started_at' => now(),
            ]);
            $crawlJob->appendLog("Crawl started. Total URLs: {$crawlJob->total_urls}");
        }

        // Slice the batch
        $allUrls = $crawlJob->all_urls ?? [];
        $offset  = $crawlJob->current_offset;
        $batch   = array_slice($allUrls, $offset, $batchSize);

        if (empty($batch)) {
            $crawlJob->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'current_url'  => null,
            ]);
            $crawlJob->appendLog("All URLs processed. Done.");
            // Update the parent crawler's last_crawled_at
            $crawler->update([
                'last_crawled_at' => now(),
                'crawl_stats' => [
                    'pages_processed' => $crawlJob->processed_count,
                    'pages_failed'    => $crawlJob->failed_count,
                    'total_urls'      => $crawlJob->total_urls,
                ],
            ]);
            $this->info("CrawlJob #{$jobId} completed.");
            return 0;
        }

        // Process each URL in the batch
        $aiService      = app(AiAgentService::class);
        $crawlerService = app(WebsiteCrawlerService::class);
        $processed      = $crawlJob->processed_count;
        $failed         = $crawlJob->failed_count;
        $failedUrls     = $crawlJob->failed_urls ?? [];

        foreach ($batch as $url) {
            $crawlJob->update(['current_url' => $url]);

            $success = $crawlerService->crawlSinglePagePublic($url, $crawler);

            if ($success) {
                $processed++;
                $crawlJob->appendLog("OK: {$url}");
            } else {
                $failed++;
                $failedUrls[] = $url;
                $crawlJob->appendLog("FAIL: {$url}");
            }

            $newOffset = $offset + 1;
            $offset++;

            $crawlJob->update([
                'processed_count' => $processed,
                'failed_count'    => $failed,
                'current_offset'  => $newOffset,
                'failed_urls'     => $failedUrls,
            ]);

            // Respectful delay between requests
            sleep(1);
        }

        // Check if more URLs remain
        $nextOffset = $crawlJob->current_offset;
        if ($nextOffset < count($allUrls)) {
            // Spawn next batch as background process
            $artisan = base_path('artisan');
            $php     = PHP_BINARY;
            $cmd     = "{$php} {$artisan} crawl:run-batch {$jobId} --batch-size={$batchSize}";
            $this->info("Spawning next batch: offset={$nextOffset}");
            exec('nohup ' . $cmd . ' > /dev/null 2>&1 &');
        } else {
            // All done
            $crawlJob->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'current_url'  => null,
            ]);
            $crawlJob->appendLog("All URLs processed. Done. Processed: {$processed}, Failed: {$failed}");
            $crawler->update([
                'last_crawled_at' => now(),
                'crawl_stats' => [
                    'pages_processed' => $processed,
                    'pages_failed'    => $failed,
                    'total_urls'      => count($allUrls),
                ],
            ]);
        }

        $this->info("Batch done. Processed={$processed} Failed={$failed} Offset={$nextOffset}");
        return 0;
    }
}
