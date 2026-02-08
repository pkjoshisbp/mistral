<?php

namespace App\Console\Commands;

use App\Models\EmailCampaignRecipient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ResendUnopenedEmails extends Command
{
    protected $signature = 'email:resend-unopened {--dry-run}';
    protected $description = 'Resend email campaigns to recipients who have not opened, with weekly interval and retry limits.';

    protected int $maxRetries = 2;
    protected int $intervalDays = 7;

    public function handle(): int
    {
        $dryRun = (bool)$this->option('dry-run');

        $query = EmailCampaignRecipient::query()
            ->where('status', 'sent')
            ->whereNull('opened_at')
            ->where(function ($q) {
                $q->whereNull('next_resend_at')->orWhere('next_resend_at', '<=', now());
            })
            ->where('resend_count', '<', $this->maxRetries)
            ->with('campaign');

        $count = $query->count();
        if ($count === 0) {
            $this->info('No recipients eligible for resend.');
            return self::SUCCESS;
        }

        $this->info("Eligible recipients: {$count}");

        $query->chunkById(100, function ($recipients) use ($dryRun) {
            foreach ($recipients as $recipient) {
                $campaign = $recipient->campaign;
                if (!$campaign) continue;

                $token = $recipient->tracking_token ?: bin2hex(random_bytes(16));
                $content = $this->buildPersonalizedContent($campaign->content, $recipient->variables ?? []);
                $subject = $this->buildPersonalizedContent($campaign->subject, $recipient->variables ?? []);
                $trackedContent = $this->buildTrackedContent($content, $token);

                if ($dryRun) {
                    $this->line("[Dry-run] Resend to {$recipient->recipient_email}");
                    continue;
                }

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

                    $recipient->tracking_token = $token;
                    $recipient->resend_count = (int)($recipient->resend_count ?? 0) + 1;
                    $recipient->last_sent_at = now();
                    $recipient->next_resend_at = now()->addDays($this->intervalDays);
                    $recipient->last_event = 'resent';
                    $recipient->last_event_at = now();
                    $recipient->save();
                } catch (\Throwable $e) {
                    $this->error("Resend failed for {$recipient->recipient_email}: {$e->getMessage()}");
                }
            }
        });

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
