<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AffiliateLink;
use App\Models\AffiliateVisit;

class AffiliateController extends Controller
{
    /**
     * Handle affiliate link redirect
     */
    public function redirect(Request $request, $code)
    {
        // Find the affiliate link by tracking code
        $link = AffiliateLink::where('tracking_code', $code)
            ->where('is_active', true)
            ->first();

        if (!$link) {
            // If link not found, redirect to homepage
            return redirect('/');
        }

        // Track the visit (this will also be handled by middleware, but good to have backup)
        $this->trackVisit($request, $link);

        // Redirect to the original URL
        return redirect($link->original_url);
    }

    /**
     * Track affiliate visit
     */
    private function trackVisit(Request $request, AffiliateLink $link)
    {
        // Create visitor fingerprint
        $fingerprint = md5(
            $request->ip() . 
            $request->header('User-Agent', '') . 
            $request->header('Accept-Language', '')
        );

        // Check if this visitor has already been tracked for this link recently
        $existingVisit = AffiliateVisit::where('affiliate_id', $link->affiliate_id)
            ->where('link_id', $link->id)
            ->where('visitor_fingerprint', $fingerprint)
            ->where('visited_at', '>=', now()->subHours(1)) // Within last hour
            ->first();

        if (!$existingVisit) {
            // Create new visit record
            AffiliateVisit::create([
                'affiliate_id' => $link->affiliate_id,
                'link_id' => $link->id,
                'visitor_ip' => $request->ip(),
                'visitor_fingerprint' => $fingerprint,
                'user_agent' => $request->header('User-Agent'),
                'referrer' => $request->header('Referer'),
                'visited_at' => now(),
            ]);
        }
    }
}
