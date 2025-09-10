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
        Schema::create('analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('visitor_id'); // Unique visitor identifier
            $table->string('session_id'); // Session identifier
            $table->string('event_type'); // page_view, widget_open, chat_message, etc.
            $table->string('page_url')->nullable();
            $table->string('page_title')->nullable();
            $table->string('referrer')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->json('event_data')->nullable(); // Additional event-specific data
            $table->integer('time_on_page')->default(0); // Seconds spent on page
            $table->timestamps();
            
            $table->index(['organization_id', 'event_type']);
            $table->index(['visitor_id', 'session_id']);
            $table->index(['created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics');
    }
};
