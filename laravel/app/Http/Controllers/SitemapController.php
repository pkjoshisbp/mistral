<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index()
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        
        // Homepage
        $sitemap .= $this->addUrl(route('home'), now()->toDateString(), 'daily', '1.0');
        
        // Static pages
        $sitemap .= $this->addUrl(route('about'), now()->toDateString(), 'monthly', '0.8');
        $sitemap .= $this->addUrl(route('features'), now()->toDateString(), 'monthly', '0.8');
        $sitemap .= $this->addUrl(route('contact'), now()->toDateString(), 'monthly', '0.7');
        $sitemap .= $this->addUrl(route('blog.index'), now()->toDateString(), 'daily', '0.9');
        
        // Legal pages
        $sitemap .= $this->addUrl(route('terms'), now()->toDateString(), 'yearly', '0.3');
        $sitemap .= $this->addUrl(route('privacy'), now()->toDateString(), 'yearly', '0.3');
        $sitemap .= $this->addUrl(route('refund-policy'), now()->toDateString(), 'yearly', '0.3');
        
        // Blog posts
        $blogs = Blog::published()->orderBy('published_at', 'desc')->get();
        foreach ($blogs as $blog) {
            $sitemap .= $this->addUrl(
                route('blog.show', $blog->slug),
                $blog->updated_at->toDateString(),
                'weekly',
                '0.8'
            );
        }
        
        $sitemap .= '</urlset>';
        
        return response($sitemap)
            ->header('Content-Type', 'application/xml');
    }
    
    private function addUrl($loc, $lastmod, $changefreq, $priority)
    {
        return sprintf(
            "  <url>\n    <loc>%s</loc>\n    <lastmod>%s</lastmod>\n    <changefreq>%s</changefreq>\n    <priority>%s</priority>\n  </url>\n",
            htmlspecialchars($loc),
            $lastmod,
            $changefreq,
            $priority
        );
    }
}
