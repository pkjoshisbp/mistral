<?php

namespace App\Services;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidgetSpamGuard
{
    public function inspect(Organization $organization, Request $request, string $sessionId, string $message): ?array
    {
        $settings = is_array($organization->settings ?? null) ? $organization->settings : [];

        if (array_key_exists('widget_spam_protection_enabled', $settings)
            && !(bool) $settings['widget_spam_protection_enabled']) {
            return null;
        }

        $message = trim($message);
        $maxChars = $this->boundedInt($settings['widget_max_message_chars'] ?? 4000, 500, 10000, 4000);
        if ($this->stringLength($message) > $maxChars) {
            return $this->block(422, "Message is too long. Please keep it under {$maxChars} characters.", 'message_too_long');
        }

        if ($this->looksLikeAbusivePayload($message)) {
            return $this->block(422, 'This message looks like spam. Please send a normal support question.', 'abusive_payload');
        }

        $sessionScope = $this->sessionScope($organization, $sessionId);
        $ipScope = $this->ipScope($organization, $request);

        $volumeChecks = [
            [$sessionScope, 'session_10m', $this->boundedInt($settings['widget_spam_session_messages_per_10_minutes'] ?? 20, 5, 200, 20), 600],
            [$sessionScope, 'session_1h', $this->boundedInt($settings['widget_spam_session_messages_per_hour'] ?? 60, 10, 500, 60), 3600],
            [$ipScope, 'ip_10m', $this->boundedInt($settings['widget_spam_ip_messages_per_10_minutes'] ?? 60, 10, 500, 60), 600],
            [$ipScope, 'ip_1h', $this->boundedInt($settings['widget_spam_ip_messages_per_hour'] ?? 180, 20, 1500, 180), 3600],
        ];

        foreach ($volumeChecks as [$scope, $name, $maxAttempts, $decaySeconds]) {
            $limited = $this->hitFixedWindow($scope, $name, $maxAttempts, $decaySeconds);
            if ($limited !== null) {
                $this->logBlocked($organization, $request, $sessionId, $limited['reason']);
                return $limited;
            }
        }

        $normalized = $this->normalizeMessage($message);
        if ($normalized !== '') {
            $duplicateLimit = $this->boundedInt($settings['widget_spam_duplicate_messages_per_5_minutes'] ?? 2, 1, 20, 2);
            $duplicate = $this->hitDuplicateLimit($sessionScope, $normalized, $duplicateLimit);
            if ($duplicate !== null) {
                $this->logBlocked($organization, $request, $sessionId, $duplicate['reason']);
                return $duplicate;
            }
        }

        $cooldownSeconds = $this->boundedInt($settings['widget_spam_min_seconds_between_messages'] ?? 2, 0, 15, 2);
        if ($cooldownSeconds > 0) {
            $cooldown = $this->checkAndMarkCooldown($sessionScope, $cooldownSeconds);
            if ($cooldown !== null) {
                $this->logBlocked($organization, $request, $sessionId, $cooldown['reason']);
                return $cooldown;
            }
        }

        return null;
    }

    private function hitFixedWindow(string $scope, string $name, int $maxAttempts, int $decaySeconds): ?array
    {
        if ($maxAttempts <= 0) {
            return null;
        }

        $key = "widget_spam:{$name}:{$scope}";
        $expiresKey = "{$key}:expires_at";
        $expiresAt = now()->addSeconds($decaySeconds);

        Cache::add($key, 0, $expiresAt);
        Cache::add($expiresKey, $expiresAt->timestamp, $expiresAt);

        $attempts = Cache::increment($key);
        if (!is_numeric($attempts)) {
            $attempts = (int) Cache::get($key, 1);
        }

        if ((int) $attempts <= $maxAttempts) {
            return null;
        }

        return $this->block(
            429,
            'Too many messages. Please wait a moment before sending another message.',
            "rate_limited_{$name}",
            $this->retryAfter($expiresKey, $decaySeconds)
        );
    }

