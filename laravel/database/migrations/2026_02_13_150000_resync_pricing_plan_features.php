<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('pricing_plans')->get(['id', 'plan_type', 'slug', 'metadata']);

        foreach ($plans as $plan) {
            $metadata = $plan->metadata ? json_decode($plan->metadata, true) : [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $features = $metadata['features'] ?? null;
            $hasFeatures = is_array($features) ? count($features) > 0 : !empty($features);
            if ($hasFeatures) {
                continue;
            }

            $sourceTable = $metadata['source_table'] ?? null;
            $sourceId = $metadata['source_id'] ?? null;
            $baseSlug = $metadata['original_slug'] ?? $plan->slug;

            $legacyFeatures = null;

            if ($sourceTable === 'subscription_plans' && $sourceId) {
                $legacyFeatures = DB::table('subscription_plans')->where('id', $sourceId)->value('features');
            } elseif ($sourceTable === 'credit_packages' && $sourceId) {
                $legacyFeatures = DB::table('credit_packages')->where('id', $sourceId)->value('features');
            } elseif ($plan->plan_type === 'subscription') {
                $legacyRow = DB::table('subscription_plans')
                    ->where('slug', $baseSlug)
                    ->first(['id', 'features']);
                if ($legacyRow) {
                    $legacyFeatures = $legacyRow->features;
                    $metadata['source_table'] = 'subscription_plans';
                    $metadata['source_id'] = $legacyRow->id;
                }
            } elseif ($plan->plan_type === 'credit') {
                $legacyRow = DB::table('credit_packages')
                    ->where('slug', $baseSlug)
                    ->first(['id', 'features']);
                if ($legacyRow) {
                    $legacyFeatures = $legacyRow->features;
                    $metadata['source_table'] = 'credit_packages';
                    $metadata['source_id'] = $legacyRow->id;
                }
            }

            if ($legacyFeatures === null) {
                continue;
            }

            $decoded = json_decode($legacyFeatures, true);
            $metadata['features'] = is_array($decoded) ? $decoded : $legacyFeatures;

            DB::table('pricing_plans')->where('id', $plan->id)->update([
                'metadata' => json_encode($metadata),
            ]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
