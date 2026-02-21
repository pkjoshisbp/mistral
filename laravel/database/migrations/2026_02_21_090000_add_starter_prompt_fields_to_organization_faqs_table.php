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
        Schema::table('organization_faqs', function (Blueprint $table) {
            $table->boolean('is_starter_prompt')->default(false)->after('is_active');
            $table->integer('starter_sort_order')->default(0)->after('is_starter_prompt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_faqs', function (Blueprint $table) {
            $table->dropColumn(['is_starter_prompt', 'starter_sort_order']);
        });
    }
};
