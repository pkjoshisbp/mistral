<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class EmailTrackingController extends Controller
{
    public function open(string $token): Response
    {
        $recipient = EmailCampaignRecipient::where('tracking_token', $token)->first();
        if ($recipient) {
            $now = now();
            $recipient->opened_at = $recipient->opened_at ?: $now;
            $recipient->last_opened_at = $now;
            $recipient->open_count = (int)($recipient->open_count ?? 0) + 1;
            $recipient->last_event = 'opened';
            $recipient->last_event_at = $now;
            $recipient->save();
        }

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==');
        return response($gif, 200)->header('Content-Type', 'image/gif');
    }
}
