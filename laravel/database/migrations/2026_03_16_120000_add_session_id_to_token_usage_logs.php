<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->string('session_id')->nullable()->after('organization_id');
            $table->index(['organization_id', 'session_id', 'used_at'], 'token_usage_logs_org_session_used_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->dropIndex('token_usage_logs_org_session_used_at_idx');
            $table->dropColumn('session_id');
        });
    }
};