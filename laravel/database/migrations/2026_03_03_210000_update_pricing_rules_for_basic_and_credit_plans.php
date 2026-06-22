<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptionRows = DB::table('pricing_plans')
            ->where('plan_type', 'subscription')
            ->whereIn('billing_period', ['monthly', 'yearly'])
            ->get();

        foreach ($subscriptionRows as $row) {
            $metadata = json_decode($row->metadata ?? '{}', true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $originalSlug = strtolower((string) ($metadata['original_slug'] ?? ''));
            $slug = strtolower((string) ($row->slug ?? ''));
            $isBasic = $originalSlug === 'basic' || str_contains($slug, 'basic');

            if (!$isBasic) {
                continue;
            }

            $updates = [
                'token_cap' => 500000,
                'updated_at' => now(),
            ];

            if ($row->billing_period === 'monthly') {
                $updates['price'] = 19;
            }

            if ($row->billing_period === 'yearly') {
                $updates['price'] = 190;
            }

            $features = $metadata['features'] ?? [];
            if (!is_array($features)) {
                $features = [];
            }

            $normalized = [];
            foreach ($features as $feature) {
                $line = trim((string) $feature);
                if ($line === '') {
                    continue;
                }

                if (preg_match('/up\s*to\s*\d+[\d,.]*\s*[km]?\s*tokens\/month/i', $line)) {
                    continue;
                }

                $normalized[] = $line;
            }

            $normalized[] = 'Up to 500K tokens/month';
            $metadata['features'] = array_values(array_unique($normalized));
            $updates['metadata'] = json_encode($metadata);

            DB::table('pricing_plans')->where('id', $row->id)->update($updates);
        }

        $creditRows = DB::table('pricing_plans')
            ->where('plan_type', 'credit')
            ->get();

        foreach ($creditRows as $row) {
            $metadata = json_decode($row->metadata ?? '{}', true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $originalSlug = strtolower((string) ($metadata['original_slug'] ?? ''));
            $slug = strtolower((string) ($row->slug ?? ''));
            $isStarterCredits = $originalSlug === 'starter-credits' || str_contains($slug, 'starter-credits');

            $updates = [
                'credit_validity_months' => 12,
                'updated_at' => now(),
            ];

            if ($isStarterCredits) {
                $updates['price'] = 24;
                $updates['credits'] = 500000;
                $metadata['features'] = [
                    '500 Thousand tokens',
                    '1 Year validity',
                    'Carry forward on timely renewal',
                    'Easy entry, no commitment',
                    'Perfect for testing',
                    'Email support',
                    'Credits remain active for 12 months and can be carried forward on timely renewal.',
                ];
            } else {
                $features = $metadata['features'] ?? [];
                if (!is_array($features)) {
                    $features = [];
                }

                $features[] = 'Credits remain active for 12 months and can be carried forward on timely renewal.';
                $metadata['features'] = array_values(array_unique(array_map(static function ($value) {
                    return trim((string) $value);
                }, $features)));
            }

            $usdPrice = array_key_exists('price', $updates)
                ? (float) $updates['price']
                : (float) $row->price;
            $metadata['inr_price'] = number_format($usdPrice * 100, 2, '.', '');
            $updates['metadata'] = json_encode($metadata);

            DB::table('pricing_plans')->where('id', $row->id)->update($updates);
        }

        $freeRows = DB::table('pricing_plans')
            ->where('plan_type', 'subscription')
            ->where(function ($query) {
                $query->where('price', 0)
                    ->orWhere('slug', 'like', 'free%')
                    ->orWhere('metadata->original_slug', 'free');
            })
            ->get();

        foreach ($freeRows as $row) {
            $metadata = json_decode($row->metadata ?? '{}', true);
            if (!is_array($metadata)) {
                $metadata = [];
            }

            $metadata['shopify_available'] = true;
            $metadata['non_shopify_available'] = true;

            DB::table('pricing_plans')
                ->where('id', $row->id)
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
    }
};
