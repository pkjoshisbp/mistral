<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;

class EmailClickController extends Controller
{
    public function redirect(string $token, Request $request)
    {
        $url = $request->query('url');
        
        if (!$url) {
            abort(404);
        }
        
        $recipient = EmailCampaignRecipient::where('tracking_token', $token)->first();
        
        if ($recipient) {
            $now = now();
            
            // Track the click
            $clickData = $recipient->click_data ?? [];
            $clickData[] = [
                'url' => $url,
                'clicked_at' => $now->toDateTimeString(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ];
            
            $recipient->click_data = $clickData;
            $recipient->clicked_at = $recipient->clicked_at ?: $now;
            $recipient->last_clicked_at = $now;
            $recipient->click_count = (int)($recipient->click_count ?? 0) + 1;
            $recipient->last_event = 'clicked';
            $recipient->last_event_at = $now;
            $recipient->save();
        }
        
        return redirect()->away($url);
    }
}
