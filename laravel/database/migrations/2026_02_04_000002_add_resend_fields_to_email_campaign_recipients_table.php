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
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->unsignedInteger('resend_count')->default(0)->after('open_count');
            $table->timestamp('last_sent_at')->nullable()->after('resend_count');
            $table->timestamp('next_resend_at')->nullable()->after('last_sent_at');

            $table->index(['email_campaign_id', 'next_resend_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['email_campaign_id', 'next_resend_at']);
            $table->dropColumn(['resend_count', 'last_sent_at', 'next_resend_at']);
        });
    }
};