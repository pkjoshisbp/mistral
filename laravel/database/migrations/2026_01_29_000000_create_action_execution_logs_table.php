<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('action_execution_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('action_id')->constrained('organization_actions')->cascadeOnDelete();
            $table->string('action_type')->nullable();
            $table->string('source_type')->nullable();
            $table->enum('status', ['success', 'failure'])->default('failure');
            $table->unsignedInteger('attempts')->default(1);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->json('params')->nullable();
            $table->json('result_meta')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['action_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_execution_logs');
    }
};
