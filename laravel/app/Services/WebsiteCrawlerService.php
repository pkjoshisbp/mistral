<?php

namespace App\Services;

use App\Models\CrawlJob;
use App\Models\Organization;
use App\Models\OrganizationData;
use App\Models\WebsiteCrawler;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

// Include the simple HTML DOM parser
require_once base_path('vendor/simple-html-dom/simple-html-dom/simple_html_dom.php');

class WebsiteCrawlerService
{
    private Client $httpClient;
    private AiAgentService $aiAgentService;
    private array $visitedUrls = [];
    private int $maxExecutionTime = 300; // 5 minutes
    
    public function __construct(AiAgentService $aiAgentService)
    {
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'AI Agent Website Crawler 1.0'
            ]
        ]);
        $this->aiAgentService = $aiAgentService;
    }

    /**
     * Create a CrawlJob record and kick off the first batch in background.
     * Returns the CrawlJob.
     */
    public function startCrawlJob(WebsiteCrawler $crawler, int $batchSize = 20): CrawlJob
    {
        $urls = $this->getUrlsToCrawl($crawler);

        // Filter to only crawlable URLs
        $filtered = array_values(array_filter($urls, fn($u) => $this->shouldCrawlUrl($u, $crawler)));

        // Respect max_pages config
        if ($crawler->max_pages > 0 && count($filtered) > $crawler->max_pages) {
            $filtered = array_slice($filtered, 0, $crawler->max_pages);
        }

        $job = CrawlJob::create([
            'organization_id' => $crawler->organization_id,
            'crawler_id'      => $crawler->id,
            'status'          => 'pending',
            'total_urls'      => count($filtered),
            'processed_count' => 0,
            'failed_count'    => 0,
            'batch_size'      => $batchSize,
            'current_offset'  => 0,
            'all_urls'        => $filtered,
            'crawl_log'       => ['Crawl job created. ' . count($filtered) . ' URLs queued.'],
        ]);

        // Launch first batch as a detached background process (nohup ensures it
        // survives SSH session closure / VS Code disconnect)
        $artisan = base_path('artisan');
        $php     = PHP_BINARY;
        $cmd     = "{$php} {$artisan} crawl:run-batch {$job->id} --batch-size={$batchSize}";
        exec('nohup ' . $cmd . ' > /dev/null 2>&1 &');

        Log::info("CrawlJob #{$job->id} started. {$job->total_urls} URLs, batch_size={$batchSize}");

        return $job;
    }

    /**
     * Public wrapper around crawlSinglePage so the artisan command can call it.
     */
    public function crawlSinglePagePublic(string $url, WebsiteCrawler $crawler): bool
    {
        return $this->crawlSinglePage($url, $crawler);
    }

    public function testUrl($url)
    {
        try {
            $response = $this->httpClient->get($url);
            $body = $response->getBody()->getContents();
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'title' => $this->extractTitle($body),
                'content_length' => strlen($body)
            ];
        } catch (GuzzleException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function detectSitemap($baseUrl)
    {
        $commonSitemapPaths = [
            '/sitemap.xml',
            '/sitemap_index.xml',
            '/sitemaps.xml',
            '/sitemap/sitemap.xml',
            '/sitemap/index.xml'
        ];

        foreach ($commonSitemapPaths as $path) {
            $sitemapUrl = rtrim($baseUrl, '/') . $path;
            
            try {
                $response = $this->httpClient->head($sitemapUrl);
                if ($response->getStatusCode() === 200) {
                    return $sitemapUrl;
                }
            } catch (GuzzleException $e) {
                // Continue to next path
            }
        }

        return null;
    }

    public function parseSitemap($sitemapUrl)
    {
        try {
            $response = $this->httpClient->get($sitemapUrl);
            $xmlContent = $response->getBody()->getContents();
            
            $xml = simplexml_load_string($xmlContent);
            $urls = [];

            if ($xml) {
                // Handle sitemap index files
                if (isset($xml->sitemap)) {
                    foreach ($xml->sitemap as $sitemap) {
                        $subSitemapUrl = (string) $sitemap->loc;
                        $subUrls = $this->parseSitemap($subSitemapUrl);
                        $urls = array_merge($urls, $subUrls);
                    }
                }
                
                // Handle regular sitemap files
                if (isset($xml->url)) {
                    foreach ($xml->url as $url) {
                        $urls[] = (string) $url->loc;
                    }
                }
            }

            return array_unique($urls);
        } catch (GuzzleException $e) {
            Log::error('Failed to parse sitemap: ' . $e->getMessage());
            return [];
        }
    }

    public function analyzeSamplePage(string $url, array $options = []): array
    {
        $pageData = $this->fetchSamplePageData($url, $options['noise_selectors'] ?? []);
        $suggestedTemplate = $this->suggestTemplateForPage($pageData, $options + ['page_url' => $url]);

        return [
            'url' => $url,
            'base_url' => $this->buildBaseUrl($url),
            'title' => $pageData['title'],
            'meta_description' => $pageData['meta_description'],
            'headings' => $pageData['headings'],
            'content_excerpt' => $pageData['content_excerpt'],
            'suggested_template' => $suggestedTemplate,
        ];
    }

    public function previewSampleExtraction(string $url, array $options = []): array
    {
        $attributes = array_values(array_filter($options['attributes'] ?? []));

        if (empty($attributes)) {
            throw new \InvalidArgumentException('No attributes provided for preview extraction.');
        }

        $pageData = $this->fetchSamplePageData($url, $options['noise_selectors'] ?? []);
        $result = $this->extractAttributesViaLlm(
            $pageData['content'],
            $attributes,
            $options['page_type'] ?? 'general',
            $url,
            $options['prompt_override'] ?? null
        );

        return [
            'url' => $url,
            'title' => $pageData['title'],
            'headings' => $pageData['headings'],
            'content_excerpt' => $pageData['content_excerpt'],
            'extracted' => $result['extracted'] ?? [],
            'flat_content' => $result['flat_content'] ?? '',
        ];
    }

    public function crawl(WebsiteCrawler $crawler, $progressCallback = null)
    {
        $startTime = time();
        $stats = [
            'pages_processed' => 0,
            'pages_failed' => 0,
            'start_time' => $startTime
        ];

        try {
            $urlsToCrawl = $this->getUrlsToCrawl($crawler);
            $totalUrls = count($urlsToCrawl);
            
            // Call progress callback with initial data
            if ($progressCallback) {
                $progressCallback([
                    'status' => 'started',
                    'total_pages' => $totalUrls,
                    'pages_processed' => 0,
                    'pages_failed' => 0,
                    'current_url' => '',
                    'progress_percent' => 0
                ]);
            }
            
            foreach ($urlsToCrawl as $index => $url) {
                // Check execution time limit
                if (time() - $startTime > $this->maxExecutionTime) {
                    Log::warning('Crawl timeout reached for crawler: ' . $crawler->id);
                    break;
                }

                if ($this->shouldCrawlUrl($url, $crawler)) {
                    // Update progress before processing
                    if ($progressCallback) {
                        $progressCallback([
                            'status' => 'processing',
                            'total_pages' => $totalUrls,
                            'pages_processed' => $stats['pages_processed'],
                            'pages_failed' => $stats['pages_failed'],
                            'current_url' => $url,
                            'progress_percent' => round(($index / $totalUrls) * 100)
                        ]);
                    }
                    
                    if ($this->crawlSinglePage($url, $crawler)) {
                        $stats['pages_processed']++;
                    } else {
                        $stats['pages_failed']++;
                    }
                    
                    // Update progress after processing
                    if ($progressCallback) {
                        $progressCallback([
                            'status' => 'processing',
                            'total_pages' => $totalUrls,
                            'pages_processed' => $stats['pages_processed'],
                            'pages_failed' => $stats['pages_failed'],
                            'current_url' => $url,
                            'progress_percent' => round((($index + 1) / $totalUrls) * 100)
                        ]);
                    }
                    
                    // Be respectful - add delay between requests
                    sleep(2);
                }
            }

            // Final progress update
            if ($progressCallback) {
                $progressCallback([
                    'status' => 'completed',
                    'total_pages' => $totalUrls,
                    'pages_processed' => $stats['pages_processed'],
                    'pages_failed' => $stats['pages_failed'],
                    'current_url' => '',
                    'progress_percent' => 100
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Crawl failed for crawler ' . $crawler->id . ': ' . $e->getMessage());
            
            if ($progressCallback) {
                $progressCallback([
                    'status' => 'error',
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Update crawler statistics
        $crawler->update([
            'last_crawled_at' => now(),
            'crawl_stats' => $stats
        ]);

        return $stats;
    }

    private function getUrlsToCrawl(WebsiteCrawler $crawler): array
    {
        if ($crawler->sitemap_url) {
            return $this->parseSitemap($crawler->sitemap_url);
        }
        
        if ($crawler->specific_pages) {
            return $crawler->specific_pages;
        }
        
        // For full crawl, start with homepage
        return [$crawler->website_url];
    }

    private function shouldCrawlUrl($url, WebsiteCrawler $crawler): bool
    {
        // Skip non-http(s) schemes or pseudo links
        if (preg_match('/^(tel:|mailto:|javascript:|#)/i', $url)) {
            return false;
        }

        // Skip if already visited
        if (in_array($url, $this->visitedUrls)) {
            return false;
        }

        // Skip non-HTML files (images, documents, etc.)
        $excludeExtensions = [
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg', // Images
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', // Documents
            'zip', 'rar', '7z', 'tar', 'gz', // Archives
            'mp3', 'mp4', 'avi', 'mov', 'wmv', 'flv', // Media
            'css', 'js', 'ico', 'xml', 'json', // Web assets
            'exe', 'dmg', 'pkg', 'deb', 'rpm' // Executables
        ];

        $urlPath = parse_url($url, PHP_URL_PATH);
        if ($urlPath) {
            $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));
            if (in_array($extension, $excludeExtensions)) {
                return false;
            }
        }

        // Skip URLs that look like file downloads or assets
        $skipPatterns = [
            '/download/', '/assets/', '/static/', '/media/', '/files/',
            '/images/', '/img/', '/gallery/', '/uploads/', '/wp-content/',
            '/admin/', '/login/', '/register/', '/dashboard/',
            '/api/', '/ajax/', '/rss/', '/feed/'
        ];

        foreach ($skipPatterns as $pattern) {
            if (stripos($url, $pattern) !== false) {
                return false;
            }
        }

        // Check include patterns
        if ($crawler->include_patterns) {
            $matches = false;
            foreach ($crawler->include_patterns as $pattern) {
                if (strpos($url, $pattern) !== false) {
                    $matches = true;
                    break;
                }
            }
            if (!$matches) {
                return false;
            }
        }

        // Check exclude patterns
        if ($crawler->exclude_patterns) {
            foreach ($crawler->exclude_patterns as $pattern) {
                if (strpos($url, $pattern) !== false) {
                    return false;
                }
            }
        }

        // Check url_filter_pattern (simple substring or glob match, e.g. /products/)
        if (!empty($crawler->url_filter_pattern)) {
            $pattern = $crawler->url_filter_pattern;
            // Support wildcard * patterns
            if (strpos($pattern, '*') !== false) {
                $regexPattern = '#' . str_replace(['\*', '/'], ['.*', '\/'], preg_quote($pattern, '#')) . '#i';
                if (!preg_match($regexPattern, $url)) {
                    return false;
                }
            } elseif (strpos($url, $pattern) === false) {
                return false;
            }
        }

        return true;
    }

    private function crawlSinglePage($url, WebsiteCrawler $crawler): bool
    {
        try {
            $this->visitedUrls[] = $url;
            
            $response = $this->httpClient->get($url);
            
            // Check content type
            $contentType = $response->getHeader('Content-Type')[0] ?? '';
            if (!str_contains(strtolower($contentType), 'text/html')) {
                Log::info('Skipping non-HTML content: ' . $url . ' (Content-Type: ' . $contentType . ')');
                return false;
            }
            
            $content = $response->getBody()->getContents();
            
            // Validate that we have actual HTML content
            if (empty($content) || !str_contains($content, '<html') && !str_contains($content, '<body')) {
                Log::info('Skipping invalid HTML content: ' . $url);
                return false;
            }

            // --- Noise removal: strip configured selectors from HTML ---
            $noiseSelectors = $crawler->noise_selectors_with_defaults;
            $content = $this->stripNoiseSelectors($content, $noiseSelectors);
            
            // Extract content using simple HTML DOM (title, meta, headings, plain text)
            $extractedData = $this->extractContent($content, $url);
            
            if (!empty($extractedData['content'])) {
                $attributeList = $crawler->attribute_list;
                $useStructuredExtraction = !empty($attributeList) 
                    && ($crawler->extraction_method ?? 'llm') === 'llm';

                if ($useStructuredExtraction) {
                    // --- LLM-based structured attribute extraction ---
                    $result = $this->extractAttributesViaLlm(
                        $extractedData['content'],
                        $attributeList,
                        $crawler->page_type ?? 'product',
                        $url,
                        $crawler->extraction_prompt_override
                    );

                    $title = $result['extracted']['name']
                        ?? $result['extracted']['title']
                        ?? ($extractedData['title'] ?: parse_url($url, PHP_URL_PATH));

                    // Auto-inject the page URL for URL-type attributes that LLM can't know
                    $urlAttributes = ['product_url', 'page_url', 'url', 'link', 'listing_url'];
                    foreach ($urlAttributes as $urlAttr) {
                        if (in_array($urlAttr, $attributeList) && empty($result['extracted'][$urlAttr])) {
                            $result['extracted'][$urlAttr] = $url;
                            // Rebuild flat_content with injected URL
                            $flat = $result['flat_content'] ?? '';
                            $result['flat_content'] = $flat . "\n{$urlAttr}: {$url}";
                        }
                    }

                    $contentToStore = !empty($result['flat_content'])
                        ? $result['flat_content']
                        : $extractedData['content'];

                    $this->storeCrawledPage(
                        $crawler,
                        $title,
                        $contentToStore,
                        $crawler->qdrant_data_type ?? 'product',
                        array_merge($result['extracted'] ?? [], [
                            'url' => $url,
                            'source' => 'website_crawler',
                            'crawler_id' => $crawler->id,
                            'page_type' => $crawler->page_type ?? 'product',
                            'meta_description' => $extractedData['meta_description'] ?? '',
                        ])
                    );
                } else {
                    // --- Plain text extraction (no LLM, fast fallback) ---
                    $this->storeCrawledPage(
                        $crawler,
                        $extractedData['title'] ?: parse_url($url, PHP_URL_PATH),
                        $extractedData['content'],
                        $crawler->qdrant_data_type ?? 'webpage',
                        [
                            'url' => $url,
                            'source' => 'website_crawler',
                            'crawler_id' => $crawler->id,
                            'meta_description' => $extractedData['meta_description'] ?? '',
                            'headings' => $extractedData['headings'] ?? []
                        ]
                    );
                }

                Log::info('Successfully crawled: ' . $url);
                return true;
            }
            
        } catch (GuzzleException $e) {
            Log::error('Failed to crawl ' . $url . ': ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('Error processing ' . $url . ': ' . $e->getMessage());
        }
        
        return false;
    }

    /**
     * Save crawled page data to organization_data table and sync to Qdrant
     * using the organization slug as the collection name (consistent with rest of platform).
     */
    private function storeCrawledPage(
        WebsiteCrawler $crawler,
        string $title,
        string $content,
        string $dataType,
        array $metadata = []
    ): void {
        $organization = $crawler->organization ?? Organization::find($crawler->organization_id);

        if (!$organization) {
            Log::warning('storeCrawledPage: organization not found for crawler ' . $crawler->id);
            return;
        }

        // 1. Persist to organization_data for DB management & history
        $orgData = OrganizationData::create([
            'organization_id' => $organization->id,
            'type'            => $dataType,
            'name'            => $title,
            'description'     => $metadata['meta_description'] ?? '',
            'content'         => $content,
            'metadata'        => $metadata,
            'is_synced'       => false,
        ]);

        // 2. Sync to Qdrant under the org slug collection (e.g. "indian-art-zone")
        $item = [
            'id'      => $orgData->id,
            'title'   => $title,
            'content' => $content,
            'type'    => $dataType,
        ];
        // Merge key metadata fields into the Qdrant payload
        foreach (['url', 'product_url', 'price', 'artist', 'medium', 'style',
                  'size', 'color', 'availability', 'category_name', 'source', 'crawler_id'] as $field) {
            if (!empty($metadata[$field])) {
                $item[$field] = $metadata[$field];
            }
        }

        $result = $this->aiAgentService->storeDataToQdrant($organization->slug, $dataType, [$item]);

        // 3. Mark as synced if Qdrant accepted it
        if (!empty($result['successful_stores'])) {
            $orgData->update(['is_synced' => true, 'last_synced_at' => now()]);
        } else {
            Log::warning('storeCrawledPage: Qdrant sync failed', [
                'org'  => $organization->slug,
                'type' => $dataType,
                'title' => $title,
            ]);
        }
    }

    /**
     * Strip HTML noise selectors (nav, footer, ads, etc.) from raw HTML
     * before processing or sending to the LLM.
     */
    private function stripNoiseSelectors(string $html, array $selectors): string
    {
        if (empty($selectors)) {
            return $html;
        }

        // Use DOMDocument for reliable node removal
        try {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
            $xpath = new \DOMXPath($dom);

            foreach ($selectors as $selector) {
                $selector = trim($selector);
                if (empty($selector)) continue;

                // Convert CSS-ish selectors to XPath
                if ($selector[0] === '#') {
                    // #id
                    $id = substr($selector, 1);
                    $xpathQuery = "//*[@id='{$id}']";
                } elseif ($selector[0] === '.') {
                    // .class
                    $class = substr($selector, 1);
                    $xpathQuery = "//*[contains(concat(' ',normalize-space(@class),' '),' {$class} ')]";
                } elseif (strpos($selector, '.') !== false && strpos($selector, '.') !== 0) {
                    // tag.class
                    [$tag, $cls] = explode('.', $selector, 2);
                    $xpathQuery = "//{$tag}[contains(concat(' ',normalize-space(@class),' '),' {$cls} ')]";
                } else {
                    // plain tag
                    $xpathQuery = "//{$selector}";
                }

                try {
                    $nodes = $xpath->query($xpathQuery);
                    if ($nodes) {
                        foreach ($nodes as $node) {
                            $node->parentNode?->removeChild($node);
                        }
                    }
                } catch (\Exception $e) {
                    Log::debug("Noise selector '{$selector}' failed: " . $e->getMessage());
                }
            }

            return $dom->saveHTML();
        } catch (\Exception $e) {
            Log::debug('stripNoiseSelectors DOMDocument failed, returning original HTML: ' . $e->getMessage());
            return $html;
        }
    }

    private function fetchSamplePageData(string $url, array $noiseSelectors = []): array
    {
        try {
            $response = $this->httpClient->get($url);
            $contentType = $response->getHeader('Content-Type')[0] ?? '';

            if (!str_contains(strtolower($contentType), 'text/html')) {
                throw new \RuntimeException('The sample URL does not return HTML content.');
            }

            $html = $response->getBody()->getContents();
            if (empty($html)) {
                throw new \RuntimeException('The sample page returned empty content.');
            }

            $cleanHtml = $this->stripNoiseSelectors($html, $noiseSelectors);
            $extracted = $this->extractContent($cleanHtml, $url);
            $content = trim((string) ($extracted['content'] ?? ''));

            if ($content === '') {
                throw new \RuntimeException('Could not extract readable text from the sample page.');
            }

            return [
                'title' => $extracted['title'] ?: parse_url($url, PHP_URL_PATH),
                'meta_description' => $extracted['meta_description'] ?? '',
                'headings' => array_values($extracted['headings'] ?? []),
                'content' => $content,
                'content_excerpt' => mb_substr($content, 0, 1800),
            ];
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Failed to fetch sample page: ' . $e->getMessage(), 0, $e);
        }
    }

    private function suggestTemplateForPage(array $pageData, array $options = []): array
    {
        $fastApiUrl = config('services.ai_agent.url', 'http://localhost:8111');

        try {
            $response = $this->httpClient->post($fastApiUrl . '/crawl/suggest-template', [
                'json' => [
                    'title' => $pageData['title'] ?? '',
                    'meta_description' => $pageData['meta_description'] ?? '',
                    'headings' => $pageData['headings'] ?? [],
                    'text' => $pageData['content'] ?? '',
                    'page_url' => $options['page_url'] ?? null,
                    'current_page_type' => $options['page_type'] ?? null,
                    'current_attributes' => $options['attributes'] ?? [],
                    'prompt_override' => $options['prompt_override'] ?? null,
                ],
                'timeout' => 60,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            if (!is_array($result)) {
                throw new \RuntimeException('Template suggestion returned an invalid response.');
            }

            return [
                'page_type' => $result['page_type'] ?? ($options['page_type'] ?: 'general'),
                'qdrant_data_type' => $result['qdrant_data_type'] ?? $this->mapPageTypeToDataType($result['page_type'] ?? ($options['page_type'] ?: 'general')),
                'attribute_schema' => array_values(array_filter($result['attribute_schema'] ?? [])),
                'url_filter_pattern' => $result['url_filter_pattern'] ?: $this->guessUrlFilterPattern($options['website_url'] ?? null, $options['page_url'] ?? null),
                'summary' => $result['summary'] ?? 'Template suggestion generated from the sample page.',
            ];
        } catch (\Exception $e) {
            Log::warning('Template suggestion fallback used: ' . $e->getMessage());
            return $this->fallbackTemplateSuggestion($options['page_url'] ?? null, $pageData, $options);
        }
    }

    private function fallbackTemplateSuggestion(?string $pageUrl, array $pageData, array $options = []): array
    {
        $pageType = $options['page_type'] ?: $this->guessPageType($pageUrl, $pageData['title'] ?? '', $pageData['headings'] ?? []);
        $attributeSchema = $options['attributes'] ?: $this->defaultAttributesForPageType($pageType);

        return [
            'page_type' => $pageType,
            'qdrant_data_type' => $this->mapPageTypeToDataType($pageType),
            'attribute_schema' => $attributeSchema,
            'url_filter_pattern' => $this->guessUrlFilterPattern($options['website_url'] ?? null, $pageUrl),
            'summary' => 'Fallback template generated from the URL structure and extracted headings.',
        ];
    }

    private function buildBaseUrl(string $url): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME) ?: 'https';
        $host = parse_url($url, PHP_URL_HOST) ?: '';

        return $host ? $scheme . '://' . $host : $url;
    }

    private function guessPageType(?string $url, string $title, array $headings): string
    {
        $haystack = strtolower(trim(($url ?? '') . ' ' . $title . ' ' . implode(' ', $headings)));

        return match (true) {
            str_contains($haystack, 'faq') || str_contains($haystack, 'question') => 'faq',
            str_contains($haystack, 'test') || str_contains($haystack, 'diagnostic') || str_contains($haystack, 'scan') => 'medical-test',
            str_contains($haystack, 'doctor') || str_contains($haystack, 'consultant') || str_contains($haystack, 'specialist') => 'doctor',
            str_contains($haystack, 'service') => 'service',
            str_contains($haystack, 'blog') || str_contains($haystack, 'article') || str_contains($haystack, 'news') => 'article',
            default => 'product',
        };
    }

    private function defaultAttributesForPageType(string $pageType): array
    {
        return match ($pageType) {
            'service' => ['name', 'summary', 'pricing', 'duration', 'eligibility', 'requirements'],
            'doctor' => ['name', 'specialty', 'qualification', 'experience', 'availability', 'consultation_fee'],
            'medical-test' => ['test_name', 'price', 'preparation', 'report_time', 'sample_type', 'timing'],
            'faq' => ['question', 'answer', 'category'],
            'article' => ['title', 'summary', 'author', 'published_date', 'key_topics'],
            default => ['name', 'price', 'category', 'availability', 'description'],
        };
    }

    private function mapPageTypeToDataType(string $pageType): string
    {
        return match ($pageType) {
            'service', 'doctor', 'medical-test' => 'service',
            'faq' => 'faq',
            'article' => 'info',
            default => 'product',
        };
    }

    private function guessUrlFilterPattern(?string $websiteUrl, ?string $sampleUrl): ?string
    {
        if (!$sampleUrl) {
            return null;
        }

        $samplePath = trim((string) parse_url($sampleUrl, PHP_URL_PATH), '/');
        if ($samplePath === '') {
            return null;
        }

        $segments = explode('/', $samplePath);
        $firstSegment = $segments[0] ?? '';

        return $firstSegment !== '' ? '/' . $firstSegment . '/' : null;
    }

    /**
     * Call FastAPI /crawl/extract-attributes to extract structured attributes
     * from cleaned page text using the configured LLM.
     */
    private function extractAttributesViaLlm(
        string $pageText,
        array $attributes,
        string $pageType,
        string $pageUrl,
        ?string $promptOverride = null
    ): array {
        $fastApiUrl = config('services.ai_agent.url', 'http://localhost:8111');

        try {
            $payload = [
                'text'       => $pageText,
                'attributes' => $attributes,
                'page_type'  => $pageType,
                'page_url'   => $pageUrl,
            ];

            if ($promptOverride) {
                $payload['prompt_override'] = $promptOverride;
            }

            $response = $this->httpClient->post($fastApiUrl . '/crawl/extract-attributes', [
                'json'    => $payload,
                'timeout' => 60,
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            return $result ?? ['extracted' => [], 'flat_content' => ''];

        } catch (\Exception $e) {
            Log::error('LLM attribute extraction failed for ' . $pageUrl . ': ' . $e->getMessage());
            return ['extracted' => [], 'flat_content' => $pageText];
        }
    }


    private function extractContent($html, $url): array
    {
        // Validate input
        if (empty($html) || !is_string($html)) {
            Log::warning('Empty or invalid HTML content for URL: ' . $url);
            return [
                'title' => '',
                'content' => '',
                'meta_description' => '',
                'headings' => []
            ];
        }

        // Check if content looks like binary data (images, PDFs, etc.)
        if (!mb_check_encoding($html, 'UTF-8') || 
            preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $html) ||
            preg_match('/\xFF\xD8\xFF|\x89PNG|\x47\x49\x46|\x25\x50\x44\x46/', $html) ||
            strlen($html) < 50) {
            Log::info('Skipping non-HTML content for URL: ' . $url);
            return [
                'title' => '',
                'content' => '',
                'meta_description' => '',
                'headings' => []
            ];
        }
        
        // Check for basic HTML structure
        if (!preg_match('/<\s*html|<\s*body|<\s*head|<\s*div|<\s*p|<\s*title/i', $html)) {
            Log::info('Content does not appear to be HTML for URL: ' . $url);
            return [
                'title' => '',
                'content' => '',
                'meta_description' => '',
                'headings' => []
            ];
        }

        // If content contains problematic characters, use regex-only extraction
        if (preg_match('/[\x00-\x1F\x7F-\xFF]/', $html)) {
            Log::info('Using regex-only extraction for problematic content: ' . $url);
            return $this->extractContentWithRegex($html);
        }

        try {
            // Sanitize HTML before parsing
            $sanitizedHtml = $this->sanitizeHtmlForParsing($html);
            
            // Wrap DOM parsing in error handler to catch all issues
            $dom = $this->safeHtmlParse($sanitizedHtml);
            
            $data = [
                'title' => '',
                'content' => '',
                'meta_description' => '',
                'headings' => []
            ];

            if (!$dom) {
                // Fallback to simple regex extraction if DOM parsing fails
                Log::info('DOM parsing failed, using regex fallback for: ' . $url);
                return $this->extractContentWithRegex($html);
            }

            // Extract title
            $titleElement = null;
            try {
                $titleElement = $dom->find('title', 0);
                if ($titleElement) {
                    $data['title'] = trim($titleElement->plaintext);
                }
            } catch (Throwable $e) {
                Log::info('Error extracting title: ' . $e->getMessage());
            }

            // Extract meta description
            try {
                $metaDesc = $dom->find('meta[name=description]', 0);
                if ($metaDesc) {
                    $data['meta_description'] = trim($metaDesc->content);
                }
            } catch (Throwable $e) {
                Log::info('Error extracting meta description: ' . $e->getMessage());
            }

            // Extract headings
            $headings = [];
            try {
                foreach ($dom->find('h1, h2, h3, h4, h5, h6') as $heading) {
                    $headings[] = trim($heading->plaintext);
                }
                $data['headings'] = array_filter($headings);
            } catch (Throwable $e) {
                Log::info('Error extracting headings: ' . $e->getMessage());
            }

            // Extract main content
            $contentSelectors = [
                'main',
                'article',
                '.content',
                '.main-content',
                '#content',
                '#main',
                '.post-content',
                '.entry-content'
            ];

            $content = '';
            try {
                foreach ($contentSelectors as $selector) {
                    try {
                        $elements = $dom->find($selector);
                        if (!empty($elements)) {
                            foreach ($elements as $element) {
                                $content .= ' ' . $element->plaintext;
                            }
                            break;
                        }
                    } catch (Throwable $e) {
                        Log::info("Error with selector '$selector': " . $e->getMessage());
                        continue;
                    }
                }

                // If no main content found, extract from body
                if (empty($content)) {
                    try {
                        $body = $dom->find('body', 0);
                        if ($body) {
                            // Remove script and style tags
                            foreach ($body->find('script, style, nav, header, footer, aside') as $element) {
                                $element->outertext = '';
                            }
                            $content = $body->plaintext;
                        }
                    } catch (Throwable $e) {
                        Log::info('Error extracting body content: ' . $e->getMessage());
                    }
                }

                // Clean up content
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Limit content length to prevent oversized chunks
                // Truncation disabled: extract and embed full content
                // If you experience timeouts or errors, you can re-enable below:
                // if (strlen($content) > 5000) {
                //     $content = substr($content, 0, 5000) . '...';
                // }

                $data['content'] = $content;
            } catch (Throwable $e) {
                Log::info('Error processing content: ' . $e->getMessage());
            }

            // Clean up DOM object
            if ($dom) {
                $dom->clear();
                unset($dom);
            }

            return $data;
            
        } catch (Exception $e) {
            Log::error('Error extracting content from URL: ' . $url . ' - ' . $e->getMessage());
            // Return basic data structure on error
            return [
                'title' => '',
                'content' => '',
                'meta_description' => '',
                'headings' => []
            ];
        }
    }

    /**
     * Sanitize HTML content before parsing to prevent regex compilation errors
     */
    private function sanitizeHtmlForParsing($html)
    {
        // Remove null bytes and other problematic characters
        $html = str_replace(["\0", "\x0B"], '', $html);
        
        // Convert to UTF-8 if needed
        if (!mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'auto');
        }
        
        // Remove any remaining control characters except basic whitespace
        $html = preg_replace('/[\x00-\x08\x0E-\x1F\x7F]/', '', $html);
        
        return $html;
    }

    /**
     * Safely parse HTML with comprehensive error handling
     */
    private function safeHtmlParse($html)
    {
        try {
            // Temporarily disable Laravel's error handler and PHP error reporting
            $originalHandler = set_exception_handler(null);
            restore_exception_handler();
            
            $previousLevel = error_reporting(0);
            
            // Set a custom error handler that silently ignores errors
            set_error_handler(function($severity, $message, $file, $line) {
                // Return true to suppress the error
                return true;
            });
            
            $dom = null;
            
            // Wrap in additional try-catch for extra safety
            try {
                $dom = str_get_html($html);
            } catch (Throwable $e) {
                $dom = false;
            }
            
            // Restore error handling
            restore_error_handler();
            error_reporting($previousLevel);
            
            // Re-register Laravel's exception handler if it existed
            if ($originalHandler) {
                set_exception_handler($originalHandler);
            }
            
            return $dom;
            
        } catch (Throwable $e) {
            // Final catch-all for any remaining issues
            Log::info('HTML parsing failed completely: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback content extraction using regex
     */
    private function extractContentWithRegex($html): array
    {
        $data = [
            'title' => '',
            'content' => '',
            'meta_description' => '',
            'headings' => []
        ];

        // Validate that the content is likely HTML
        if (!is_string($html) || strlen($html) < 10 || !str_contains($html, '<')) {
            return $data;
        }

        try {
            // Extract title
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
                $data['title'] = trim(strip_tags($matches[1]));
            }
        } catch (Exception $e) {
            Log::error('Error extracting title: ' . $e->getMessage());
        }

        try {
            // Extract meta description
            if (preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
                $data['meta_description'] = trim($matches[1]);
            }
        } catch (Exception $e) {
            Log::error('Error extracting meta description: ' . $e->getMessage());
        }

        try {
            // Extract headings
            if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $html, $matches)) {
                $data['headings'] = array_map(function($heading) {
                    return trim(strip_tags($heading));
                }, $matches[1]);
            }
        } catch (Exception $e) {
            Log::error('Error extracting headings: ' . $e->getMessage());
        }

        try {
            // Extract content from body
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
                $content = $matches[1];
                
                // Remove script and style tags
                $content = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $content);
                $content = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $content);
                $content = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $content);
                $content = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $content);
                $content = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $content);
                $content = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $content);
                
                $content = strip_tags($content);
                $content = preg_replace('/\s+/', ' ', $content);
                $content = trim($content);
                
                // Limit content length
                // Truncation disabled: extract and embed full content
                // If you experience timeouts or errors, you can re-enable below:
                // if (strlen($content) > 5000) {
                //     $content = substr($content, 0, 5000) . '...';
                // }
                
                $data['content'] = $content;
            }
        } catch (Exception $e) {
            Log::error('Error extracting body content: ' . $e->getMessage());
        }

        return $data;
    }

    private function extractTitle($html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return trim(strip_tags($matches[1]));
        }
        return 'Unknown Title';
    }
}
