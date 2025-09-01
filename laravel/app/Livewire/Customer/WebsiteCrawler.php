<?php

namespace App\Livewire\Customer;

use Livewire\Component;
use App\Services\AiAgentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class WebsiteCrawler extends Component
{
    public $url = '';
    public $maxPages = 10;
    public $crawlDepth = 2;
    public $crawlStatus = '';
    public $crawledPages = [];
    public $isCrawling = false;

    protected $rules = [
        'url' => 'required|url',
        'maxPages' => 'required|integer|min:1|max:100',
        'crawlDepth' => 'required|integer|min:1|max:5',
    ];

    public function startCrawl()
    {
        $this->validate();

        $organization = Auth::user()->organization;
        if (!$organization) {
            session()->flash('error', 'No organization assigned to your account.');
            return;
        }

        try {
            $this->isCrawling = true;
            $this->crawlStatus = 'Starting crawl...';
            $this->crawledPages = [];

            // Call the AI agent service to start crawling
            $aiAgentService = new AiAgentService();
            
            // For demo purposes, we'll simulate the crawl process
            $this->crawlStatus = 'Crawling website...';
            
            // In a real implementation, this would be a background job
            $crawlData = [
                'url' => $this->url,
                'organization_id' => $organization->id,
                'max_pages' => $this->maxPages,
                'depth' => $this->crawlDepth,
            ];

            // Simulate crawl results
            $samplePages = [
                ['url' => $this->url, 'title' => 'Home Page', 'status' => 'success'],
                ['url' => $this->url . '/about', 'title' => 'About Us', 'status' => 'success'],
                ['url' => $this->url . '/services', 'title' => 'Our Services', 'status' => 'success'],
                ['url' => $this->url . '/contact', 'title' => 'Contact Us', 'status' => 'success'],
            ];

            $this->crawledPages = array_slice($samplePages, 0, min($this->maxPages, 4));
            $this->crawlStatus = 'Crawl completed successfully!';
            $this->isCrawling = false;

            session()->flash('message', 'Website crawl completed! ' . count($this->crawledPages) . ' pages processed.');

        } catch (\Exception $e) {
            $this->isCrawling = false;
            $this->crawlStatus = 'Crawl failed: ' . $e->getMessage();
            session()->flash('error', 'Crawl failed: ' . $e->getMessage());
        }
    }

    public function resetCrawl()
    {
        $this->reset(['url', 'maxPages', 'crawlDepth', 'crawlStatus', 'crawledPages', 'isCrawling']);
        $this->maxPages = 10;
        $this->crawlDepth = 2;
    }

    public function render()
    {
        return view('livewire.customer.website-crawler')
            ->layout('layouts.customer');
    }
}
