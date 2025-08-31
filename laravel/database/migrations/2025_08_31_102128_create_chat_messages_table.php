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
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('chat_conversations')->onDelete('cascade');
            $table->enum('sender_type', ['user', 'ai', 'agent']); // user, ai assistant, or human agent
            $table->string('sender_name')->nullable();
            $table->text('message');
            $table->json('metadata')->nullable(); // For storing AI model info, confidence scores, etc.
            $table->boolean('is_internal')->default(false); // For internal notes/messages
            $table->timestamp('sent_at');
            $table->timestamps();
            
            $table->index(['conversation_id', 'sent_at']);
            $table->index('sender_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
