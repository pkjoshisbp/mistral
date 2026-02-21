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
        Schema::table('analytics', function (Blueprint $table) {
            $table->text('page_url')->nullable()->change();
            $table->text('referrer')->nullable()->change();
            $table->text('user_agent')->nullable()->change();
            $table->text('page_title')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analytics', function (Blueprint $table) {
            $table->string('page_url')->nullable()->change();
            $table->string('referrer')->nullable()->change();
            $table->string('user_agent')->nullable()->change();
            $table->string('page_title')->nullable()->change();
        });
    }
};
