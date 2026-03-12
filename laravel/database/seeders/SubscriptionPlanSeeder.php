<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Low-cost entry plan to try AI chat support',
                'monthly_price' => 19.00,
                'yearly_price' => 190.00, // 10 months price for 12 months
                'token_cap_monthly' => 500000, // 500K tokens
                'overage_price_per_100k' => 6.00,
                'features' => [
                    'Dashboard access',
                    'Email support',
                    'Basic analytics',
                    'Up to 500K tokens/month',
                    'Perfect for small projects'
                ],
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Perfect for small businesses getting started with AI chat',
                'monthly_price' => 49.00,
                'yearly_price' => 490.00,
                'token_cap_monthly' => 2000000, // 2M tokens
                'overage_price_per_100k' => 5.00,
                'features' => [
                    'Everything in Starter',
                    'Priority email support',
                    'Advanced analytics',
                    'Up to 2M tokens/month',
                    'Team collaboration'
                ],
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For growing businesses with higher AI usage needs',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'token_cap_monthly' => 10000000, // 10M tokens
                'overage_price_per_100k' => 4.00,
                'features' => [
                    'Everything in Pro',
                    'Team collaboration',
                    'API access',
                    'Advanced analytics',
                    'Custom alerts',
                    'Up to 10M tokens/month'
                ],
                'is_active' => true,
                'sort_order' => 3
            ],
            [
                'name' => 'Pay-as-you-go',
                'slug' => 'payg',
                'description' => 'Flexible pricing with $5 minimum charge for variable usage patterns',
                'monthly_price' => 5.00, // $5 minimum charge
                'yearly_price' => 5.00, // Same minimum
                'token_cap_monthly' => 100000, // 100k tokens for $5
                'overage_price_per_100k' => 5.00,
                'features' => [
                    '$5 minimum charge (100k tokens)',
                    'Tokens never expire',
                    'API access',
                    'Email support',
                    'Pay per additional token used',
                    'No monthly commitment'
                ],
                'is_active' => true,
                'sort_order' => 4
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom solutions for large organizations',
                'monthly_price' => 999.00,
                'yearly_price' => 9990.00,
                'token_cap_monthly' => 50000000, // 50M+ tokens
                'overage_price_per_100k' => 3.00,
                'features' => [
                    'Everything in Business',
                    'SLA guarantee',
                    'White-label options',
                    'Priority support',
                    'Custom integrations',
                    'Dedicated account manager',
                    '50M+ tokens/month'
                ],
                'is_active' => true,
                'sort_order' => 5
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
