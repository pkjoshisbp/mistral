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
        Schema::create('token_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained()->onDelete('set null');
            $table->string('endpoint_type'); // 'chat', 'search', 'rewrite', etc.
            $table->integer('tokens_used');
            $table->text('request_summary')->nullable(); // brief description of the request
            $table->timestamp('used_at');
            $table->timestamps();
            
            $table->index(['user_id', 'used_at']);
            $table->index(['organization_id', 'used_at']);
            $table->index(['subscription_id', 'used_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('token_usage_logs');
    }
};
