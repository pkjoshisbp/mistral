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
        // Use raw SQL to avoid requiring doctrine/dbal for column modification
        DB::statement('ALTER TABLE credit_transactions MODIFY credits DECIMAL(20,4) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the previous size used when the column was created
        DB::statement('ALTER TABLE credit_transactions MODIFY credits DECIMAL(10,4) NULL');
    }
};
