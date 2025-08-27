<?php

namespace App\Services;

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

    public function testUrl($url)
    {
        try {
            $response = $this->httpClient->get($url);
            
            return [
                'success' => true,
                'status_code' => $response->getStatusCode(),
                'title' => $this->extractTitle($response->getBody()->getContents()),
                'content_length' => strlen($response->getBody()->getContents())
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
            
            // Extract content using simple HTML DOM
            $extractedData = $this->extractContent($content, $url);
            
            if (!empty($extractedData['content'])) {
                // Store in vector database
                $this->aiAgentService->storeData(
                    $crawler->organization_id,
                    'webpage',
                    $extractedData['title'] ?: parse_url($url, PHP_URL_PATH),
                    $extractedData['content'],
                    [
                        'url' => $url,
                        'source' => 'website_crawler',
                        'crawler_id' => $crawler->id,
                        'meta_description' => $extractedData['meta_description'] ?? '',
                        'headings' => $extractedData['headings'] ?? []
                    ]
                );
                
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
