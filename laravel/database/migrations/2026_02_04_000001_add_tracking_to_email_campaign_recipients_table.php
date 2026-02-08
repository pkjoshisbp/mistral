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
            $table->string('tracking_token', 64)->nullable()->after('recipient_email');
            $table->string('message_id')->nullable()->after('tracking_token');
            $table->string('provider')->nullable()->after('message_id');
            $table->string('delivery_status')->nullable()->after('provider');
            $table->timestamp('delivered_at')->nullable()->after('delivery_status');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('last_opened_at')->nullable()->after('opened_at');
            $table->unsignedInteger('open_count')->default(0)->after('last_opened_at');
            $table->string('last_event')->nullable()->after('open_count');
            $table->timestamp('last_event_at')->nullable()->after('last_event');

            $table->unique('tracking_token');
            $table->index(['email_campaign_id', 'opened_at']);
            $table->index(['email_campaign_id', 'delivered_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('email_campaign_recipients', function (Blueprint $table) {
            $table->dropUnique(['tracking_token']);
            $table->dropIndex(['email_campaign_id', 'opened_at']);
            $table->dropIndex(['email_campaign_id', 'delivered_at']);

            $table->dropColumn([
                'tracking_token',
                'message_id',
                'provider',
                'delivery_status',
                'delivered_at',
                'opened_at',
                'last_opened_at',
                'open_count',
                'last_event',
                'last_event_at',
            ]);
        });
    }
};