    private function hitDuplicateLimit(string $scope, string $normalizedMessage, int $maxDuplicates): ?array
    {
        $key = 'widget_spam:duplicate:' . $scope . ':' . hash('sha256', $normalizedMessage);
        $expiresKey = "{$key}:expires_at";
        $expiresAt = now()->addMinutes(5);

        Cache::add($key, 0, $expiresAt);
        Cache::add($expiresKey, $expiresAt->timestamp, $expiresAt);

        $attempts = Cache::increment($key);
        if (!is_numeric($attempts)) {
            $attempts = (int) Cache::get($key, 1);
        }

        if ((int) $attempts <= $maxDuplicates) {
            return null;
        }

        return $this->block(
            429,
            'You already sent that message. Please wait a moment or ask a different question.',
            'duplicate_message',
            $this->retryAfter($expiresKey, 300)
        );
    }

    private function checkAndMarkCooldown(string $scope, int $cooldownSeconds): ?array
    {
        $key = "widget_spam:last_sent:{$scope}";
        $lastSentAt = (float) Cache::get($key, 0);
        $now = microtime(true);

        if ($lastSentAt > 0 && ($now - $lastSentAt) < $cooldownSeconds) {
            $retryAfter = max(1, (int) ceil($cooldownSeconds - ($now - $lastSentAt)));

            return $this->block(
                429,
                'Please wait a moment before sending another message.',
                'message_cooldown',
                $retryAfter
            );
        }

        Cache::put($key, $now, now()->addSeconds(max(30, $cooldownSeconds * 4)));

        return null;
    }

    private function looksLikeAbusivePayload(string $message): bool
    {
        if (preg_match('/(.)\1{79,}/s', $message)) {
            return true;
        }

        $urlCount = preg_match_all('/(?:https?:\/\/|www\.|[a-z0-9][a-z0-9.-]{1,}\.[a-z]{2,})(?:\/|\b)/i', $message);
        if ($urlCount !== false && $urlCount > 6) {
            return true;
        }

        if (substr_count($message, "\n") > 40 && strlen($message) > 1000) {
            return true;
        }

        $compact = preg_replace('/\s+/', '', strtolower($message)) ?? '';
        if (strlen($compact) > 200) {
            $unique = count(array_unique(str_split($compact)));
            if ($unique <= 5) {
                return true;
            }
        }

        return false;
    }

    private function normalizeMessage(string $message): string
    {
        $normalized = strtolower(trim($message));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/[^\pL\pN\s]+/u', '', $normalized) ?? $normalized;

        return trim($normalized);
    }

    private function sessionScope(Organization $organization, string $sessionId): string
    {
        $sessionKey = trim($sessionId) !== '' ? hash('sha256', $sessionId) : 'missing';

        return 'org:' . $organization->id . ':session:' . $sessionKey;
    }

    private function ipScope(Organization $organization, Request $request): string
    {
        $ip = trim((string) $request->ip()) ?: 'unknown';

        return 'org:' . $organization->id . ':ip:' . hash('sha256', $ip);
    }

    private function retryAfter(string $expiresKey, int $fallbackSeconds): int
    {
        $expiresAt = (int) Cache::get($expiresKey, 0);
        if ($expiresAt > 0) {
            return max(1, $expiresAt - now()->timestamp);
        }

        return max(1, $fallbackSeconds);
    }

    private function block(int $status, string $message, string $reason, ?int $retryAfter = null): array
    {
        $body = [
            'error' => $message,
            'message' => $message,
            'reason' => $reason,
        ];

        if ($retryAfter !== null) {
            $body['retry_after'] = $retryAfter;
        }

        return [
            'status' => $status,
            'body' => $body,
            'reason' => $reason,
        ];
    }

    private function boundedInt(mixed $value, int $min, int $max, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function stringLength(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }

    private function logBlocked(Organization $organization, Request $request, string $sessionId, string $reason): void
    {
        Log::warning('Widget spam guard blocked request', [
            'org_id' => $organization->id,
            'org_slug' => $organization->slug,
            'session_id_hash' => hash('sha256', $sessionId),
            'ip' => $request->ip(),
            'reason' => $reason,
        ]);
    }
}
