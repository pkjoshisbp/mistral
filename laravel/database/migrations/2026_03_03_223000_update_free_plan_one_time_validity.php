<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $freePlan = DB::table('pricing_plans')
            ->where('name', 'Free')
            ->where('plan_type', 'subscription')
            ->first();

        if (!$freePlan) {
            return;
        }

        $metadata = [];
        if (!empty($freePlan->metadata)) {
            $decoded = json_decode((string) $freePlan->metadata, true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $features = [];
        if (isset($metadata['features']) && is_array($metadata['features'])) {
            $features = array_values(array_filter(array_map('strval', $metadata['features'])));
        }

        $features = array_values(array_filter($features, function (string $feature): bool {
            return !preg_match('/month|monthly|free\s*trial|shopify/i', $feature);
        }));

        $features[] = '20K one-time tokens';
        $features[] = 'Valid for 1 month';
        $features[] = 'Try before you buy';

        $features = array_values(array_unique($features));

        $metadata['features'] = $features;
        $metadata['free_plan_validity_months'] = 1;

        DB::table('pricing_plans')
            ->where('id', $freePlan->id)
            ->update([
                'billing_period' => 'one_time',
                'price' => 0,
                'token_cap' => 20000,
                'credit_validity_months' => 1,
                'description' => 'One-time free plan with 20K tokens valid for 1 month.',
                'metadata' => json_encode($metadata),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // intentionally left empty
    }
};
