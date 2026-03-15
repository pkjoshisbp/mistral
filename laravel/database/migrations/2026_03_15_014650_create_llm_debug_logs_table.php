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
        Schema::create('llm_debug_logs', function (Blueprint $table) {
            $table->id();

            // Link back to conversation & org
            $table->unsignedBigInteger('conversation_id')->nullable()->index();
            $table->string('session_id', 191)->index();
            $table->unsignedBigInteger('organization_id')->index();

            // User message
            $table->text('user_message')->nullable();

            // Intent detection
            $table->string('intent', 100)->nullable();
            $table->decimal('intent_confidence', 5, 4)->nullable();
            $table->string('intent_method', 100)->nullable();

            // Search / retrieval
            $table->text('original_query')->nullable();
            $table->text('final_search_query')->nullable();
            $table->boolean('query_was_rewritten')->default(false);
            $table->text('rewritten_query')->nullable();
            $table->decimal('best_qdrant_score', 6, 4)->nullable();
            $table->integer('context_length')->nullable();        // chars of context passed to LLM
            $table->boolean('context_cleared')->default(false);   // score < 0.52, context blanked
            $table->boolean('low_relevance_warning')->default(false); // 0.52-0.62 mismatch warning

            // Query expansion (low-confidence fallback)
            $table->boolean('expansion_attempted')->default(false);
            $table->text('expanded_query')->nullable();
            $table->decimal('expansion_score_gain', 6, 4)->nullable();

            // FAQ / keyword matching
            $table->boolean('faq_matched')->default(false);
            $table->string('faq_match_type', 50)->nullable();     // direct / keyword / clarification
            $table->decimal('faq_keyword_score', 8, 4)->nullable();

            // Clarification
            $table->boolean('clarification_sought')->default(false);

            // LLM call details
            $table->string('model_used', 191)->nullable();
            $table->string('ai_provider', 100)->nullable();
            $table->integer('max_tokens')->nullable();
            $table->integer('llm_elapsed_ms')->nullable();
            $table->integer('search_elapsed_ms')->nullable();
            $table->integer('total_elapsed_ms')->nullable();
            $table->integer('input_tokens')->nullable();
            $table->integer('output_tokens')->nullable();

            // Response structure
            $table->boolean('envelope_parse_ok')->default(false);
            $table->string('response_path', 100)->nullable(); // 'faq_direct','faq_keyword','clarification','llm','stream_llm'

            // Catch-all for extra debug fields
            $table->json('extra')->nullable();

            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('chat_conversations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_debug_logs');
    }
};
