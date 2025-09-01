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
                'description' => 'Perfect for small businesses getting started with AI chat',
                'monthly_price' => 49.00,
                'yearly_price' => 490.00,
                'token_cap_monthly' => 2000000, // 2M tokens
                'overage_price_per_100k' => 5.00,
                'features' => [
                    'Dashboard access',
                    'Email support',
                    'Basic analytics',
                    'Up to 2M tokens/month'
                ],
                'is_active' => true,
                'sort_order' => 1
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For growing businesses with higher AI usage needs',
                'monthly_price' => 199.00,
                'yearly_price' => 1990.00,
                'token_cap_monthly' => 10000000, // 10M tokens
                'overage_price_per_100k' => 4.00,
                'features' => [
                    'Everything in Starter',
                    'Team collaboration',
                    'API access',
                    'Advanced analytics',
                    'Custom alerts',
                    'Up to 10M tokens/month'
                ],
                'is_active' => true,
                'sort_order' => 2
            ],
            [
                'name' => 'Pay-as-you-go',
                'slug' => 'payg',
                'description' => 'Flexible pricing with $5 minimum charge for variable usage patterns',
                'monthly_price' => 5.00, // $5 minimum charge
                'yearly_price' => 5.00, // Same minimum
                'token_cap_monthly' => 200000, // 200k tokens for $5
                'overage_price_per_100k' => 5.00,
                'features' => [
                    '$5 minimum charge (200k tokens)',
                    'Tokens never expire',
                    'API access',
                    'Email support',
                    'Pay per additional token used',
                    'No monthly commitment'
                ],
                'is_active' => true,
                'sort_order' => 3
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
                    'Everything in Pro',
                    'SLA guarantee',
                    'White-label options',
                    'Priority support',
                    'Custom integrations',
                    'Dedicated account manager',
                    '50M+ tokens/month'
                ],
                'is_active' => true,
                'sort_order' => 4
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
