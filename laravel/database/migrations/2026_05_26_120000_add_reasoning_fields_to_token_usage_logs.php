<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->unsignedInteger('visible_output_tokens')->nullable()->after('output_tokens')
                ->comment('User-visible completion tokens shown in the final response');
            $table->unsignedInteger('reasoning_tokens')->nullable()->after('visible_output_tokens')
                ->comment('Hidden/internal reasoning tokens, exact when available or estimated otherwise');
            $table->boolean('usage_is_estimated')->default(false)->after('reasoning_tokens')
                ->comment('True when total tokens are estimated instead of provided exactly by the model provider');
            $table->string('token_estimation_method', 80)->nullable()->after('usage_is_estimated')
                ->comment('Method used to derive token usage and any reasoning estimate');
        });
    }

    public function down(): void
    {
        Schema::table('token_usage_logs', function (Blueprint $table) {
            $table->dropColumn([
                'visible_output_tokens',
                'reasoning_tokens',
                'usage_is_estimated',
                'token_estimation_method',
            ]);
        });
    }
};