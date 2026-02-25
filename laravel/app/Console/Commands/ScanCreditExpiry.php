<?php

namespace App\Console\Commands;

use App\Models\UserCredit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ScanCreditExpiry extends Command
{
    protected $signature = 'credits:scan-expiry {--days=7 : Days ahead to flag upcoming expiry}';

    protected $description = 'Scan credit balances for upcoming expiry and grace-period users and log renewal reminders';

    public function handle(): int
    {
        $daysAhead = max(1, (int) $this->option('days'));
        $now = now();
        $soonCutoff = $now->copy()->addDays($daysAhead)->endOfDay();

        $totalScanned = 0;
        $expiringSoon = 0;
        $inGrace = 0;

        UserCredit::with('user')
            ->where('balance', '>', 0)
            ->chunkById(100, function ($credits) use (
                $daysAhead,
                $now,
                $soonCutoff,
                &$totalScanned,
                &$expiringSoon,
                &$inGrace
            ) {
                foreach ($credits as $credit) {
                    $totalScanned++;

                    $summary = $credit->getUsableCreditSummary($now);
                    $usable = (float) ($summary['usable_balance'] ?? 0);
                    if ($usable <= 0) {
                        continue;
                    }

                    $user = $credit->user;
                    $baseContext = [
                        'user_id' => $credit->user_id,
                        'email' => $user?->email,
                        'usable_balance' => $usable,
                        'raw_balance' => (float) ($summary['raw_balance'] ?? 0),
                        'expired_balance' => (float) ($summary['expired_balance'] ?? 0),
                        'in_grace_balance' => (float) ($summary['in_grace_balance'] ?? 0),
                    ];

                    if (($summary['in_grace_balance'] ?? 0) > 0 && !empty($summary['next_grace_end_at'])) {
                        $inGrace++;
                        $graceEndsAt = Carbon::parse($summary['next_grace_end_at']);
                        $daysLeft = max(0, $now->diffInDays($graceEndsAt, false));

                        Log::warning('Credit renewal reminder: user in grace period', array_merge($baseContext, [
                            'next_grace_end_at' => $graceEndsAt->toDateTimeString(),
                            'days_left_in_grace' => $daysLeft,
                            'reminder_type' => 'grace_period',
                        ]));

                        continue;
                    }

                    if (!empty($summary['next_expiry_at'])) {
                        $nextExpiryAt = Carbon::parse($summary['next_expiry_at']);
                        if ($nextExpiryAt->lte($soonCutoff)) {
                            $expiringSoon++;
                            $daysLeft = max(0, $now->diffInDays($nextExpiryAt, false));

                            Log::info('Credit renewal reminder: credits expiring soon', array_merge($baseContext, [
                                'next_expiry_at' => $nextExpiryAt->toDateTimeString(),
                                'days_to_expiry' => $daysLeft,
                                'reminder_window_days' => $daysAhead,
                                'reminder_type' => 'expiring_soon',
                            ]));
                        }
                    }
                }
            });

        $this->info("Scanned {$totalScanned} credit accounts. Expiring soon: {$expiringSoon}. In grace: {$inGrace}.");

        Log::info('Credit expiry scan completed', [
            'scanned_accounts' => $totalScanned,
            'expiring_soon' => $expiringSoon,
            'in_grace' => $inGrace,
            'window_days' => $daysAhead,
        ]);

        return self::SUCCESS;
    }
}
