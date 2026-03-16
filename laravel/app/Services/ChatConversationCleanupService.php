<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\LlmDebugLog;
use App\Models\Subscription;
use App\Models\TokenUsageLog;
use App\Models\UserCredit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatConversationCleanupService
{
    public function deleteConversation(ChatConversation $conversation, ?int $deletedByUserId = null): array
    {
        return DB::transaction(function () use ($conversation, $deletedByUserId) {
            $sessionId = (string) ($conversation->conversation_id ?? '');
            $organizationId = (int) $conversation->organization_id;

            $tokenLogs = $this->resolveTokenUsageLogs($conversation);

            $refundedTokens = 0;
            $refundedLogCount = 0;

            foreach ($tokenLogs as $log) {
                $this->refundTokenUsageLog($log, $sessionId, $deletedByUserId);
                $refundedTokens += (int) ($log->tokens_used ?? 0);
                $refundedLogCount++;
                $log->delete();
            }

            LlmDebugLog::where('conversation_id', $conversation->id)->delete();
            $conversation->messages()->delete();
            $conversation->delete();

            if ($sessionId !== '') {
                try {
                    Cache::store('redis')->forget("widget_chat_ctx:{$sessionId}");
                    Cache::store('redis')->forget("widget_shopify_data:{$sessionId}");
                } catch (\Throwable $e) {
                    Log::warning('Failed to clear widget chat cache during conversation cleanup', [
                        'session_id' => $sessionId,
                        'organization_id' => $organizationId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'refunded_tokens' => $refundedTokens,
                'refunded_log_count' => $refundedLogCount,
            ];
        });
    }

    private function refundTokenUsageLog(TokenUsageLog $log, string $sessionId, ?int $deletedByUserId = null): void
    {
        $tokens = (int) ($log->tokens_used ?? 0);
        if ($tokens <= 0) {
            return;
        }

        if ($log->subscription_id) {
            $subscription = Subscription::lockForUpdate()->find($log->subscription_id);
            if ($subscription) {
                $current = (int) ($subscription->tokens_used_this_period ?? 0);
                $subscription->update([
                    'tokens_used_this_period' => max(0, $current - $tokens),
                ]);
            }
            return;
        }

        if ($log->user_id) {
            $userCredit = UserCredit::getOrCreateForUser($log->user_id);
            $userCredit->restoreCredits($tokens, 'Refund for deleted chat history', [
                'reference_id' => 'chat-delete:' . $log->id,
                'notes' => 'Chat history deleted from admin panel',
                'metadata' => [
                    'source' => 'chat_history_delete',
                    'session_id' => $sessionId,
                    'organization_id' => $log->organization_id,
                    'token_usage_log_id' => $log->id,
                    'deleted_by_user_id' => $deletedByUserId,
                    'endpoint_type' => $log->endpoint_type,
                ],
            ]);
        }
    }

    private function resolveTokenUsageLogs(ChatConversation $conversation)
    {
        $sessionId = (string) ($conversation->conversation_id ?? '');
        $organizationId = (int) $conversation->organization_id;

        $linked = TokenUsageLog::query()
            ->where('organization_id', $organizationId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->get();

        if ($linked->isNotEmpty()) {
            return $linked;
        }

        $aiMessages = $conversation->messages()
            ->whereIn('sender_type', ['ai', 'assistant'])
            ->orderBy('sent_at')
            ->get(['sent_at']);

        if ($aiMessages->isEmpty()) {
            return $linked;
        }

        $start = optional($aiMessages->first()->sent_at)->copy()?->subSeconds(45);
        $end = optional($aiMessages->last()->sent_at)->copy()?->addSeconds(45);

        if (!$start || !$end) {
            return $linked;
        }

        $fallback = TokenUsageLog::query()
            ->where('organization_id', $organizationId)
            ->whereNull('session_id')
            ->whereIn('endpoint_type', ['llm_chat_stream', 'llm_chat', 'faq_direct', 'faq_keyword'])
            ->whereBetween('used_at', [$start, $end])
            ->lockForUpdate()
            ->get();

        if ($fallback->count() > max(1, $aiMessages->count() + 1)) {
            return collect();
        }

        return $fallback;
    }
}