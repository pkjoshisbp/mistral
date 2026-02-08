<?php

namespace App\Console\Commands;

use App\Models\EmailCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendScheduledEmailCampaigns extends Command
{
    protected $signature = 'email:send-scheduled {--dry-run}';
    protected $description = 'Send scheduled email campaigns that are due.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $campaigns = EmailCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->with('recipients')
            ->get();

        if ($campaigns->isEmpty()) {
            $this->info('No scheduled campaigns due.');
            return self::SUCCESS;
        }

        foreach ($campaigns as $campaign) {
            if ($dryRun) {
                $this->line("[Dry-run] Would send campaign: {$campaign->id} ({$campaign->name})");
                continue;
            }

            $campaign->update(['status' => 'sending']);

            $sentCount = 0;
            $failedCount = 0;

            $recipients = $campaign->recipients()->where('status', 'pending')->get();
            if ($recipients->isEmpty()) {
                $campaign->update([
                    'status' => 'failed',
                    'failed_count' => 0,
                    'sent_count' => 0,
                    'sent_at' => now(),
                    'total_recipients' => 0,
                ]);
                continue;
            }

            foreach ($recipients as $recipient) {
                $token = $recipient->tracking_token ?: bin2hex(random_bytes(16));
                $content = $this->buildPersonalizedContent($campaign->content, $recipient->variables ?? []);
                $subject = $this->buildPersonalizedContent($campaign->subject, $recipient->variables ?? []);
                $trackedContent = $this->buildTrackedContent($content, $token);

                try {
                    Mail::send([], [], function ($message) use ($recipient, $campaign, $trackedContent, $subject, $token) {
                        $message->to($recipient->recipient_email)
                            ->subject($subject)
                            ->from($campaign->sender_email, $campaign->sender_name)
                            ->html($trackedContent);

                        $message->getSymfonyMessage()
                            ->getHeaders()
                            ->addTextHeader('X-AICS-Tracking-Token', $token);

                        if (!empty($campaign->bcc_recipients)) {
                            $message->bcc($campaign->bcc_recipients);
                        }
                    });

                    $recipient->update([
                        'tracking_token' => $token,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'resend_count' => (int) ($recipient->resend_count ?? 0),
                        'last_sent_at' => now(),
                        'next_resend_at' => now()->addDays(7),
                        'last_event' => 'sent',
                        'last_event_at' => now(),
                    ]);
                    $sentCount++;
                } catch (\Throwable $e) {
                    $failedCount++;
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                }
            }

            $campaign->update([
                'status' => $sentCount > 0 ? 'sent' : 'failed',
                'sent_count' => $sentCount,
                'failed_count' => $failedCount,
                'sent_at' => now(),
                'total_recipients' => $sentCount + $failedCount,
            ]);
        }

        return self::SUCCESS;
    }

    protected function buildPersonalizedContent(string $content, array $variables): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/i', function ($m) use ($variables) {
            $key = $m[1];
            return $variables[$key] ?? $m[0];
        }, $content);
    }

    protected function buildTrackedContent(string $content, string $token): string
    {
        $content = $this->applyEmailTypography($content);
        $baseUrl = rtrim(config('app.url'), '/');
        $pixel = '<img src="' . $baseUrl . '/email/open/' . $token . '.png" width="1" height="1" style="display:none" alt="" />';

        if (stripos($content, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $pixel . '</body>', $content, 1);
        }

        return $content . $pixel;
    }

    protected function applyEmailTypography(string $content): string
    {
        $fontFamily = 'Segoe UI, Helvetica, Arial, sans-serif';
        $baseStyle = 'font-family: ' . $fontFamily . '; font-size: 16px; line-height: 1.6;';

        if (preg_match('/font-family:/i', $content)) {
            $content = preg_replace(
                '/font-family:\s*[^;"\']*arial[^;"\']*;?/i',
                'font-family: ' . $fontFamily . '; font-size: 16px; line-height: 1.6;',
                $content
            );
        } else {
            $content = '<div style="' . $baseStyle . '">' . $content . '</div>';
        }

        $content = preg_replace_callback('/<body([^>]*)>/i', function ($m) use ($baseStyle) {
            $attrs = $m[1] ?? '';
            if (stripos($attrs, 'style=') !== false) {
                return preg_replace('/style=("|\')(.*?)\1/i', 'style="$2 ' . $baseStyle . '"', $m[0], 1);
            }
            return '<body' . $attrs . ' style="' . $baseStyle . '">';
        }, $content);

        return $content;
    }
}
