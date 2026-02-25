<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existingMonthly = DB::table('pricing_plans')->where('slug', 'free-monthly')->first();

        $baseMetadata = [
            'source_table' => 'pricing_plans',
            'source_id' => null,
            'original_slug' => 'free',
            'shopify_available' => true,
            'shopify_trial_days' => 0,
            'features' => [
                '20K tokens for free trial',
                'Try before you buy',
            ],
        ];

        if (!$existingMonthly) {
            DB::table('pricing_plans')->insert([
                'plan_type' => 'subscription',
                'name' => 'Free',
                'slug' => 'free-monthly',
                'description' => 'Free trial plan for Shopify users',
                'price' => 0,
                'currency' => 'USD',
                'billing_period' => 'monthly',
                'token_cap' => 20000,
                'overage_price_per_100k' => 0,
                'credits' => null,
                'credit_validity_months' => null,
                'is_active' => true,
                'sort_order' => 0,
                'metadata' => json_encode($baseMetadata),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('pricing_plans')
                ->where('id', $existingMonthly->id)
                ->update([
                    'name' => 'Free',
                    'description' => 'Free trial plan for Shopify users',
                    'price' => 0,
                    'token_cap' => 20000,
                    'is_active' => true,
                    'sort_order' => 0,
                    'metadata' => json_encode(array_merge($baseMetadata, [
                        'source_id' => $existingMonthly->id,
                    ])),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        DB::table('pricing_plans')->where('slug', 'free-monthly')->delete();
    }
};
