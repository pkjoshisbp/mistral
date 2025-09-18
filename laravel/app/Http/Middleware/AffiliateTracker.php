<?php

namespace App\Http\Middleware;

use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\AffiliateVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AffiliateTracker
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for affiliate reference in URL
        $affiliateRef = $request->get('ref');
        
        if ($affiliateRef) {
            $this->trackAffiliateVisit($request, $affiliateRef);
        }
        
        // Always check for existing affiliate cookie
        $this->maintainAffiliateSession($request);
        
        $response = $next($request);
        
        return $response;
    }

    protected function trackAffiliateVisit(Request $request, string $affiliateRef)
    {
        try {
            // Find affiliate link by reference code
            $affiliateLink = AffiliateLink::where('link_code', $affiliateRef)
                ->where('is_active', true)
                ->with('affiliate')
                ->first();

            if (!$affiliateLink || !$affiliateLink->affiliate->isActive()) {
                return;
            }

            // Get or create visitor ID
            $visitorId = $this->getOrCreateVisitorId($request);
            
            // Prepare visit data
            $visitData = [
                'visitor_id' => $visitorId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent() ?? 'Unknown',
                'referrer' => $request->header('referer'),
                'landing_page' => $request->fullUrl()
            ];

            // Create or update visit record
            $visit = AffiliateVisit::createOrUpdateVisit(
                $affiliateLink->affiliate_id,
                $affiliateLink->id,
                $visitData
            );

            // Increment click counter
            $affiliateLink->incrementClick();

            // Set affiliate tracking cookie (15 days)
            Cookie::queue(
                'affiliate_ref', 
                $affiliateRef, 
                60 * 24 * 15, // 15 days
                '/', // path
                null, // domain
                true, // secure
                true // httpOnly
            );
            
            Cookie::queue(
                'affiliate_visitor', 
                $visitorId, 
                60 * 24 * 15, // 15 days
                '/', // path
                null, // domain
                true, // secure
                true // httpOnly
            );

            Log::info('Affiliate visit tracked', [
                'affiliate_code' => $affiliateLink->affiliate->affiliate_code,
                'link_code' => $affiliateRef,
                'visitor_id' => $visitorId,
                'ip' => $request->ip()
            ]);

        } catch (\Exception $e) {
            Log::error('Affiliate tracking error', [
                'error' => $e->getMessage(),
                'ref' => $affiliateRef,
                'url' => $request->fullUrl()
            ]);
        }
    }

    protected function maintainAffiliateSession(Request $request)
    {
        // Check if we have an existing affiliate session
        $affiliateRef = Cookie::get('affiliate_ref');
        $visitorId = Cookie::get('affiliate_visitor');

        if ($affiliateRef && $visitorId) {
            // Find the visit and update last activity if still valid
            $visit = AffiliateVisit::where('visitor_id', $visitorId)
                ->whereHas('affiliateLink', function ($query) use ($affiliateRef) {
                    $query->where('link_code', $affiliateRef);
                })
                ->where('expires_at', '>', now())
                ->first();

            if ($visit && !$visit->converted) {
                $visit->update(['last_visit_at' => now()]);
            }
        }
    }

    protected function getOrCreateVisitorId(Request $request): string
    {
        // Check if visitor already has an ID
        $existingVisitorId = Cookie::get('affiliate_visitor');
        
        if ($existingVisitorId) {
            return $existingVisitorId;
        }

        // Create new visitor ID based on IP and user agent
        $fingerprint = md5($request->ip() . $request->userAgent());
        return 'visitor_' . $fingerprint . '_' . time();
    }

    /**
     * Check if current request is from a tracked affiliate visitor
     */
    public static function getActiveAffiliateVisit(Request $request): ?AffiliateVisit
    {
        $visitorId = Cookie::get('affiliate_visitor');
        
        if (!$visitorId) {
            return null;
        }

        return AffiliateVisit::findValidVisit($visitorId);
    }

    /**
     * Mark a conversion for the current visitor
     */
    public static function trackConversion(Request $request, string $type, float $value = 0, $relatedModel = null): ?AffiliateVisit
    {
        $visit = static::getActiveAffiliateVisit($request);
        
        if (!$visit) {
            return null;
        }

        // Mark the visit as converted
        $visit->markConverted($type, $value);

        // Update affiliate link conversion stats
        if ($visit->affiliateLink) {
            $visit->affiliateLink->incrementConversion($value);
        }

        // If related to a user registration, update the user record
        if ($relatedModel instanceof \App\Models\User) {
            $relatedModel->update([
                'referred_by_affiliate_id' => $visit->affiliate_id,
                'affiliate_attributed_at' => now()
            ]);
            
            $visit->update(['user_id' => $relatedModel->id]);
        }

        Log::info('Affiliate conversion tracked', [
            'affiliate_id' => $visit->affiliate_id,
            'visitor_id' => $visit->visitor_id,
            'conversion_type' => $type,
            'conversion_value' => $value
        ]);

        return $visit;
    }
}
