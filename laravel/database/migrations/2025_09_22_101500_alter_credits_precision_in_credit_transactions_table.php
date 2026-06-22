<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->decimal('credits', 20, 4)->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE credit_transactions MODIFY credits DECIMAL(20,4) NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('credit_transactions', function (Blueprint $table) {
                $table->decimal('credits', 10, 4)->nullable()->change();
            });

            return;
        }

        DB::statement('ALTER TABLE credit_transactions MODIFY credits DECIMAL(10,4) NULL');
    }
};
