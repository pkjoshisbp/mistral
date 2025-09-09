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
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->string('visitor_phone')->nullable()->after('visitor_email');
            $table->string('visitor_country')->nullable()->after('visitor_phone');
            $table->string('visitor_region')->nullable()->after('visitor_country');
            $table->string('visitor_location')->nullable()->after('visitor_region');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_conversations', function (Blueprint $table) {
            $table->dropColumn(['visitor_phone', 'visitor_country', 'visitor_region', 'visitor_location']);
        });
    }
};
