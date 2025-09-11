<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CreditPackage;

class CreditPackageSeeder extends Seeder
{
    /**
     * Run the database seeder.
     *
     * Pricing Logic:
     * - Starter Plan: $49/month for 2M tokens = $24.50 per 1M tokens (monthly)
     * - Pro Plan: $199/month for 10M tokens = $19.90 per 1M tokens (monthly)
     * - PAYG: $5 for 100K tokens = $50 per 1M tokens
     * 
     * Credit packages should be more expensive than subscriptions since they never expire
     * Premium: 20-30% more than subscription equivalent
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Basic Credits',
                'slug' => 'basic-credits',
                'description' => 'Perfect for occasional usage with no expiration',
                'usd_price' => 69.00, // ~40% more than Starter monthly rate per 1M
                'inr_price' => 5700.00, // ~83x exchange rate
                'tokens' => 1000000, // 1M tokens
                'features' => [
                    '1 Million tokens',
                    'Never expires',
                    'Pay once, use anytime',
                    'Perfect for small projects',
                    'Email support'
                ],
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Standard Credits', 
                'slug' => 'standard-credits',
                'description' => 'Great value for regular usage with lifetime validity',
                'usd_price' => 129.00, // ~30% more than Starter for 2M
                'inr_price' => 10700.00,
                'tokens' => 2000000, // 2M tokens 
                'features' => [
                    '2 Million tokens',
                    'Never expires',
                    'Better value per token',
                    'Priority support',
                    'Usage analytics'
                ],
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Premium Credits',
                'slug' => 'premium-credits', 
                'description' => 'Best value for heavy users with maximum flexibility',
                'usd_price' => 299.00, // ~25% more than Pro monthly rate for 5M
                'inr_price' => 24800.00,
                'tokens' => 5000000, // 5M tokens
                'features' => [
                    '5 Million tokens',
                    'Never expires',
                    'Best value per token',
                    'Dedicated support',
                    'Advanced analytics',
                    'API priority access'
                ],
                'is_active' => true,
                'sort_order' => 3
            ]
        ];

        foreach ($packages as $package) {
            CreditPackage::create($package);
        }
    }
}
