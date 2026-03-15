<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmDebugLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'session_id',
        'organization_id',
        'user_message',
        'intent',
        'intent_confidence',
        'intent_method',
        'original_query',
        'final_search_query',
        'query_was_rewritten',
        'rewritten_query',
        'best_qdrant_score',
        'context_length',
        'context_cleared',
        'low_relevance_warning',
        'expansion_attempted',
        'expanded_query',
        'expansion_score_gain',
        'faq_matched',
        'faq_match_type',
        'faq_keyword_score',
        'clarification_sought',
        'model_used',
        'ai_provider',
        'max_tokens',
        'llm_elapsed_ms',
        'search_elapsed_ms',
        'total_elapsed_ms',
        'input_tokens',
        'output_tokens',
        'envelope_parse_ok',
        'response_path',
        'extra',
    ];

    protected $casts = [
        'query_was_rewritten'   => 'boolean',
        'context_cleared'       => 'boolean',
        'low_relevance_warning' => 'boolean',
        'expansion_attempted'   => 'boolean',
        'faq_matched'           => 'boolean',
        'clarification_sought'  => 'boolean',
        'envelope_parse_ok'     => 'boolean',
        'best_qdrant_score'     => 'float',
        'intent_confidence'     => 'float',
        'expansion_score_gain'  => 'float',
        'faq_keyword_score'     => 'float',
        'extra'                 => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Scope to records newer than N days */
    public function scopeNewerThan($query, int $days)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /** Delete records older than the given number of days */
    public static function pruneOlderThan(int $days = 15): int
    {
        return static::where('created_at', '<', now()->subDays($days))->delete();
    }
}
