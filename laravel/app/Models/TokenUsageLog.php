<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TokenUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'session_id',
        'subscription_id',
        'endpoint_type',
        'model',
        'tokens_used',
        'input_tokens',
        'cached_input_tokens',
        'output_tokens',
        'visible_output_tokens',
        'reasoning_tokens',
        'usage_is_estimated',
        'token_estimation_method',
        'request_summary',
        'used_at'
    ];

    protected $casts = [
        'used_at' => 'datetime',
        'usage_is_estimated' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function estimatedCostUsd(): float
    {
        return self::estimateCostUsd(
            (int) $this->tokens_used,
            $this->input_tokens !== null ? (int) $this->input_tokens : null,
            $this->output_tokens !== null ? (int) $this->output_tokens : null,
            $this->cached_input_tokens !== null ? (int) $this->cached_input_tokens : null,
            $this->model
        );
    }

    public static function estimateCostUsd(int $totalTokens, ?int $inputTokens = null, ?int $outputTokens = null, ?int $cachedInputTokens = null, ?string $model = null): float
    {
        $rates = self::pricingForModel($model);
        $totalTokens = max(0, $totalTokens);

        if ($inputTokens === null && $outputTokens === null) {
            $inputTokens = (int) floor($totalTokens / 2);
            $outputTokens = $totalTokens - $inputTokens;
        } else {
            $inputTokens = max(0, (int) ($inputTokens ?? 0));
            $outputTokens = $outputTokens !== null
                ? max(0, (int) $outputTokens)
                : max(0, $totalTokens - $inputTokens);
        }

        $cachedInputTokens = max(0, (int) ($cachedInputTokens ?? 0));
        $billableInputTokens = max(0, $inputTokens - $cachedInputTokens);

        return (
            ($billableInputTokens * $rates['input'])
            + ($cachedInputTokens * $rates['cached_input'])
            + ($outputTokens * $rates['output'])
        ) / 1000000;
    }

    public static function pricingForModel(?string $model = null): array
    {
        $model = strtolower(trim((string) $model));
        $pricing = config('openai.pricing_per_million', []);

        if ($model !== '' && isset($pricing[$model])) {
            return [
                'input' => (float) ($pricing[$model]['input'] ?? 0),
                'cached_input' => (float) ($pricing[$model]['cached_input'] ?? $pricing[$model]['input'] ?? 0),
                'output' => (float) ($pricing[$model]['output'] ?? 0),
            ];
        }

        $defaultModel = strtolower(trim((string) config('openai.default_model', 'gpt-5-mini')));
        $fallback = $pricing[$defaultModel] ?? $pricing['gpt-5-mini'] ?? ['input' => 0.25, 'output' => 2.00];

        return [
            'input' => (float) ($fallback['input'] ?? 0.25),
            'cached_input' => (float) ($fallback['cached_input'] ?? $fallback['input'] ?? 0.25),
            'output' => (float) ($fallback['output'] ?? 2.00),
        ];
    }
}
