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
            $table->longText('answer_markdown')->nullable()->after('answer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('organization_faqs', function (Blueprint $table) {
            $table->dropColumn('answer_markdown');
        });
    }
};
