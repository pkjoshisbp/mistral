<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $plans = DB::table('pricing_plans')
            ->where('plan_type', 'subscription')
            ->where('price', 0)
            ->where(function ($query) {
                $query->where('slug', 'like', 'free%')
                    ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.original_slug')) = 'free'");
            })
            ->get();

        foreach ($plans as $plan) {
            $metadata = json_decode($plan->metadata ?? '{}', true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $metadata['original_slug'] = 'free';
            $metadata['shopify_available'] = true;

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update([
                    'slug' => 'free-one-time',
                    'billing_period' => 'one_time',
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('pricing_plans')
            ->where('plan_type', 'subscription')
            ->where('slug', 'free-one-time')
            ->where('price', 0)
            ->update([
                'slug' => 'free-monthly',
                'billing_period' => 'monthly',
                'updated_at' => now(),
            ]);
    }
};
