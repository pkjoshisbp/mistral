<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->string('model', 120)->nullable()->after('endpoint_type');
            $table->index(['model', 'used_at'], 'token_usage_logs_model_used_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->dropIndex('token_usage_logs_model_used_at_idx');
            $table->dropColumn('model');
        });
    }
};
