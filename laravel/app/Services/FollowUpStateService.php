<?php

namespace App\Services;

use App\Models\ChatConversation;
use Carbon\Carbon;

class FollowUpStateService
{
    public function getPendingState(?ChatConversation $conversation): ?array
    {
        if (!$conversation) {
            return null;
        }

        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $pending = $metadata['pending_follow_up'] ?? null;

        if (!is_array($pending)) {
            return null;
        }

        $expiresAt = (string) ($pending['expires_at'] ?? '');
        if ($expiresAt !== '') {
            try {
                if (now()->greaterThan(Carbon::parse($expiresAt))) {
                    return null;
                }
            } catch (\Throwable $e) {
                return null;
            }
        }

        return $pending;
    }

    public function buildPinnedFollowUpQuery(?array $pendingState, string $currentMessage): string
    {
        $message = trim($currentMessage);
        if (!is_array($pendingState)) {
            return $message;
        }

        $parts = [];

        $entity = trim((string) ($pendingState['entity'] ?? ''));
        if ($entity !== '') {
            $parts[] = $entity;
        }

        $topicHints = $pendingState['topic_hints'] ?? [];
        if (is_array($topicHints)) {
            foreach ($topicHints as $hint) {
                $hint = trim((string) $hint);
                if ($hint !== '') {
                    $parts[] = $hint;
                }
            }
        }

        $question = trim((string) ($pendingState['question'] ?? ''));
        if ($question !== '') {
            $parts[] = $question;
        }

        if ($message !== '') {
            $parts[] = $message;
        }

        $query = trim(implode(' ', array_values(array_unique(array_filter($parts)))));
        return $query !== '' ? $query : $message;
    }

    public function updatePendingState(ChatConversation $conversation, string $assistantResponse, array $contextPayloads = []): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = $this->extractPendingState($assistantResponse, $contextPayloads);

        if ($state === null) {
            unset($metadata['pending_follow_up']);
        } else {
            $metadata['pending_follow_up'] = $state;
        }

        $conversation->update([
            'metadata' => $metadata,
            'last_activity_at' => now(),
        ]);
    }

    private function extractPendingState(string $assistantResponse, array $contextPayloads = []): ?array
    {
        $question = $this->extractQuestionLine($assistantResponse);
        if ($question === '') {
            return null;
        }

        $entity = '';
        $topicHints = [];
        foreach ($contextPayloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $title = trim((string) ($payload['title'] ?? ''));
            if ($entity === '' && $title !== '') {
                $entity = $title;
            }

            $dataType = trim((string) ($payload['data_type'] ?? ''));
            if ($dataType !== '') {
                $topicHints[] = $dataType;
            }

            $category = trim((string) ($payload['category'] ?? ''));
            if ($category !== '') {
                $topicHints[] = $category;
            }
        }

        $topicHints = array_values(array_unique(array_filter(array_map(function ($item) {
            return trim((string) $item);
        }, $topicHints))));

        return [
            'question' => $question,
            'entity' => $entity,
            'topic_hints' => array_slice($topicHints, 0, 4),
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(20)->toIso8601String(),
        ];
    }

    private function extractQuestionLine(string $response): string
    {
        $trimmed = trim($response);
        if ($trimmed === '' || !str_contains($trimmed, '?')) {
            return '';
        }

        $lines = preg_split('/\r?\n/', $trimmed) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && str_contains($line, '?')) {
                return $line;
            }
        }

        if (preg_match('/([^?]{3,}\?)/', $trimmed, $matches)) {
            return trim((string) ($matches[1] ?? ''));
        }

        return '';
    }
}