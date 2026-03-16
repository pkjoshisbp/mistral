<?php

namespace App\Services;

use App\Models\ChatConversation;
use App\Models\CreditTransaction;
use App\Models\LlmDebugLog;
use App\Models\Subscription;
use App\Models\TokenUsageLog;
use App\Models\UserCredit;
use Illuminate\Support\Collection;
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
                $refundedAmount = $this->refundTokenUsageLog($log, $sessionId, $deletedByUserId);
                if ($refundedAmount <= 0) {
                    continue;
                }

                $refundedTokens += $refundedAmount;
                $refundedLogCount++;
            }

            LlmDebugLog::where('conversation_id', $conversation->id)->delete();
            $conversation->messages()->delete();
            $conversation->delete();

            Log::info('Chat conversation deleted', [
                'conversation_id' => $conversation->id,
                'session_id' => $sessionId,
                'organization_id' => $organizationId,
                'deleted_by_user_id' => $deletedByUserId,
                'refunded_tokens' => $refundedTokens,
                'refunded_log_count' => $refundedLogCount,
            ]);

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

    private function refundTokenUsageLog(TokenUsageLog $log, string $sessionId, ?int $deletedByUserId = null): int
    {
        $tokens = (int) ($log->tokens_used ?? 0);
        if ($tokens <= 0) {
            return 0;
        }

        if ($log->subscription_id) {
            $subscription = Subscription::lockForUpdate()->find($log->subscription_id);
            if (!$subscription) {
                return 0;
            }

            $current = (int) ($subscription->tokens_used_this_period ?? 0);
            $subscription->update([
                'tokens_used_this_period' => max(0, $current - $tokens),
            ]);

            return $tokens;
        }

        if (!$log->user_id) {
            return 0;
        }

        $refundReference = 'chat-delete-refund:' . $log->id;
        if (CreditTransaction::where('reference_id', $refundReference)->exists()) {
            return 0;
        }

        $usageTransaction = CreditTransaction::query()
            ->where('user_id', $log->user_id)
            ->where('type', 'debit')
            ->where('reference_id', 'token-usage:' . $log->id)
            ->lockForUpdate()
            ->first();

        if (!$usageTransaction) {
            Log::warning('Skipping chat-delete refund because no exact billed usage transaction was found', [
                'token_usage_log_id' => $log->id,
                'session_id' => $sessionId,
                'organization_id' => $log->organization_id,
                'user_id' => $log->user_id,
                'deleted_by_user_id' => $deletedByUserId,
            ]);
            return 0;
        }

        $refundAmount = (float) ($usageTransaction->amount ?? 0);
        if ($refundAmount <= 0) {
            return 0;
        }

        $userCredit = UserCredit::getOrCreateForUser($log->user_id);
        $userCredit->restoreCredits($refundAmount, 'Refund for deleted chat history', [
            'reference_id' => $refundReference,
            'notes' => 'Chat history deleted from admin panel',
            'metadata' => [
                'source' => 'chat_history_delete',
                'session_id' => $sessionId,
                'organization_id' => $log->organization_id,
                'token_usage_log_id' => $log->id,
                'deleted_by_user_id' => $deletedByUserId,
                'endpoint_type' => $log->endpoint_type,
                'usage_transaction_id' => $usageTransaction->id,
            ],
        ]);

        return (int) round($refundAmount);
    }

    private function resolveTokenUsageLogs(ChatConversation $conversation): Collection
    {
        $sessionId = (string) ($conversation->conversation_id ?? '');
        $organizationId = (int) $conversation->organization_id;

        if ($sessionId === '' || $organizationId <= 0) {
            return collect();
        }

        return TokenUsageLog::query()
            ->where('organization_id', $organizationId)
            ->where('session_id', $sessionId)
            ->lockForUpdate()
            ->get();
    }
}