<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use League\CommonMark\CommonMarkConverter;
use DOMDocument;

class OrganizationFaq extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'question',
        'answer', 
        'answer_markdown',
        'follow_up',
        'category',
        'keywords',
        'sort_order',
        'is_active',
        'is_starter_prompt',
        'starter_sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_starter_prompt' => 'boolean',
        'sort_order' => 'integer',
        'starter_sort_order' => 'integer',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Convert Markdown to sanitized HTML
     */
    public function convertMarkdownToHtml($markdown)
    {
        if (empty($markdown)) {
            return '';
        }

        // Convert Markdown to HTML
        $converter = new CommonMarkConverter([
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
        
        $html = $converter->convert($markdown)->getContent();
        
        // Sanitize the HTML
        return $this->sanitizeHtml($html);
    }

    /**
     * Sanitize HTML content
     * Make public so other layers (Livewire) can sanitize user-provided HTML before saving.
     */
    public function sanitizeHtml($html)
    {
        if (empty($html)) {
            return '';
        }

        // Prepare HTML and suppress libxml warnings for malformed inputs (e.g., stray &)
        $prepared = $this->prepareHtmlForDom((string) $html);
        $dom = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $prepared, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        // Define allowed tags and their allowed attributes
        $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'a', 'img', 'code', 'pre', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
        $allowedAttributes = [
            'a' => ['href', 'target', 'rel'],
            'img' => ['src', 'alt', 'title', 'style']
        ];

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//*');

        foreach ($nodes as $node) {
            if (!in_array($node->tagName, $allowedTags)) {
                // Remove disallowed tags but keep content
                $parent = $node->parentNode;
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }

            // Remove disallowed attributes
            $attributes = [];
            foreach ($node->attributes as $attr) {
                $attributes[] = $attr->name;
            }

            foreach ($attributes as $attrName) {
                if (!isset($allowedAttributes[$node->tagName]) || !in_array($attrName, $allowedAttributes[$node->tagName])) {
                    $node->removeAttribute($attrName);
                }
            }

            // Ensure links open in new tab and have security attributes
            if ($node->tagName === 'a') {
                $href = $node->getAttribute('href');
                if ($href && (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0)) {
                    $node->setAttribute('target', '_blank');
                    $node->setAttribute('rel', 'nofollow noopener noreferrer');
                }
            }

            // Strip data: URIs from <img src> — only allow http/https URLs
            if ($node->tagName === 'img') {
                $src = $node->getAttribute('src');
                if ($src !== '' && !preg_match('#^https?://#i', $src)) {
                    $node->removeAttribute('src');
                }
                // Sanitize style — strip dangerous CSS (expression, javascript:, url())
                $style = $node->getAttribute('style');
                if ($style !== '') {
                    $style = preg_replace('/expression\s*\(.*?\)/i', '', $style);
                    $style = preg_replace('/javascript\s*:/i', '', $style);
                    $style = preg_replace('/url\s*\(/i', '', $style);
                    $node->setAttribute('style', trim($style));
                }
            }
        }

        return $dom->saveHTML();
    }

    /**
     * Get plain text version for embeddings
     */
    public function getPlainTextAttribute()
    {
        // Prefer sanitized HTML answer if present
        $html = trim((string) $this->answer);
        if ($html !== '') {
            $safeHtml = $this->sanitizeHtml($html);
            return html_entity_decode(strip_tags($safeHtml), ENT_QUOTES, 'UTF-8');
        }

        // Fallback to markdown if legacy data exists
        $markdown = trim((string) $this->answer_markdown);
        if ($markdown !== '') {
            $converted = $this->convertMarkdownToHtml($markdown); // already sanitized
            return html_entity_decode(strip_tags($converted), ENT_QUOTES, 'UTF-8');
        }

        // No indexable content
        return '';
    }

    /**
     * Convert sanitized HTML to plain text but preserve anchor URLs by appending them.
     * Example: <a href="https://example.com">Example</a> -> "Example (https://example.com)"
     */
    private function htmlToPlainTextWithLinks(string $html): string
    {
        if (trim($html) === '') return '';

        $dom = new DOMDocument();
        $prepared = $this->prepareHtmlForDom($html);
        $prev = libxml_use_internal_errors(true);
        // Load already-sanitized HTML to avoid unsafe tags
        $dom->loadHTML('<?xml encoding="UTF-8">' . $prepared, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        /** @var \DOMElement $a */
        foreach ($xpath->query('//a') as $a) {
            $href = $a->getAttribute('href');
            $text = trim($a->textContent);
            $replacement = $href ? trim(($text !== '' ? $text . ' (' . $href . ')' : $href)) : $text;
            // Pad with spaces to avoid words sticking together when multiple anchors are adjacent
            $textNode = $dom->createTextNode(' ' . $replacement . ' ');
            $a->parentNode->replaceChild($textNode, $a);
        }
        // Serialize and convert basic block boundaries to newlines
        $serialized = $dom->saveHTML();
        $withBreaks = preg_replace([
            '#<br\s*/?>#i',
            '#</p\s*>#i',
            '#</li\s*>#i',
            '#</h[1-6]\s*>#i',
            '#</div\s*>#i'
        ], "\n", $serialized);
        $plain = strip_tags($withBreaks);
        // Collapse excessive whitespace while preserving single newlines
        $plain = preg_replace("/\r/", '', $plain);
        $plain = preg_replace("/\n\s*\n+/", "\n\n", $plain);
        $plain = preg_replace("/[\t ]+/", ' ', $plain);
        return html_entity_decode(trim($plain), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Extract all anchor hrefs from sanitized HTML
     */
    private function extractLinksFromHtml(string $html): array
    {
        $links = [];
        if (trim($html) === '') return $links;
        $dom = new DOMDocument();
        $prepared = $this->prepareHtmlForDom($html);
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $prepared, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $xpath = new \DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') as $a) {
            $href = trim($a->getAttribute('href'));
            if ($href !== '' && (strpos($href, 'http://') === 0 || strpos($href, 'https://') === 0)) {
                $links[] = $href;
            }
        }
        // Unique preserve order
        return array_values(array_unique($links));
    }

    /**
     * Normalize HTML string for DOM parsing by escaping stray ampersands and ensuring UTF-8 handling.
     */
    private function prepareHtmlForDom(string $html): string
    {
        $html = trim($html);
        if ($html === '') return '';
        // Escape stray ampersands that are not valid entities (common cause of htmlParseEntityRef)
        $html = preg_replace('/&(?!#\d+;|#x[0-9a-fA-F]+;|[a-zA-Z][a-zA-Z0-9]+;)/', '&amp;', $html);
        return $html;
    }

    /**
     * Accessor: plain text that preserves URLs from anchors.
     */
    public function getPlainTextWithLinksAttribute(): string
    {
        $html = trim((string) $this->answer);
        if ($html !== '') {
            $safeHtml = $this->sanitizeHtml($html);
            return $this->htmlToPlainTextWithLinks($safeHtml);
        }

        $markdown = trim((string) $this->answer_markdown);
        if ($markdown !== '') {
            $converted = $this->convertMarkdownToHtml($markdown); // sanitized
            return $this->htmlToPlainTextWithLinks($converted);
        }
        return '';
    }

    /**
     * Accessor: list of URLs present in the answer content.
     */
    public function getLinksAttribute(): array
    {
        $html = trim((string) $this->answer);
        if ($html !== '') {
            return $this->extractLinksFromHtml($this->sanitizeHtml($html));
        }
        $markdown = trim((string) $this->answer_markdown);
        if ($markdown !== '') {
            $converted = $this->convertMarkdownToHtml($markdown);
            return $this->extractLinksFromHtml($converted);
        }
        return [];
    }

    /**
     * Accessor for rendered HTML from markdown
     */
    public function getAnswerHtmlAttribute()
    {
        return $this->sanitizeHtml($this->answer);
    }
}
