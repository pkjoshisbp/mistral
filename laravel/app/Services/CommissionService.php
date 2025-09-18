<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\AffiliateLink;
use App\Models\AffiliateCommission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    /**
     * Calculate and create commission for a conversion
     */
    public function calculateCommission(User $customer, AffiliateLink $link, float $saleAmount): ?AffiliateCommission
    {
        $affiliate = $link->affiliate;
        
        // Check if commission already exists for this customer and affiliate
        $existingCommission = AffiliateCommission::where('affiliate_id', $affiliate->id)
            ->where('affiliate_visit_id', $link->id)
            ->where('user_id', $customer->id)
            ->first();

        if ($existingCommission) {
            // Commission already exists, don't create duplicate
            return $existingCommission;
        }

        // Calculate commission amount based on affiliate's commission type
        $commissionAmount = $this->calculateAmount($affiliate, $saleAmount);

        if ($commissionAmount <= 0) {
            return null;
        }

        // Create commission record
        return AffiliateCommission::create([
            'affiliate_id' => $affiliate->id,
            'affiliate_visit_id' => $link->id,
            'user_id' => $customer->id,
            'commission_type' => $affiliate->commission_type,
            'order_value' => $saleAmount,
            'commission_amount' => $commissionAmount,
            'commission_rate' => $this->getCommissionRate($affiliate),
            'status' => 'pending', // Will be approved later
            'commission_start_date' => now(),
        ]);
    }

    /**
     * Calculate commission amount based on type
     */
    private function calculateAmount(Affiliate $affiliate, float $saleAmount): float
    {
        $rate = $this->getCommissionRate($affiliate);
        
        switch ($affiliate->commission_type) {
            case 'one_time':
                return $saleAmount * $rate;
                
            case 'recurring':
                // For recurring, this is the first month's commission
                // Subsequent months will be handled by recurring commission job
                return $saleAmount * $rate;
                
            default:
                return 0;
        }
    }

    /**
     * Get commission rate based on affiliate's type
     */
    private function getCommissionRate(Affiliate $affiliate): float
    {
        switch ($affiliate->commission_type) {
            case 'one_time':
                // 20-40% for one-time commissions
                return 0.30; // Default 30%, can be customized per affiliate
                
            case 'recurring':
                // 5-15% for recurring commissions
                return 0.10; // Default 10%, can be customized per affiliate
                
            default:
                return 0;
        }
    }

    /**
     * Process recurring commissions for approved affiliates
     * This should be called monthly via a scheduled job
     */
    public function processRecurringCommissions()
    {
        $recurringCommissions = AffiliateCommission::where('commission_type', 'ongoing')
            ->where('status', 'approved')
            ->with(['affiliate', 'user'])
            ->get();

        foreach ($recurringCommissions as $originalCommission) {
            // Check if customer still has active subscription
            if (!$this->hasActiveSubscription($originalCommission->user)) {
                continue;
            }

            // Check if we've reached 3-year limit
            $monthsSinceFirst = $originalCommission->commission_start_date->diffInMonths(now());
            if ($monthsSinceFirst >= 36) { // 3 years = 36 months
                continue;
            }

            // Check if commission for this month already exists
            $thisMonthCommission = AffiliateCommission::where('affiliate_id', $originalCommission->affiliate_id)
                ->where('user_id', $originalCommission->user_id)
                ->where('commission_type', 'ongoing')
                ->whereYear('commission_start_date', now()->year)
                ->whereMonth('commission_start_date', now()->month)
                ->first();

            if (!$thisMonthCommission) {
                // Create this month's recurring commission
                AffiliateCommission::create([
                    'affiliate_id' => $originalCommission->affiliate_id,
                    'affiliate_visit_id' => $originalCommission->affiliate_visit_id,
                    'user_id' => $originalCommission->user_id,
                    'commission_type' => 'ongoing',
                    'order_value' => $originalCommission->order_value,
                    'commission_amount' => $originalCommission->commission_amount,
                    'commission_rate' => $originalCommission->commission_rate,
                    'status' => 'pending',
                    'commission_start_date' => now(),
                    'parent_commission_id' => $originalCommission->id,
                    'is_recurring' => true,
                ]);
            }
        }
    }

    /**
     * Check if customer has active subscription
     */
    private function hasActiveSubscription(User $customer): bool
    {
        // This should check your actual subscription logic
        // For now, assume active if customer exists
        return $customer->exists;
    }

    /**
     * Approve pending commissions
     */
    public function approveCommissions(array $commissionIds): int
    {
        return DB::transaction(function () use ($commissionIds) {
            return AffiliateCommission::whereIn('id', $commissionIds)
                ->where('status', 'pending')
                ->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);
        });
    }

    /**
     * Reject pending commissions
     */
    public function rejectCommissions(array $commissionIds, string $reason = ''): int
    {
        return DB::transaction(function () use ($commissionIds, $reason) {
            return AffiliateCommission::whereIn('id', $commissionIds)
                ->where('status', 'pending')
                ->update([
                    'status' => 'rejected',
                    'rejected_at' => now(),
                    'rejection_reason' => $reason,
                ]);
        });
    }

    /**
     * Get affiliate earnings summary
     */
    public function getEarningsSummary(Affiliate $affiliate, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $commissions = $affiliate->commissions()
            ->where('commission_start_date', '>=', $startDate)
            ->get();

        return [
            'total_earned' => $commissions->where('status', 'approved')->sum('commission_amount'),
            'pending_approval' => $commissions->where('status', 'pending')->sum('commission_amount'),
            'total_conversions' => $commissions->count(),
            'approved_conversions' => $commissions->where('status', 'approved')->count(),
            'pending_conversions' => $commissions->where('status', 'pending')->count(),
            'rejected_conversions' => $commissions->where('status', 'rejected')->count(),
        ];
    }
}