<?php

namespace App\Livewire;

use App\Models\CrawlJob;
use App\Models\Organization;
use App\Models\WebsiteCrawler;
use App\Services\WebsiteCrawlerService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class WebsiteCrawlerManager extends Component
{
    use WithFileUploads;

    public $organizations;
    public $selectedOrgId;
    public $crawlers;
    public $showCreateForm = false;

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

    public $page_type = 'product';
    public $attribute_schema = '';
    public $noise_selectors_input = '';
    public $url_filter_pattern = '';
    public $extraction_method = 'llm';
    public $extraction_prompt_override = '';
    public $qdrant_data_type = 'product';

    public $sample_page_url = '';
    public $sampleAnalysis = [];
    public $sampleExtractionPreview = [];
    public $selectedSuggestedAttributes = [];

    public $sitemap_file;
    public $activeCrawlJobs = [];

    protected $rules = [
        'selectedOrgId' => 'required|exists:organizations,id',
        'name' => 'required|min:3',
        'website_url' => 'required|url',
        'sitemap_url' => 'nullable|url',
        'max_depth' => 'integer|min:1|max:10',
        'max_pages' => 'integer|min:1|max:5000',
        'batch_size' => 'integer|min:5|max:100',
        'description' => 'nullable',
    ];

    public function mount()
    {
        $this->organizations = Organization::orderBy('name')->get();
        $this->crawlers = collect();
        $this->loadCrawlers();
        $this->refreshActiveCrawlJobs();
    }

    public function pollProgress()
    {
        $this->refreshActiveCrawlJobs();
    }

    public function refreshActiveCrawlJobs()
    {
        $query = CrawlJob::with('crawler')->orderByDesc('created_at');

        if ($this->selectedOrgId) {
            $query->where('organization_id', $this->selectedOrgId);
        }

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
                ->orderByDesc('created_at')
                ->get();

            return;
        }

        $this->crawlers = collect();
    }

    public function detectSitemap()
    {
        if (!$this->website_url) {
            return;
        }

        try {
            $detectedSitemap = app(WebsiteCrawlerService::class)->detectSitemap($this->website_url);

            if ($detectedSitemap) {
                $this->sitemap_url = $detectedSitemap;
                $this->crawl_type = 'sitemap';
                session()->flash('message', 'Sitemap found: ' . $detectedSitemap);
                return;
            }

            session()->flash('error', 'No sitemap found. Enter it manually, upload one, or use specific pages.');
        } catch (\Exception $e) {
            session()->flash('error', 'Error detecting sitemap: ' . $e->getMessage());
        }
    }

    public function uploadSitemap()
    {
        $this->validate(['sitemap_file' => 'required|file|mimes:xml,txt|max:5120']);

        try {
            $path = $this->sitemap_file->storeAs('sitemaps', time() . '_sitemap.xml');
            $content = Storage::get($path);
            $urls = [];

            if (preg_match_all('/<loc>(.*?)<\/loc>/', $content, $matches)) {
                foreach ($matches[1] as $url) {
                    $decodedUrl = html_entity_decode(trim($url));
                    if (filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
                        $urls[] = $decodedUrl;
                    }
                }
            }

            $this->specific_pages = implode("\n", array_slice($urls, 0, 5000));
            $this->crawl_type = 'specific_pages';
            session()->flash('message', count($urls) . ' URLs extracted from the sitemap file.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to process sitemap: ' . $e->getMessage());
        }
    }

    public function testCrawl()
    {
        if (!$this->website_url) {
            return;
        }

        try {
            $result = app(WebsiteCrawlerService::class)->testUrl($this->website_url);

            if ($result['success']) {
                session()->flash('message', 'Website is reachable. Title: ' . ($result['title'] ?? 'N/A'));
                return;
            }

            session()->flash('error', 'Website test failed: ' . ($result['error'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            session()->flash('error', 'Website test failed: ' . $e->getMessage());
        }
    }

    public function analyzeSamplePage()
    {
        $this->validate([
            'sample_page_url' => 'required|url',
        ]);

        try {
            $analysis = app(WebsiteCrawlerService::class)->analyzeSamplePage(
                $this->sample_page_url,
                [
                    'page_url' => $this->sample_page_url,
                    'website_url' => $this->website_url,
                    'noise_selectors' => $this->parseCommaSeparatedList($this->noise_selectors_input),
                    'page_type' => $this->page_type,
                    'attributes' => $this->parseCommaSeparatedList($this->attribute_schema),
                    'prompt_override' => trim($this->extraction_prompt_override) ?: null,
                ]
            );

            $this->sampleAnalysis = $analysis;
            $this->sampleExtractionPreview = [];
            $this->selectedSuggestedAttributes = $analysis['suggested_template']['attribute_schema'] ?? [];

            if (!$this->website_url && !empty($analysis['base_url'])) {
                $this->website_url = $analysis['base_url'];
            }

            session()->flash('message', 'Sample page analyzed. Review the suggested template, adjust it, then preview extraction.');
        } catch (\Exception $e) {
            session()->flash('error', 'Sample analysis failed: ' . $e->getMessage());
        }
    }

    public function applySuggestedTemplate()
    {
        if (empty($this->sampleAnalysis['suggested_template'])) {
            session()->flash('error', 'Run sample analysis first.');
            return;
        }

        $template = $this->sampleAnalysis['suggested_template'];
        $selectedAttributes = array_values(array_filter(array_map('trim', $this->selectedSuggestedAttributes ?? [])));

        if (!empty($selectedAttributes)) {
            $this->attribute_schema = implode(', ', $selectedAttributes);
        } elseif (!empty($template['attribute_schema'])) {
            $this->attribute_schema = implode(', ', $template['attribute_schema']);
        }

        if (!empty($template['page_type'])) {
            $this->page_type = $template['page_type'];
        }

        if (!empty($template['qdrant_data_type'])) {
            $this->qdrant_data_type = $template['qdrant_data_type'];
        }

        if (!empty($template['url_filter_pattern'])) {
            $this->url_filter_pattern = $template['url_filter_pattern'];
        }

        if (!$this->website_url && !empty($this->sampleAnalysis['base_url'])) {
            $this->website_url = $this->sampleAnalysis['base_url'];
        }

        session()->flash('message', 'Suggested template applied to the crawler form.');
    }

    public function previewSampleExtraction()
    {
        $this->validate([
            'sample_page_url' => 'required|url',
        ]);

        $attributes = $this->parseCommaSeparatedList($this->attribute_schema);

        if (empty($attributes) && !empty($this->selectedSuggestedAttributes)) {
            $attributes = array_values(array_filter(array_map('trim', $this->selectedSuggestedAttributes)));
        }

        if (empty($attributes)) {
            session()->flash('error', 'Add some attributes or apply the suggested template before previewing extraction.');
            return;
        }

        try {
            $this->sampleExtractionPreview = app(WebsiteCrawlerService::class)->previewSampleExtraction(
                $this->sample_page_url,
                [
                    'attributes' => $attributes,
                    'page_type' => $this->page_type,
                    'noise_selectors' => $this->parseCommaSeparatedList($this->noise_selectors_input),
                    'prompt_override' => trim($this->extraction_prompt_override) ?: null,
                ]
            );

            session()->flash('message', 'Sample extraction preview refreshed.');
        } catch (\Exception $e) {
            session()->flash('error', 'Could not preview extraction: ' . $e->getMessage());
        }
    }

    public function createCrawler()
    {
        $this->validate();

        try {
            WebsiteCrawler::create([
                'organization_id' => $this->selectedOrgId,
                'name' => $this->name,
                'website_url' => $this->website_url,
                'sitemap_url' => $this->crawl_type === 'sitemap' ? $this->sitemap_url : null,
                'specific_pages' => $this->crawl_type === 'specific_pages' && $this->specific_pages
                    ? $this->parseLineSeparatedList($this->specific_pages)
                    : null,
                'exclude_patterns' => $this->parseLineSeparatedList($this->exclude_patterns),
                'include_patterns' => $this->parseLineSeparatedList($this->include_patterns),
                'max_depth' => $this->max_depth,
                'max_pages' => $this->max_pages,
                'description' => $this->description,
                'is_active' => true,
                'page_type' => $this->page_type,
                'attribute_schema' => trim($this->attribute_schema),
                'noise_selectors' => $this->parseCommaSeparatedList($this->noise_selectors_input),
                'url_filter_pattern' => trim($this->url_filter_pattern) ?: null,
                'extraction_method' => $this->extraction_method,
                'extraction_prompt_override' => trim($this->extraction_prompt_override) ?: null,
                'qdrant_data_type' => $this->qdrant_data_type,
            ]);

            $this->loadCrawlers();
            $this->resetForm();
            $this->showCreateForm = false;
            session()->flash('message', 'Crawler created successfully.');
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to create crawler: ' . $e->getMessage());
        }
    }

    public function startCrawl($crawlerId)
    {
        try {
            $crawler = WebsiteCrawler::findOrFail($crawlerId);
            $batchSize = $crawler->max_pages > 0 ? min(20, $crawler->max_pages) : 20;
            $job = app(WebsiteCrawlerService::class)->startCrawlJob($crawler, $batchSize);

            session()->flash('message', "Crawl started. Job #{$job->id} queued with {$job->total_urls} URLs in batches of {$batchSize}.");
            $this->refreshActiveCrawlJobs();
        } catch (\Exception $e) {
            session()->flash('error', 'Could not start crawl: ' . $e->getMessage());
        }
    }

    public function cancelCrawlJob($jobId)
    {
        $job = CrawlJob::find($jobId);

        if ($job && $job->isRunning()) {
            $job->update([
                'status' => 'failed',
                'error_message' => 'Cancelled by user.',
            ]);
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
        $this->name = '';
        $this->website_url = '';
        $this->sitemap_url = '';
        $this->specific_pages = '';
        $this->exclude_patterns = '';
        $this->include_patterns = '';
        $this->max_depth = 3;
        $this->max_pages = 500;
        $this->batch_size = 20;
        $this->description = '';
        $this->crawl_type = 'sitemap';
        $this->page_type = 'product';
        $this->attribute_schema = '';
        $this->noise_selectors_input = '';
        $this->url_filter_pattern = '';
        $this->extraction_method = 'llm';
        $this->extraction_prompt_override = '';
        $this->qdrant_data_type = 'product';
        $this->sample_page_url = '';
        $this->sampleAnalysis = [];
        $this->sampleExtractionPreview = [];
        $this->selectedSuggestedAttributes = [];
        $this->sitemap_file = null;
    }

    public function render()
    {
        return view('livewire.website-crawler-manager');
    }

    private function parseCommaSeparatedList(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', explode(',', $value))));

        return empty($items) ? null : $items;
    }

    private function parseLineSeparatedList(?string $value): ?array
    {
        if (!$value) {
            return null;
        }

        $items = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value))));

        return empty($items) ? null : $items;
    }
}