<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('credit_validity_months')->nullable()->after('credits');
        });

        DB::table('pricing_plans')
            ->where('plan_type', 'credit')
            ->update(['credit_validity_months' => 12]);

        DB::table('pricing_plans')
            ->where('plan_type', 'credit')
            ->orderBy('id')
            ->select('id', 'metadata', 'credit_validity_months')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    $metadata = $this->decodeMetadata($row->metadata);
                    $features = is_array($metadata['features'] ?? null) ? $metadata['features'] : [];
                    $metadata['features'] = $this->synchronizeCreditValidityFeature(
                        $features,
                        (int) ($row->credit_validity_months ?: 12)
                    );

                    DB::table('pricing_plans')
                        ->where('id', $row->id)
                        ->update(['metadata' => json_encode($metadata)]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->dropColumn('credit_validity_months');
        });
    }

    private function decodeMetadata($metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function synchronizeCreditValidityFeature(array $features, int $validityMonths): array
    {
        $targetLine = "Credits remain active for {$validityMonths} months and can be carried forward on timely renewal.";
        $updated = [];
        $replaced = false;

        foreach ($features as $feature) {
            $line = trim((string) $feature);
            if ($line === '') {
                continue;
            }

            if (
                preg_match('/never\s*expire|no\s*expiration|lifetime\s*validity/i', $line) ||
                preg_match('/credits\s*remain\s*active\s*for\s*\d+\s*months?.*carry\s*forward/i', $line)
            ) {
                if (!$replaced) {
                    $updated[] = $targetLine;
                    $replaced = true;
                }
                continue;
            }

            $updated[] = $line;
        }

        if (!$replaced) {
            $updated[] = $targetLine;
        }

        return array_values(array_unique($updated));
    }
};
