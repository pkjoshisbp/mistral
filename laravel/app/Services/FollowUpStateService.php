<?php

namespace App\Services;

use App\Models\ChatConversation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FollowUpStateService
{
    public function __construct(private AiAgentService $aiAgent)
    {
    }

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
            $addedHints = 0;
            foreach ($topicHints as $hint) {
                $hint = trim((string) $hint);
                if ($hint !== '' && $this->isRetrievalSafeHint($hint)) {
                    $parts[] = $hint;
                    $addedHints++;
                    if ($addedHints >= 2) {
                        break;
                    }
                }
            }
        }

        if ($message !== '') {
            $parts[] = $message;
        }

        $query = trim(implode(' ', array_values(array_unique(array_filter($parts)))));
        $query = preg_replace('/\s+/', ' ', (string) $query) ?? '';

        if (mb_strlen($query) > 220) {
            $fallbackParts = [];
            if ($entity !== '' && $this->isRetrievalSafeHint($entity)) {
                $fallbackParts[] = $entity;
            }
            if ($message !== '') {
                $fallbackParts[] = $message;
            }
            $query = trim(implode(' ', $fallbackParts));
        }

        return $query !== '' ? $query : $message;
    }

    private function isRetrievalSafeHint(string $hint): bool
    {
        $normalized = trim((string) preg_replace('/\s+/', ' ', $hint));
        if ($normalized === '' || mb_strlen($normalized) > 64) {
            return false;
        }

        if (str_contains($normalized, ',') || str_contains($normalized, '|')) {
            return false;
        }

        if (substr_count($normalized, '/') >= 2 || substr_count($normalized, '\\') >= 2) {
            return false;
        }

        if (preg_match('/\b(default\s+category|show\s+by\s+room|room\s+by\s+view|expand)\b/i', $normalized)) {
            return false;
        }

        return true;
    }

    public function updatePendingState(ChatConversation $conversation, string $assistantResponse, array $contextPayloads = [], ?array $providedState = null): void
    {
        $metadata = is_array($conversation->metadata) ? $conversation->metadata : [];
        $state = null;
        if (is_array($providedState) && !empty($providedState)) {
            $state = $this->normalizeProvidedState($providedState, $assistantResponse, $contextPayloads);
        }

        if ($state === null) {
            $state = $this->extractPendingState($assistantResponse, $contextPayloads, (int) $conversation->organization_id);
        }

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

    private function normalizeProvidedState(array $providedState, string $assistantResponse, array $contextPayloads): ?array
    {
        $question = $this->extractQuestionLine($assistantResponse);
        if ($question === '') {
            return null;
        }

        $fallbackEntity = '';
        $fallbackTopicHints = [];
        foreach ($contextPayloads as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $title = trim((string) ($payload['title'] ?? ''));
            if ($fallbackEntity === '' && $title !== '' && !str_contains($title, '?')) {
                $fallbackEntity = $title;
            }

            $dataType = trim((string) ($payload['data_type'] ?? ''));
            if ($dataType !== '') {
                $fallbackTopicHints[] = $dataType;
            }

            $category = trim((string) ($payload['category'] ?? ''));
            if ($category !== '') {
                $fallbackTopicHints[] = $category;
            }
        }

        $entity = trim((string) ($providedState['entity'] ?? ''));
        if ($entity === '' || str_contains($entity, '?')) {
            $entity = $fallbackEntity;
        }

        $topicsCovered = $this->normalizeStringList($providedState['topics_covered'] ?? []);
        $followUpRaw = is_array($providedState['follow_up'] ?? null) ? $providedState['follow_up'] : [];
        $followUpType = $this->normalizeFollowUpType((string) ($followUpRaw['type'] ?? ''), $question);
        $followUpTopics = $this->normalizeFollowUpTopics($followUpRaw['topic'] ?? []);

        $topicHints = array_values(array_unique(array_filter(array_merge(
            $fallbackTopicHints,
            $topicsCovered,
            $followUpTopics
        ))));

        $state = [
            'question' => $question,
            'entity' => $entity,
            'topic_hints' => array_slice($topicHints, 0, 4),
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(20)->toIso8601String(),
        ];

        if (!empty($topicsCovered)) {
            $state['topics_covered'] = array_slice($topicsCovered, 0, 6);
        }

        if ($followUpType !== '' || !empty($followUpTopics)) {
            $state['follow_up'] = [
                'type' => $followUpType !== '' ? $followUpType : 'expand',
                'topic' => $followUpTopics,
            ];
        }

        return $state;
    }

    private function extractPendingState(string $assistantResponse, array $contextPayloads = [], ?int $organizationId = null): ?array
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
            if ($entity === '' && $title !== '' && !str_contains($title, '?')) {
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

        $structured = $this->extractStructuredStateViaLlm(
            $assistantResponse,
            $question,
            $entity,
            $topicHints,
            $contextPayloads,
            $organizationId
        );

        $structuredEntity = trim((string) ($structured['entity'] ?? ''));
        $invalidEntityValues = ['fallback_entity', 'unknown', 'n/a', 'na', 'null', 'none'];
        if ($structuredEntity !== ''
            && !in_array(strtolower($structuredEntity), $invalidEntityValues, true)
            && !str_contains($structuredEntity, '?')) {
            $entity = $structuredEntity;
        }

        $structuredTopicsCovered = $this->normalizeStringList($structured['topics_covered'] ?? []);

        $structuredFollowUp = null;
        if (is_array($structured['follow_up'] ?? null)) {
            $followUpType = $this->normalizeFollowUpType((string) ($structured['follow_up']['type'] ?? ''), $question);
            $followUpTopics = $this->normalizeFollowUpTopics($structured['follow_up']['topic'] ?? []);

            if ($followUpType !== '' || !empty($followUpTopics)) {
                $structuredFollowUp = [
                    'type' => $followUpType,
                    'topic' => $followUpTopics,
                ];

                if (!empty($followUpTopics)) {
                    $topicHints = array_values(array_unique(array_merge($topicHints, $followUpTopics)));
                }
            }
        }

        $state = [
            'question' => $question,
            'entity' => $entity,
            'topic_hints' => array_slice($topicHints, 0, 4),
            'created_at' => now()->toIso8601String(),
            'expires_at' => now()->addMinutes(20)->toIso8601String(),
        ];

        if (!empty($structuredTopicsCovered)) {
            $state['topics_covered'] = array_slice($structuredTopicsCovered, 0, 6);
        }

        if ($structuredFollowUp !== null) {
            $state['follow_up'] = $structuredFollowUp;
        }

        return $state;
    }

    private function extractStructuredStateViaLlm(
        string $assistantResponse,
        string $question,
        string $fallbackEntity,
        array $fallbackTopicHints,
        array $contextPayloads,
        ?int $organizationId = null
    ): array {
        $contextHints = [];
        foreach (array_slice($contextPayloads, 0, 6) as $payload) {
            if (!is_array($payload)) {
                continue;
            }

            $title = trim((string) ($payload['title'] ?? ''));
            $dataType = trim((string) ($payload['data_type'] ?? ''));
            $category = trim((string) ($payload['category'] ?? ''));

            $line = implode(' | ', array_filter([$title, $dataType, $category], fn ($v) => $v !== ''));
            if ($line !== '') {
                $contextHints[] = $line;
            }
        }

        $systemPrompt = "Extract follow-up intent from an assistant response. Return ONLY valid JSON with this schema: "
            . '{"entity":"string","topics_covered":["string"],"follow_up":{"type":"string","topic":["string"]}}' . "\n"
            . "Rules: use short lowercase topics, use empty arrays when unknown, and set follow_up to null when there is no follow-up question.";

        $userPrompt = "assistant_response:\n{$assistantResponse}\n\n"
            . "question_line:\n{$question}\n\n"
            . "fallback_entity:\n{$fallbackEntity}\n\n"
            . "fallback_topic_hints:\n" . implode(', ', $fallbackTopicHints) . "\n\n"
            . "context_hints:\n" . implode("\n", $contextHints);

        try {
            $model = $this->aiAgent->getLlamaModelForOrganization($organizationId ?? null);
            $response = $this->aiAgent->smartLlmChat(
                [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                $model,
                null,
                $organizationId,
                [
                    'num_predict' => 180,
                    'temperature' => 0.0,
                    'use_vastai' => true,
                ]
            );

            $raw = trim((string) ($response['message']['content'] ?? ''));
            if ($raw === '') {
                return [];
            }

            if (preg_match('/\{.*\}/s', $raw, $matches)) {
                $raw = $matches[0];
            }

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }

            return $decoded;
        } catch (\Throwable $e) {
            Log::debug('Structured follow-up extraction failed', [
                'error' => $e->getMessage(),
                'organization_id' => $organizationId,
            ]);

            return [];
        }
    }

    private function normalizeStringList($value): array
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $normalized = array_map(function ($item) {
            return trim((string) $item);
        }, $value);

        return array_values(array_unique(array_filter($normalized, fn ($item) => $item !== '')));
    }

    private function normalizeFollowUpTopics($value): array
    {
        $topics = $this->normalizeStringList($value);

        $topics = array_values(array_filter($topics, function ($topic) {
            return !str_contains($topic, '?') && mb_strlen($topic) <= 40;
        }));

        return array_map(function ($topic) {
            $topic = strtolower(trim($topic));
            return (string) preg_replace('/\s+/', '_', $topic);
        }, $topics);
    }

    private function normalizeFollowUpType(string $type, string $question): string
    {
        $normalized = strtolower(trim($type));
        $allowed = ['expand', 'clarify', 'compare', 'confirm', 'choose', 'next_step'];

        if (in_array($normalized, $allowed, true)) {
            return $normalized;
        }

        $questionLower = strtolower($question);
        if (preg_match('/\b(which|what|specify|tell me more|more about|want to know)\b/i', $questionLower)) {
            return 'clarify';
        }
        if (preg_match('/\b(compare|difference|vs|versus)\b/i', $questionLower)) {
            return 'compare';
        }

        return 'expand';
    }

    private function extractQuestionLine(string $response): string
    {
        $trimmed = trim($response);
        if ($trimmed === '' || !str_contains($trimmed, '?')) {
            return '';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $trimmed) ?: [];
        $questionSentences = array_values(array_filter(array_map('trim', $sentences), function ($sentence) {
            return $sentence !== '' && str_contains($sentence, '?');
        }));
        if (!empty($questionSentences)) {
            $lastQuestionSentence = trim((string) end($questionSentences));
            if ($lastQuestionSentence !== '') {
                return $lastQuestionSentence;
            }
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