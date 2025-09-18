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
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_commission_id')->nullable()->after('user_id');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            
            $table->foreign('parent_commission_id')
                  ->references('id')
                  ->on('affiliate_commissions')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('affiliate_commissions', function (Blueprint $table) {
            $table->dropForeign(['parent_commission_id']);
            $table->dropColumn([
                'parent_commission_id',
                'rejected_at',
                'rejection_reason'
            ]);
        });
    }
};
