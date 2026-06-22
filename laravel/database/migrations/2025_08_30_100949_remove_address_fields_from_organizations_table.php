<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_COLUMNS = [
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person_name',
        'contact_person_title',
        'contact_person_email',
        'contact_person_phone',
        'business_hours',
        'services',
        'industry',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $existingColumns = Schema::getColumnListing('organizations');
        $columnsToDrop = array_values(array_intersect(self::LEGACY_COLUMNS, $existingColumns));

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $existingColumns = Schema::getColumnListing('organizations');
        $columnsToRestore = array_values(array_diff(self::LEGACY_COLUMNS, $existingColumns));

        if ($columnsToRestore === []) {
            return;
        }

        Schema::table('organizations', function (Blueprint $table) use ($columnsToRestore) {
            foreach ($columnsToRestore as $column) {
                if (in_array($column, ['business_hours', 'services'], true)) {
                    $table->text($column)->nullable();
                    continue;
                }

                $table->string($column)->nullable();
            }
        });
    }
};
