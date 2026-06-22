<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Razorpay fields are created by the earlier
     * 2025_08_30_201635_add_razorpay_fields migration.
     */
    public function up(): void
    {
        // Compatibility migration retained for databases that recorded this filename.
    }

    public function down(): void
    {
        // The earlier migration owns the Razorpay columns and their rollback.
    }
};
