<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->unsignedInteger('cached_input_tokens')->nullable()->after('input_tokens')
                ->comment('Provider-reported cached input tokens billed at cached-input rate');
        });
    }

    public function down(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->dropColumn('cached_input_tokens');
        });
    }
};
