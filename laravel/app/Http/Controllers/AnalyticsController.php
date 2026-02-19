<?php

namespace App\Http\Controllers;

use App\Models\Analytics;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnalyticsController extends Controller
{
    /**
     * Track analytics events
     */
    public function track(Request $request)
    {
        try {
            $orgId = $request->input('org_id');
            $organization = Organization::find($orgId);
            
            if (!$organization) {
                return response()->json(['error' => 'Organization not found'], 404);
            }

            // Generate visitor ID if not provided (cookie-based)
            $visitorId = $request->input('visitor_id') ?: 'visitor_' . Str::random(16);
            
            // Generate session ID if not provided
            $sessionId = $request->input('session_id') ?: 'session_' . Str::random(16);
            
            $userAgent = Str::limit((string) $request->userAgent(), 255, '');

            $data = [
                'organization_id' => $orgId,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'event_type' => $request->input('event_type', 'page_view'),
                'page_url' => $request->input('page_url'),
                'page_title' => $request->input('page_title'),
                'referrer' => $request->input('referrer'),
                'user_agent' => $userAgent,
                'ip_address' => $request->ip(),
                'country' => $request->input('country'),
                'region' => $request->input('region'),
                'city' => $request->input('city'),
                'event_data' => $request->input('event_data', []),
                'time_on_page' => $request->input('time_on_page', 0)
            ];

            Analytics::create($data);

            return response()->json([
                'success' => true,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId
            ]);

        } catch (\Exception $e) {
            Log::error('Analytics tracking failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Tracking failed'], 500);
        }
    }

    /**
     * Get analytics dashboard data
     */
    public function dashboard(Request $request, $orgId)
    {
        $organization = Organization::find($orgId);
        
        if (!$organization) {
            return response()->json(['error' => 'Organization not found'], 404);
        }

        $period = $request->input('period', '7'); // days
        $startDate = now()->subDays($period);

        // Get analytics data
        $analytics = Analytics::where('organization_id', $orgId)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate metrics
        $totalPageViews = $analytics->where('event_type', 'page_view')->count();
        $uniqueVisitors = $analytics->where('event_type', 'page_view')->pluck('visitor_id')->unique()->count();
        $totalSessions = $analytics->pluck('session_id')->unique()->count();
        $widgetInteractions = $analytics->whereIn('event_type', ['widget_open', 'chat_message'])->count();

        // Top pages
        $topPages = $analytics->where('event_type', 'page_view')
            ->groupBy('page_url')
            ->map(function ($group) {
                return [
                    'url' => $group->first()->page_url,
                    'title' => $group->first()->page_title,
                    'views' => $group->count(),
                    'unique_visitors' => $group->pluck('visitor_id')->unique()->count()
                ];
            })
            ->sortByDesc('views')
            ->values()
            ->take(10);

        // Traffic by country
        $trafficByCountry = $analytics->where('event_type', 'page_view')
            ->whereNotNull('country')
            ->groupBy('country')
            ->map(function ($group) {
                return [
                    'country' => $group->first()->country,
                    'visitors' => $group->pluck('visitor_id')->unique()->count(),
                    'views' => $group->count()
                ];
            })
            ->sortByDesc('visitors')
            ->values()
            ->take(10);

        // Hourly traffic for the last 24 hours
        $hourlyTraffic = Analytics::where('organization_id', $orgId)
            ->where('event_type', 'page_view')
            ->where('created_at', '>=', now()->subHours(24))
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as views, COUNT(DISTINCT visitor_id) as unique_visitors')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'period' => $period,
            'total_page_views' => $totalPageViews,
            'unique_visitors' => $uniqueVisitors,
            'total_sessions' => $totalSessions,
            'widget_interactions' => $widgetInteractions,
            'top_pages' => $topPages,
            'traffic_by_country' => $trafficByCountry,
            'hourly_traffic' => $hourlyTraffic,
            'avg_time_on_page' => $analytics->where('event_type', 'page_view')->avg('time_on_page')
        ]);
    }
}
