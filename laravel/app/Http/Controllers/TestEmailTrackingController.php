<?php

namespace App\Http\Controllers;

use App\Models\EmailCampaign;
use App\Models\EmailCampaignRecipient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class TestEmailTrackingController extends Controller
{
    public function show(Request $request)
    {
        $recipientId = $request->query('recipient_id');
        $recipient = $recipientId ? EmailCampaignRecipient::find($recipientId) : null;
        
        return view('test-email-tracking', compact('recipient'));
    }
    
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'subject' => 'required|string',
            'content' => 'required|string',
        ]);
        
        try {
            // Create a test campaign
            $campaign = EmailCampaign::create([
                'name' => 'Test Email - ' . now()->format('Y-m-d H:i:s'),
                'subject' => $request->subject,
                'content' => $request->content,
                'sender_email' => config('mail.from.address'),
                'sender_name' => 'AI Chat Support',
                'status' => 'sending',
                'total_recipients' => 1,
                'created_by' => auth()->id() ?? 1,
            ]);
            
            $trackingToken = bin2hex(random_bytes(16));
            
            // Build tracked content
            $trackedContent = $this->buildTrackedContent($request->content, $trackingToken);
            
            // Send email
            Mail::send([], [], function ($message) use ($request, $trackedContent, $trackingToken) {
                $message->to($request->email)
                    ->subject($request->subject)
                    ->from(config('mail.from.address'), 'AI Chat Support')
                    ->html($trackedContent);
                $message->getSymfonyMessage()
                    ->getHeaders()
                    ->addTextHeader('X-AICS-Tracking-Token', $trackingToken);
            });
            
            // Create recipient record
            $recipient = $campaign->recipients()->create([
                'recipient_email' => $request->email,
                'tracking_token' => $trackingToken,
                'status' => 'sent',
                'sent_at' => now(),
                'delivered_at' => now(),
                'delivery_status' => 'sent',
                'last_event' => 'delivered',
                'last_event_at' => now(),
            ]);
            
            // Update campaign
            $campaign->update([
                'status' => 'sent',
                'sent_count' => 1,
                'sent_at' => now(),
            ]);
            
            return redirect()->route('test.email.check', $recipient->id)
                ->with('success', 'Test email sent successfully! Check your inbox and open it to test tracking.');
                
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to send email: ' . $e->getMessage());
        }
    }
    
    public function check($recipientId)
    {
        $recipient = EmailCampaignRecipient::findOrFail($recipientId);
        return view('test-email-tracking', compact('recipient'));
    }
    
    protected function buildTrackedContent(string $content, string $token): string
    {
        $baseUrl = rtrim(config('app.url'), '/');
        
        // Wrap all links with tracking URLs
        $content = preg_replace_callback(
            '/<a\s+([^>]*?)href=["\']([^"\']+)["\']([^>]*?)>/i',
            function($matches) use ($baseUrl, $token) {
                $beforeHref = $matches[1];
                $url = $matches[2];
                $afterHref = $matches[3];
                
                if (strpos($url, '/email/click/') !== false || strpos($url, '#') === 0 || strpos($url, 'mailto:') === 0) {
                    return $matches[0];
                }
                
                $trackingUrl = $baseUrl . '/email/click/' . $token . '?url=' . urlencode($url);
                return '<a ' . $beforeHref . 'href="' . $trackingUrl . '"' . $afterHref . '>';
            },
            $content
        );
        
        // Multiple tracking pixels for better reliability
        $pixel = '<img src="' . $baseUrl . '/email/open/' . $token . '.png" width="1" height="1" style="display:block;width:1px;height:1px;" alt="" />';
        $hiddenPixel = '<div style="display:none;"><img src="' . $baseUrl . '/email/open/' . $token . '.png" /></div>';
        
        // Insert tracking at multiple positions
        if (stripos($content, '</body>') !== false) {
            $content = preg_replace('/<\/body>/i', $pixel . $hiddenPixel . '</body>', $content, 1);
        } else {
            $content .= $pixel . $hiddenPixel;
        }
        
        // Also add at the beginning if there's a body tag
        if (stripos($content, '<body') !== false) {
            $content = preg_replace('/(<body[^>]*>)/i', '$1' . $pixel, $content, 1);
        }

        return $content;
    }
}
