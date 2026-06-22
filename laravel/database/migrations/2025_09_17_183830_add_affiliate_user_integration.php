<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add affiliate role to users table
        Schema::table('users', function (Blueprint $table) {
            // Add affiliate role - we'll use existing role column or add if not exists
            if (!Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('customer'); // customer, admin, affiliate
            }
        });

        // Add user_id to affiliates table to link with users
        Schema::table('affiliates', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->index('user_id');
        });

        // SQLite stores Laravel enums as text, so no enum definition update is needed.
        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin', 'affiliate') DEFAULT 'customer'");
        }
    }

    public function down(): void
    {
        Schema::table('affiliates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'admin') DEFAULT 'customer'");
        }
    }
};
