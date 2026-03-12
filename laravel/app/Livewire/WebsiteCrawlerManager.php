<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\CrawlJob;
use App\Models\Organization;
use App\Models\WebsiteCrawler;
use App\Services\AiAgentService;
use App\Services\WebsiteCrawlerService;
use Illuminate\Support\Facades\Storage;

class WebsiteCrawlerManager extends Component
{
    use WithFileUploads;

    public $organizations;
    public $selectedOrgId;
    public $crawlers;
    public $showCreateForm = false;

    // Form fields
    public $name = '';
    public $website_url = '';
    public $sitemap_url = '';
    public $specific_pages = '';
    public $exclude_patterns = '';
    public $include_patterns = '';
    public $max_depth = 3;
    public $max_pages = 500;
    public $description = '';
    public $crawl_type = 'sitemap';
    public $batch_size = 20;

    // Attribute extraction fields
    public $page_type = 'product';
    public $attribute_schema = '';
    public $noise_selectors_input = '';
    public $url_filter_pattern = '';
    public $extraction_method = 'llm';
    public $extraction_prompt_override = '';
    public $qdrant_data_type = 'product';

    // File upload
    public $sitemap_file;

    // Active crawl job tracking (polled every 5s)
    public $activeCrawlJobs = [];

    protected $rules = [
        'name'        => 'required|min:3',
        'website_url' => 'required|url',
        'sitemap_url' => 'nullable|url',
        'max_depth'   => 'integer|min:1|max:10',
        'max_pages'   => 'integer|min:1|max:5000',
        'batch_size'  => 'integer|min:5|max:100',
        'description' => 'nullable',
    ];

    public function mount()
    {
        $this->organizations = Organization::all();
        $this->crawlers      = collect();
        $this->loadCrawlers();
        $this->refreshActiveCrawlJobs();
    }

    // Called by wire:poll.5s - updates progress for running jobs
    public function pollProgress()
    {
        $this->refreshActiveCrawlJobs();
    }

    public function refreshActiveCrawlJobs()
    {
        $query = CrawlJob::with('crawler')
            ->orderByDesc('created_at');

        if ($this->selectedOrgId) {
            $query->where('organization_id', $this->selectedOrgId);
        }

        // Show running + last 10 completed
        $this->activeCrawlJobs = $query->limit(15)->get()->toArray();
    }

    public function updatedSelectedOrgId()
    {
        $this->loadCrawlers();
        $this->resetForm();
        $this->refreshActiveCrawlJobs();
    }

    public function loadCrawlers()
    {
        if ($this->selectedOrgId) {
            $this->crawlers = WebsiteCrawler::where('organization_id', $this->selectedOrgId)
                ->orderBy('created_at', 'desc')->get();
        } else {
            $this->crawlers = collect();
        }
    }

