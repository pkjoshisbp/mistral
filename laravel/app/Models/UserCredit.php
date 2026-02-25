<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserCredit extends Model
{
    use HasFactory;

    private const DEFAULT_VALIDITY_MONTHS = 12;
    private const GRACE_PERIOD_MONTHS = 1;

    protected $fillable = [
        'user_id',
        'balance',
        'total_purchased',
        'total_used',
        'last_updated_at'
    ];

    protected $casts = [
        'balance' => 'decimal:4',
        'total_purchased' => 'decimal:4',
        'total_used' => 'decimal:4',
        'last_updated_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get or create user credits record
     */
    public static function getOrCreateForUser($userId)
    {
        return static::firstOrCreate(['user_id' => $userId], [
            'balance' => 0,
            'total_purchased' => 0,
            'total_used' => 0,
            'last_updated_at' => now()
        ]);
    }

    /**
     * Add credits to user balance (thread-safe)
     */
    public function addCredits($amount, $reason = 'Purchase', array $extra = [])
    {
        DB::transaction(function () use ($amount, $reason, $extra) {
            $this->increment('balance', $amount);
            $this->increment('total_purchased', $amount);
            $this->update(['last_updated_at' => now()]);
            
            // Log the transaction
            $payload = [
                'user_id' => $this->user_id,
                'type' => 'credit',
                'amount' => $amount,
                'balance_after' => $this->fresh()->balance,
                'description' => $reason
            ];

            // Allow passing optional metadata fields for purchases
            $allowedExtras = [
                'reference_id',
                'subscription_id',
                'credit_package_id',
                'credits',
                'payment_method',
                'razorpay_payment_id',
                'notes',
                'metadata',
            ];
            foreach ($allowedExtras as $key) {
                if (array_key_exists($key, $extra)) {
                    $payload[$key] = $extra[$key];
                }
            }

            // If explicit credits not provided, set it equal to amount (commonly 1 credit = 1 token)
            if (!isset($payload['credits'])) {
                $payload['credits'] = $amount;
            }

            CreditTransaction::create($payload);
        });
    }

    /**
     * Deduct credits from user balance (thread-safe)
     */
    public function deductCredits($amount, $reason = 'Usage', array $extra = [])
    {
        return DB::transaction(function () use ($amount, $reason, $extra) {
            if ($this->getUsableCreditBalance() < $amount) {
                return false; // Insufficient credits
            }
            
            $this->decrement('balance', $amount);
            $this->increment('total_used', $amount);
            $this->update(['last_updated_at' => now()]);
            
            // Log the transaction
            $payload = [
                'user_id' => $this->user_id,
                'type' => 'debit',
                'amount' => $amount,
                'balance_after' => $this->fresh()->balance,
                'description' => $reason
            ];

            // Optional metadata on usage as well
            $allowedExtras = [
                'reference_id',
                'subscription_id',
                'credit_package_id',
                'credits',
                'payment_method',
                'razorpay_payment_id',
                'notes',
                'metadata',
            ];
            foreach ($allowedExtras as $key) {
                if (array_key_exists($key, $extra)) {
                    $payload[$key] = $extra[$key];
                }
            }

            // For debits, keep credits equal to amount unless overridden
            if (!isset($payload['credits'])) {
                $payload['credits'] = $amount;
            }

            CreditTransaction::create($payload);
            
            return true;
        });
    }

    /**
     * Check if user has sufficient credits
     */
    public function hasSufficientCredits($amount)
    {
        return $this->getUsableCreditBalance() >= $amount;
    }

    public function getUsableCreditBalance(): float
    {
        $summary = $this->getUsableCreditSummary();

        return (float) ($summary['usable_balance'] ?? 0);
    }

    public function getUsableCreditSummary(?Carbon $asOf = null): array
    {
        $asOf = $asOf ?: now();

        $transactions = CreditTransaction::where('user_id', $this->user_id)
            ->whereIn('type', ['credit', 'debit'])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'type', 'amount', 'credit_package_id', 'created_at']);

        $planIds = $transactions
            ->where('type', 'credit')
            ->pluck('credit_package_id')
            ->filter()
            ->unique()
            ->values();

        $validityByPlanId = PricingPlan::whereIn('id', $planIds)
            ->pluck('credit_validity_months', 'id');

        $lots = [];
        $totalCredited = 0.0;
        $totalDebited = 0.0;

        foreach ($transactions as $tx) {
            $amount = (float) ($tx->amount ?? 0);
            if ($amount <= 0) {
                continue;
            }

            if ($tx->type === 'credit') {
                $validityMonths = (int) ($validityByPlanId[$tx->credit_package_id] ?? self::DEFAULT_VALIDITY_MONTHS);
                if ($validityMonths <= 0) {
                    $validityMonths = self::DEFAULT_VALIDITY_MONTHS;
                }

                $creditedAt = Carbon::parse($tx->created_at);
                $expiryAt = $creditedAt->copy()->addMonthsNoOverflow($validityMonths)->endOfDay();
                $graceEndsAt = $expiryAt->copy()->addMonthsNoOverflow(self::GRACE_PERIOD_MONTHS)->endOfDay();

                $lots[] = [
                    'remaining' => $amount,
                    'expiry_at' => $expiryAt,
                    'grace_ends_at' => $graceEndsAt,
                ];
                $totalCredited += $amount;
                continue;
            }

            if ($tx->type === 'debit') {
                $toConsume = $amount;
                $totalDebited += $amount;

                foreach ($lots as &$lot) {
                    if ($toConsume <= 0) {
                        break;
                    }
                    if ($lot['remaining'] <= 0) {
                        continue;
                    }

                    $used = min($lot['remaining'], $toConsume);
                    $lot['remaining'] -= $used;
                    $toConsume -= $used;
                }
                unset($lot);
            }
        }

        $usableBalance = 0.0;
        $expiredBalance = 0.0;
        $inGraceBalance = 0.0;
        $nextExpiryAt = null;
        $nextGraceEndAt = null;

        foreach ($lots as $lot) {
            $remaining = (float) ($lot['remaining'] ?? 0);
            if ($remaining <= 0) {
                continue;
            }

            $expiryAt = $lot['expiry_at'];
            $graceEndsAt = $lot['grace_ends_at'];

            if ($asOf->gt($graceEndsAt)) {
                $expiredBalance += $remaining;
                continue;
            }

            $usableBalance += $remaining;

            if ($asOf->gt($expiryAt)) {
                $inGraceBalance += $remaining;
                if ($nextGraceEndAt === null || $graceEndsAt->lt($nextGraceEndAt)) {
                    $nextGraceEndAt = $graceEndsAt->copy();
                }
                continue;
            }

            if ($nextExpiryAt === null || $expiryAt->lt($nextExpiryAt)) {
                $nextExpiryAt = $expiryAt->copy();
            }
        }

        $computedRawBalance = max(0, $totalCredited - $totalDebited);

        return [
            'usable_balance' => round($usableBalance, 4),
            'expired_balance' => round($expiredBalance, 4),
            'in_grace_balance' => round($inGraceBalance, 4),
            'raw_balance' => round($computedRawBalance, 4),
            'next_expiry_at' => $nextExpiryAt,
            'next_grace_end_at' => $nextGraceEndAt,
            'grace_period_months' => self::GRACE_PERIOD_MONTHS,
        ];
    }
}