<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Models\CrawlJob;
use App\Models\WebsiteCrawler as WebsiteCrawlerModel;
use App\Services\AiAgentService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Support\Facades\Auth;

class WebsiteCrawler extends Component
{
    public $crawlers      = [];
    public $crawlJobs     = [];
    public $organization  = null;

    public function mount()
    {
        $user               = Auth::user();
        $this->organization = $user->organizations()->first()
                            ?? $user->organization
                            ?? null;
        $this->loadData();
    }

    // Called by wire:poll.5s to keep jobs fresh
    public function pollProgress()
    {
        $this->loadCrawlJobs();
    }

    public function loadData()
    {
        $this->loadCrawlers();
        $this->loadCrawlJobs();
    }

    public function loadCrawlers()
    {
        if (!$this->organization) {
            $this->crawlers = collect();
            return;
        }
        $this->crawlers = WebsiteCrawlerModel::where('organization_id', $this->organization->id)
            ->orderByDesc('created_at')->get();
    }

    public function loadCrawlJobs()
    {
        if (!$this->organization) {
            $this->crawlJobs = collect();
            return;
        }
        $this->crawlJobs = CrawlJob::with('crawler')
            ->where('organization_id', $this->organization->id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();
    }

    public function startCrawl($crawlerId)
    {
        if (!$this->organization) {
            session()->flash('error', 'No organization linked to your account.');
            return;
        }

        $crawler = WebsiteCrawlerModel::where('id', $crawlerId)
            ->where('organization_id', $this->organization->id)
            ->first();

        if (!$crawler) {
            session()->flash('error', 'Crawler not found.');
            return;
        }

        try {
            $crawlerService = app(WebsiteCrawlerService::class);
            $job            = $crawlerService->startCrawlJob($crawler, 20);
            session()->flash('message', "Crawl started! Job #{$job->id} — {$job->total_urls} URLs queued. Progress updates every 5 seconds.");
            $this->loadCrawlJobs();
        } catch (\Exception $e) {
            session()->flash('error', 'Could not start crawl: ' . $e->getMessage());
        }
    }

    public function cancelCrawlJob($jobId)
    {
        if (!$this->organization) return;

        $job = CrawlJob::where('id', $jobId)
            ->where('organization_id', $this->organization->id)
            ->first();

        if ($job && $job->isRunning()) {
            $job->update(['status' => 'failed', 'error_message' => 'Cancelled by user.']);
            session()->flash('message', "Job #{$jobId} cancelled.");
        }
        $this->loadCrawlJobs();
    }

    public function render()
    {
        return view('livewire.customer.website-crawler')
            ->layout('layouts.customer');
    }
}