    public function detectSitemap()
    {
        if (!$this->website_url) return;
        try {
            $crawlerService  = app(WebsiteCrawlerService::class);
            $detectedSitemap = $crawlerService->detectSitemap($this->website_url);
            if ($detectedSitemap) {
                $this->sitemap_url = $detectedSitemap;
                $this->crawl_type  = 'sitemap';
                session()->flash('message', 'Sitemap found: ' . $detectedSitemap);
            } else {
                session()->flash('error', 'No sitemap found. Enter manually or upload.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error: ' . $e->getMessage());
        }
    }

    public function uploadSitemap()
    {
        $this->validate(['sitemap_file' => 'required|file|mimes:xml,txt|max:5120']);
        try {
            $path    = $this->sitemap_file->storeAs('sitemaps', time() . '_sitemap.xml');
            $content = Storage::get($path);
            $urls    = [];
            if (preg_match_all('/<loc>(.*?)<\/loc>/', $content, $matches)) {
                foreach ($matches[1] as $url) {
                    $url = html_entity_decode(trim($url));
                    if (filter_var($url, FILTER_VALIDATE_URL)) $urls[] = $url;
                }
            }
            $this->specific_pages = implode("\n", array_slice($urls, 0, 5000));
            $this->crawl_type     = 'specific_pages';
            session()->flash('message', count($urls) . ' URLs extracted from sitemap.');
        } catch (\Exception $e) {
            session()->flash('error', 'Upload failed: ' . $e->getMessage());
        }
    }

    public function testCrawl()
    {
        if (!$this->website_url) return;
        try {
            $crawlerService = app(WebsiteCrawlerService::class);
            $result         = $crawlerService->testUrl($this->website_url);
            if ($result['success']) {
                session()->flash('message', 'Accessible! Title: ' . ($result['title'] ?? 'N/A'));
            } else {
                session()->flash('error', 'Cannot access: ' . $result['error']);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Test failed: ' . $e->getMessage());
        }
    }

    public function createCrawler()
    {
        $this->validate();
        try {
            WebsiteCrawler::create([
                'organization_id'           => $this->selectedOrgId,
                'name'                      => $this->name,
                'website_url'               => $this->website_url,
                'sitemap_url'               => $this->crawl_type === 'sitemap' ? $this->sitemap_url : null,
                'specific_pages'            => $this->crawl_type === 'specific_pages' && $this->specific_pages
                                                ? array_values(array_filter(explode("\n", $this->specific_pages)))
                                                : null,
                'exclude_patterns'          => $this->exclude_patterns
                                                ? array_values(array_filter(explode("\n", $this->exclude_patterns)))
                                                : null,
                'include_patterns'          => $this->include_patterns
                                                ? array_values(array_filter(explode("\n", $this->include_patterns)))
                                                : null,
                'max_depth'                 => $this->max_depth,
                'max_pages'                 => $this->max_pages,
                'description'               => $this->description,
                'is_active'                 => true,
                'page_type'                 => $this->page_type,
                'attribute_schema'          => trim($this->attribute_schema),
                'noise_selectors'           => $this->noise_selectors_input
                                                ? array_values(array_filter(array_map('trim', explode(',', $this->noise_selectors_input))))
                                                : null,
                'url_filter_pattern'        => trim($this->url_filter_pattern) ?: null,
                'extraction_method'         => $this->extraction_method,
                'extraction_prompt_override'=> trim($this->extraction_prompt_override) ?: null,
                'qdrant_data_type'          => $this->qdrant_data_type,
            ]);

            $this->loadCrawlers();
            $this->resetForm();
            $this->showCreateForm = false;
            session()->flash('message', 'Crawler created successfully!');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed: ' . $e->getMessage());
        }
    }

    /**
     * Kick off a batch crawl job for the given crawler.
     */
    public function startCrawl($crawlerId)
    {
        try {
            $crawler        = WebsiteCrawler::findOrFail($crawlerId);
            $crawlerService = app(WebsiteCrawlerService::class);
            $batchSize      = $crawler->max_pages > 0 ? min(20, $crawler->max_pages) : 20;

            $job = $crawlerService->startCrawlJob($crawler, $batchSize);

            session()->flash('message', "Crawl started! Job #{$job->id} — {$job->total_urls} URLs queued in batches of {$batchSize}.");
            $this->refreshActiveCrawlJobs();
        } catch (\Exception $e) {
            session()->flash('error', 'Could not start crawl: ' . $e->getMessage());
        }
    }

    /**
     * Cancel a running crawl job.
     */
    public function cancelCrawlJob($jobId)
    {
        $job = CrawlJob::find($jobId);
        if ($job && $job->isRunning()) {
            $job->update(['status' => 'failed', 'error_message' => 'Cancelled by user.']);
            session()->flash('message', "Job #{$jobId} cancelled.");
        }
        $this->refreshActiveCrawlJobs();
    }

    public function deleteCrawler($crawlerId)
    {
        WebsiteCrawler::find($crawlerId)?->delete();
        $this->loadCrawlers();
        session()->flash('message', 'Crawler deleted.');
    }

    public function resetForm()
    {
        $this->name                      = '';
        $this->website_url               = '';
        $this->sitemap_url               = '';
        $this->specific_pages            = '';
        $this->exclude_patterns          = '';
        $this->include_patterns          = '';
        $this->max_depth                 = 3;
        $this->max_pages                 = 500;
        $this->batch_size                = 20;
        $this->description               = '';
        $this->crawl_type                = 'sitemap';
        $this->sitemap_file              = null;
        $this->page_type                 = 'product';
        $this->attribute_schema          = '';
        $this->noise_selectors_input     = '';
        $this->url_filter_pattern        = '';
        $this->extraction_method         = 'llm';
        $this->extraction_prompt_override = '';
        $this->qdrant_data_type          = 'product';
    }

    public function render()
    {
        return view('livewire.website-crawler-manager');
    }
}

class WebsiteCrawlerManager extends Component
{
    use WithFileUploads;

    public $organizations;
    public $selectedOrgId;
    public $crawlers = [];
    public $showCreateForm = false;
    
    // Form fields
    public $name = '';
    public $website_url = '';
    public $sitemap_url = '';
    public $specific_pages = '';
    public $exclude_patterns = '';
    public $include_patterns = '';
    public $max_depth = 3;
    public $max_pages = 50;
    public $description = '';
    public $crawl_type = 'sitemap'; // sitemap, specific_pages, full_crawl

    // Attribute extraction fields
    public $page_type = 'product';
    public $attribute_schema = '';
    public $noise_selectors_input = ''; // comma-separated, converted to array on save
    public $url_filter_pattern = '';
    public $extraction_method = 'llm';
    public $extraction_prompt_override = '';
    public $qdrant_data_type = 'product';
    
    // File upload
    public $sitemap_file;
    
    // Status
    public $isCrawling = false;
    public $crawlProgress = [];
    public $currentCrawlId = null;
    public $progressPercent = 0;
    public $currentUrl = '';
    public $pagesProcessed = 0;
    public $pagesFailed = 0;
    public $totalPages = 0;

    protected $rules = [
        'name' => 'required|min:3',
        'website_url' => 'required|url',
        'sitemap_url' => 'nullable|url',
        'max_depth' => 'integer|min:1|max:10',
        'max_pages' => 'integer|min:1|max:500',
        'description' => 'nullable'
    ];

    public function mount()
    {
        $this->organizations = Organization::all();
        $this->loadCrawlers();
    }

    public function updatedSelectedOrgId()
    {
        $this->loadCrawlers();
        $this->resetForm();
    }

    public function loadCrawlers()
    {
        if ($this->selectedOrgId) {
            $this->crawlers = WebsiteCrawler::where('organization_id', $this->selectedOrgId)
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            $this->crawlers = collect();
        }
    }

    public function detectSitemap()
    {
        if (!$this->website_url) return;

        try {
            $aiAgentService = new AiAgentService();
            $crawlerService = new WebsiteCrawlerService($aiAgentService);
            $detectedSitemap = $crawlerService->detectSitemap($this->website_url);
            
            if ($detectedSitemap) {
                $this->sitemap_url = $detectedSitemap;
                $this->crawl_type = 'sitemap';
                session()->flash('message', 'Sitemap found: ' . $detectedSitemap);
            } else {
                session()->flash('error', 'No sitemap found at common locations. Please enter manually or upload sitemap file.');
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Error detecting sitemap: ' . $e->getMessage());
        }
    }

    public function uploadSitemap()
    {
        $this->validate([
            'sitemap_file' => 'required|file|mimes:xml,txt|max:5120', // 5MB max
        ]);

        try {
            $fileName = time() . '_sitemap.xml';
            $path = $this->sitemap_file->storeAs('sitemaps', $fileName);
            
            // Parse the sitemap to extract URLs
            $content = Storage::get($path);
            $urls = $this->parseSitemapContent($content);
            
            $this->specific_pages = implode("\n", $urls);
            $this->crawl_type = 'specific_pages';
            
            session()->flash('message', 'Sitemap uploaded and ' . count($urls) . ' URLs extracted!');
            
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to upload sitemap: ' . $e->getMessage());
        }
    }

    private function parseSitemapContent($content)
    {
        $urls = [];
        
        // Simple XML parsing for sitemap
        if (preg_match_all('/<loc>(.*?)<\/loc>/', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $url = trim($url);
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $urls[] = $url;
                }
            }
        }
        
        return array_slice($urls, 0, 100); // Limit to 100 URLs
    }

    public function testCrawl()
    {
        if (!$this->website_url) return;

        try {
            $aiAgentService = new AiAgentService();
            $crawlerService = new WebsiteCrawlerService($aiAgentService);
            $testResult = $crawlerService->testUrl($this->website_url);
            
            if ($testResult['success']) {
                session()->flash('message', 'Website is accessible! Title: ' . $testResult['title']);
            } else {
                session()->flash('error', 'Cannot access website: ' . $testResult['error']);
            }
            
        } catch (\Exception $e) {
            session()->flash('error', 'Test failed: ' . $e->getMessage());
        }
    }

    public function createCrawler()
    {
        $this->validate();

        try {
            $specificPages = $this->crawl_type === 'specific_pages' && $this->specific_pages 
                ? array_filter(explode("\n", $this->specific_pages))
                : null;
                
            $excludePatterns = $this->exclude_patterns 
                ? array_filter(explode("\n", $this->exclude_patterns))
                : null;
                
            $includePatterns = $this->include_patterns 
                ? array_filter(explode("\n", $this->include_patterns))
                : null;

            WebsiteCrawler::create([
                'organization_id' => $this->selectedOrgId,
                'name' => $this->name,
                'website_url' => $this->website_url,
                'sitemap_url' => $this->crawl_type === 'sitemap' ? $this->sitemap_url : null,
                'specific_pages' => $specificPages,
                'exclude_patterns' => $excludePatterns,
                'include_patterns' => $includePatterns,
                'max_depth' => $this->max_depth,
                'max_pages' => $this->max_pages,
                'description' => $this->description,
                'is_active' => true,
                // Attribute extraction
                'page_type' => $this->page_type,
                'attribute_schema' => trim($this->attribute_schema),
                'noise_selectors' => $this->noise_selectors_input
                    ? array_values(array_filter(array_map('trim', explode(',', $this->noise_selectors_input))))
                    : null,
                'url_filter_pattern' => trim($this->url_filter_pattern) ?: null,
                'extraction_method' => $this->extraction_method,
                'extraction_prompt_override' => trim($this->extraction_prompt_override) ?: null,
                'qdrant_data_type' => $this->qdrant_data_type,
            ]);

            $this->loadCrawlers();
            $this->resetForm();
            $this->showCreateForm = false;
            session()->flash('message', 'Website crawler created successfully!');

        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create crawler: ' . $e->getMessage());
        }
    }

    public function executeCrawl($crawlerId)
    {
        $this->isCrawling = true;
        $this->currentCrawlId = $crawlerId;
        $this->pagesProcessed = 0;
        $this->pagesFailed = 0;
        $this->totalPages = 0;
        $this->progressPercent = 5; // Show immediate feedback
        $this->currentUrl = 'Initializing crawl...';
        
        // Emit event to start progress polling
        $this->dispatch('crawl-started');
        
        session()->flash('message', 'Crawl started! This may take several minutes depending on the number of pages.');
        
        try {
            $crawler = WebsiteCrawler::find($crawlerId);
            $aiAgentService = new AiAgentService();
            $crawlerService = new WebsiteCrawlerService($aiAgentService);
            
            // Get total URLs first to show better progress
            $urlsToCrawl = $this->getUrlsForCrawler($crawler, $crawlerService);
            $this->totalPages = count($urlsToCrawl);
            $this->progressPercent = 10;
            $this->currentUrl = "Found {$this->totalPages} URLs to crawl";
            
            // Run the crawl
            $stats = $crawlerService->crawl($crawler);
            
            if ($stats && isset($stats['pages_processed'])) {
                session()->flash('message', "Crawl completed! {$stats['pages_processed']} pages processed, {$stats['pages_failed']} failed.");
            } else {
                session()->flash('error', 'Crawl completed but no pages were processed.');
            }

        } catch (\Exception $e) {
            session()->flash('error', 'Crawl execution failed: ' . $e->getMessage());
        }

        $this->isCrawling = false;
        $this->currentCrawlId = null;
        $this->progressPercent = 100;
        $this->currentUrl = '';
        $this->loadCrawlers();
    }

    private function getUrlsForCrawler($crawler, $crawlerService)
    {
        if ($crawler->sitemap_url) {
            return $crawlerService->parseSitemap($crawler->sitemap_url);
        }
        
        if ($crawler->specific_pages) {
            return $crawler->specific_pages;
        }
        
        // For full crawl, return estimated count
        return [$crawler->website_url];
    }

    public function updateProgress($progress)
    {
        if (isset($progress['total_pages'])) $this->totalPages = $progress['total_pages'];
        if (isset($progress['pages_processed'])) $this->pagesProcessed = $progress['pages_processed'];
        if (isset($progress['pages_failed'])) $this->pagesFailed = $progress['pages_failed'];
        if (isset($progress['current_url'])) $this->currentUrl = $progress['current_url'];
        if (isset($progress['progress_percent'])) $this->progressPercent = $progress['progress_percent'];
        
        // Emit event to update the frontend
        $this->dispatch('crawl-progress-updated', $progress);
    }

    public function deleteCrawler($crawlerId)
    {
        WebsiteCrawler::find($crawlerId)->delete();
        $this->loadCrawlers();
        session()->flash('message', 'Website crawler deleted successfully!');
    }

    public function resetForm()
    {
        $this->name = '';
        $this->website_url = '';
        $this->sitemap_url = '';
        $this->specific_pages = '';
        $this->exclude_patterns = '';
        $this->include_patterns = '';
        $this->max_depth = 3;
        $this->max_pages = 50;
        $this->description = '';
        $this->crawl_type = 'sitemap';
        $this->sitemap_file = null;
        // Attribute extraction
        $this->page_type = 'product';
        $this->attribute_schema = '';
        $this->noise_selectors_input = '';
        $this->url_filter_pattern = '';
        $this->extraction_method = 'llm';
        $this->extraction_prompt_override = '';
        $this->qdrant_data_type = 'product';
    }

    public function render()
    {
        return view('livewire.website-crawler-manager');
    }
}
