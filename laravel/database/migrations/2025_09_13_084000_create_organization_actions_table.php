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
        Schema::create('organization_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->onDelete('cascade');
            $table->string('name'); // e.g., "Check Room Availability"
            $table->string('action_type'); // e.g., "CHECK_AVAILABILITY", "GET_PRICING", "QUERY_DATABASE"
            $table->text('description'); // Human-readable description for vector search
            $table->json('aliases')->nullable(); // Alternative phrasings for better matching
            $table->json('keywords')->nullable(); // Keywords for intent detection
            
            // Data source configuration
            $table->string('source_type'); // 'api', 'csv', 'excel', 'google_sheets', 'database'
            $table->json('source_config'); // Source-specific configuration
            
            // Parameter configuration
            $table->json('params_template')->nullable(); // Parameter mapping template
            $table->json('required_params')->nullable(); // Required parameters
            $table->json('optional_params')->nullable(); // Optional parameters
            
            // Execution settings
            $table->decimal('min_score_threshold', 4, 3)->default(0.750); // Minimum similarity score
            $table->integer('cache_ttl')->default(60); // Cache time in seconds
            $table->boolean('is_active')->default(true);
            $table->json('roles_allowed')->nullable(); // User roles that can use this action
            
            // Response formatting
            $table->text('response_template')->nullable(); // Template for formatting response
            $table->string('output_format')->default('text'); // 'text', 'json', 'table'
            
            $table->timestamps();
            
            $table->index(['organization_id', 'is_active']);
            $table->index(['action_type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_actions');
    }
};