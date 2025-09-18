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
        // Add affiliate tracking to users table
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('referred_by_affiliate_id')->nullable()->constrained('affiliates')->onDelete('set null');
            $table->timestamp('affiliate_attributed_at')->nullable();
        });

        // Add affiliate tracking to existing orders/payments table if it exists
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('affiliate_id')->nullable()->constrained('affiliates')->onDelete('set null');
                $table->boolean('affiliate_commission_processed')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['referred_by_affiliate_id']);
            $table->dropColumn(['referred_by_affiliate_id', 'affiliate_attributed_at']);
        });

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['affiliate_id']);
                $table->dropColumn(['affiliate_id', 'affiliate_commission_processed']);
            });
        }
    }
};
