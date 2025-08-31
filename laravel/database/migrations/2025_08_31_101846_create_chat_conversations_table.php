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
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id')->unique();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('visitor_id')->nullable(); // For anonymous users
            $table->string('visitor_email')->nullable();
            $table->string('visitor_name')->nullable();
            $table->enum('status', ['active', 'closed', 'archived'])->default('active');
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable(); // For storing additional data
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
            
            $table->index(['organization_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('visitor_id');
            $table->index('last_activity_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
