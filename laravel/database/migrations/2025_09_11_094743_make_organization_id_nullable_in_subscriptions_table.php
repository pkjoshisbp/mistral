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
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->change();
            });

            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['organization_id']);
            
            // Modify the column to be nullable
            $table->foreignId('organization_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable support
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable(false)->change();
            });

            return;
        }

        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop the nullable foreign key
            $table->dropForeign(['organization_id']);
            
            // Modify back to non-nullable (only if there are no null values)
            $table->foreignId('organization_id')->change();
            
            // Re-add the non-nullable foreign key constraint
            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
        });
    }
};
