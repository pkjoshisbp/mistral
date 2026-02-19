<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $plans = DB::table('pricing_plans')->get();
        foreach ($plans as $plan) {
            $metadata = $plan->metadata ? json_decode($plan->metadata, true) : [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $sourceTable = $metadata['source_table'] ?? null;
            $sourceId = $metadata['source_id'] ?? null;
            if (!$sourceTable || !$sourceId) {
                continue;
            }

            if (!empty($metadata['features'])) {
                continue;
            }

            if ($sourceTable === 'subscription_plans') {
                $features = DB::table('subscription_plans')->where('id', $sourceId)->value('features');
                if ($features !== null) {
                    $metadata['features'] = json_decode($features, true) ?: $features;
                }
            }

            if ($sourceTable === 'credit_packages') {
                $features = DB::table('credit_packages')->where('id', $sourceId)->value('features');
                if ($features !== null) {
                    $metadata['features'] = json_decode($features, true) ?: $features;
                }
            }

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
