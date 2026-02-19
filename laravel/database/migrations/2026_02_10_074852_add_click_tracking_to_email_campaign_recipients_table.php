<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->timestamp('last_clicked_at')->nullable()->after('clicked_at');
            $table->integer('click_count')->default(0)->after('last_clicked_at');
            $table->json('click_data')->nullable()->after('click_count');
        });
    }

    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropColumn(['clicked_at', 'last_clicked_at', 'click_count', 'click_data']);
        });
    }
};
