<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analytics;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsTrackingController extends Controller
{
    public function track(Request $request)
    {
        try {
            $input = $request->all();
            if (!isset($input['organization_id']) && isset($input['org_id'])) {
                $input['organization_id'] = $input['org_id'];
            }
            if (!isset($input['organization_id']) && isset($input['org_slug'])) {
                $org = Organization::where('slug', $input['org_slug'])->first();
                if ($org) {
                    $input['organization_id'] = $org->id;
                }
            }
            if (isset($input['organization_id']) && !is_numeric($input['organization_id'])) {
                $org = Organization::where('slug', $input['organization_id'])->first();
                if ($org) {
                    $input['organization_id'] = $org->id;
                }
            }
            if (!isset($input['page_url'])) {
                $input['page_url'] = $request->header('referer') ?? config('app.url');
            }
            if (!isset($input['timestamp'])) {
                $input['timestamp'] = now()->toISOString();
            }
            if (!isset($input['time_on_page']) || !is_numeric($input['time_on_page'])) {
                $input['time_on_page'] = 0;
            }

            if (isset($input['event_data']) && is_string($input['event_data'])) {
                $decoded = json_decode($input['event_data'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $input['event_data'] = $decoded;
                } else {
                    $input['event_data'] = ['raw' => $input['event_data']];
                }
            }

            $validator = validator($input, [
                'organization_id' => 'required|exists:organizations,id',
                'event_type' => 'required|string|in:page_view,widget_open,chat_message,widget_close,unanswered_question,widget_expand,widget_minimize,widget_load',
                'page_url' => 'nullable|string',
                'page_title' => 'nullable|string',
                'referrer' => 'nullable|string',
                'user_agent' => 'nullable|string',
                'event_data' => 'nullable|array',
                'timestamp' => 'required|date',
                'time_on_page' => 'nullable|numeric|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();

            // Generate or get visitor ID from session/cookie
            $visitorId = $this->getVisitorId($request);
            $sessionId = $this->getSessionId($request);

            // Get IP and location data
            $ipAddress = $request->ip();
            $locationData = $this->getLocationFromIP($ipAddress);

            // Create analytics record
            Analytics::create([
                'organization_id' => $validated['organization_id'],
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'event_type' => $validated['event_type'],
                'page_url' => $validated['page_url'] ?? config('app.url'),
                'page_title' => $validated['page_title'] ?? '',
                'referrer' => $validated['referrer'] ?? '',
                'user_agent' => $validated['user_agent'] ?? $request->userAgent() ?? '',
                'ip_address' => $ipAddress,
                'country' => $locationData['country'] ?? null,
                'region' => $locationData['region'] ?? null,
                'city' => $locationData['city'] ?? null,
                'event_data' => $validated['event_data'] ?? null,
                'time_on_page' => (int) ($validated['time_on_page'] ?? 0),
                'created_at' => $validated['timestamp']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Event tracked successfully'
            ]);

        } catch (\Exception $e) {
            \Log::error('Analytics tracking error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to track event'
            ], 500);
        }
    }

    private function getVisitorId(Request $request)
    {
        // Try to get visitor ID from cookie or generate new one
        $visitorId = $request->cookie('ai_chat_visitor_id');
        
        if (!$visitorId) {
            $visitorId = Str::uuid()->toString();
            
            // Set cookie for 30 days
            cookie()->queue('ai_chat_visitor_id', $visitorId, 60 * 24 * 30);
        }

        return $visitorId;
    }

    private function getSessionId(Request $request)
    {
        // Try to get session ID from cookie or generate new one
        $sessionId = $request->cookie('ai_chat_session_id');
        
        if (!$sessionId) {
            $sessionId = Str::uuid()->toString();
            
            // Set cookie for 30 minutes
            cookie()->queue('ai_chat_session_id', $sessionId, 30);
        }

        return $sessionId;
    }

    private function getLocationFromIP($ipAddress)
    {
        // Skip location lookup for local IPs
        if ($ipAddress === '127.0.0.1' || $ipAddress === '::1' || 
            filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return [
                'country' => 'Local',
                'region' => 'Local',
                'city' => 'Local'
            ];
        }

        try {
            // Use a free IP geolocation service
            $response = file_get_contents("http://ip-api.com/json/{$ipAddress}?fields=status,country,regionName,city");
            $data = json_decode($response, true);

            if ($data && $data['status'] === 'success') {
                return [
                    'country' => $data['country'] ?? null,
                    'region' => $data['regionName'] ?? null,
                    'city' => $data['city'] ?? null
                ];
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to get location for IP: ' . $ipAddress);
        }

        return [
            'country' => null,
            'region' => null,
            'city' => null
        ];
    }

    private function calculateTimeOnPage(Request $request)
    {
        // For now, return null. This would need to be calculated on the frontend
        // and sent with subsequent requests
        return null;
    }
}
