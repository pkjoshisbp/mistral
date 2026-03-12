<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdminSetting;
use App\Models\PersonalAssistantItem;
use App\Models\PersonalAssistantProfile;
use App\Models\User;
use App\Services\AiAgentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobilePersonalAssistantController extends Controller
{
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:120',
        ]);

        /** @var User|null $user */
        $user = User::where('email', $validated['email'])->first();
        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 422);
        }

        if (!$user->isCustomer() && !$user->isAdmin()) {
            return response()->json(['message' => 'Personal assistant mobile access is only available for customer users.'], 403);
        }

        $deviceName = trim((string) ($validated['device_name'] ?? 'mobile-app'));
        $token = $user->createToken('mobile-assistant-' . $deviceName)->plainTextToken;

        $organization = $user->primaryOrganization();
        $profile = $this->getOrCreateProfile($user);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'organization' => $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ] : null,
            ],
            'assistant_plan' => $this->buildAssistantPlanStatus($profile),
        ]);
    }

    public function logout(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->delete();
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization();
        $profile = $this->getOrCreateProfile($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'organization' => $organization ? [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug,
                ] : null,
            ],
            'assistant_plan' => $this->buildAssistantPlanStatus($profile),
            'profile' => [
                'preferred_language' => $profile->preferred_language,
                'tts_provider' => $profile->tts_provider,
                'custom_vocabulary' => $profile->custom_vocabulary ?? [],
                'correction_map' => $profile->correction_map ?? [],
                'verified_sample_count' => (int) data_get($profile->settings, 'verified_sample_count', 0),
                'onboarding_completed' => (bool) data_get($profile->settings, 'onboarding_completed', false),
                'last_used_at' => $profile->last_used_at?->toDateTimeString(),
            ],
        ]);
    }

    public function getSettings(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        return response()->json([
            'preferred_language' => $profile->preferred_language,
            'tts_provider' => $profile->tts_provider,
            'custom_vocabulary' => $profile->custom_vocabulary ?? [],
            'correction_map' => $profile->correction_map ?? [],
            'settings' => $profile->settings ?? [],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'preferred_language' => 'nullable|string|max:16',
            'tts_provider' => 'nullable|string|max:32',
            'custom_vocabulary' => 'nullable|array',
            'custom_vocabulary.*' => 'string|max:120',
            'correction_map' => 'nullable|array',
            'correction_map.*' => 'string|max:1000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        if (array_key_exists('preferred_language', $validated)) {
            $profile->preferred_language = $validated['preferred_language'] ?: 'en';
        }

        if (array_key_exists('tts_provider', $validated)) {
            $profile->tts_provider = $validated['tts_provider'] ?: 'xtts';
        }

        if (array_key_exists('custom_vocabulary', $validated)) {
            $profile->custom_vocabulary = collect($validated['custom_vocabulary'] ?? [])
                ->map(fn ($item) => trim((string) $item))
                ->filter(fn ($item) => $item !== '')
                ->unique()
                ->take(300)
                ->values()
                ->all();
        }

        if (array_key_exists('correction_map', $validated)) {
            $pairs = [];
            foreach (($validated['correction_map'] ?? []) as $from => $to) {
                $from = trim((string) $from);
                $to = trim((string) $to);
                if ($from !== '' && $to !== '') {
                    $pairs[$from] = $to;
                }
            }
            $profile->correction_map = array_slice($pairs, -300, null, true);
        }

        $profile->save();

        return response()->json(['message' => 'Settings updated successfully.']);
    }

    public function trainingSamples(Request $request)
    {
        $mode = (string) $request->query('mode', 'sentences');
        $mode = in_array($mode, ['sentences', 'phrases', 'paragraphs'], true) ? $mode : 'sentences';

        $samples = match ($mode) {
            'phrases' => $this->phraseSamples(),
            'paragraphs' => $this->paragraphSamples(),
            default => $this->sentenceSamples(),
        };

        return response()->json([
            'mode' => $mode,
            'samples' => $samples,
        ]);
    }

    public function saveTrainingCorrection(Request $request)
    {
        $validated = $request->validate([
            'sample_text' => 'required|string|max:5000',
            'transcript' => 'required|string|max:5000',
            'corrected' => 'required|string|max:5000',
            'mode' => 'nullable|string|in:sentences,phrases,paragraphs',
        ]);

        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        $sourceText = trim((string) $validated['sample_text']);
        $raw = trim((string) $validated['transcript']);
        $corrected = trim((string) $validated['corrected']);
        $mode = (string) ($validated['mode'] ?? 'sentences');

        $settings = is_array($profile->settings) ? $profile->settings : [];
        $runs = $settings['onboarding_runs'] ?? [];

        $normalizedExpected = $this->normalizeComparisonText($sourceText);
        $normalizedCorrected = $this->normalizeComparisonText($corrected);
        $isMatch = $normalizedExpected !== '' && $normalizedExpected === $normalizedCorrected;

        $runs[] = [
            'mode' => $mode,
            'sample_text' => $sourceText,
            'transcript' => $raw,
            'corrected' => $corrected,
            'matched' => $isMatch,
            'created_at' => now()->toISOString(),
        ];
        $runs = array_slice($runs, -200);

        $verified = $settings['verified_samples'] ?? [];
        if ($isMatch) {
            $verified[$sourceText] = true;
        }

        $corrections = is_array($profile->correction_map) ? $profile->correction_map : [];
        if ($raw !== $corrected) {
            $corrections[$raw] = $corrected;
            $profile->correction_map = array_slice($corrections, -300, null, true);
        }

        $verifiedCount = count(array_filter($verified));
        $minimumVerifiedSamples = 3;
        $onboardingCompleted = $verifiedCount >= $minimumVerifiedSamples;

        $settings['onboarding_runs'] = $runs;
        $settings['verified_samples'] = $verified;
        $settings['verified_sample_count'] = $verifiedCount;
        $settings['onboarding_completed'] = $onboardingCompleted;
        $settings['training_mode'] = $mode;
        $settings['selected_training_text'] = $sourceText;

        $profile->settings = $settings;
        $profile->save();

        return response()->json([
            'message' => $isMatch
                ? 'Training saved. This sample is now verified.'
                : 'Training saved. Retry this sample until it matches exactly.',
            'verified_sample_count' => $verifiedCount,
            'minimum_verified_samples' => $minimumVerifiedSamples,
            'onboarding_completed' => $onboardingCompleted,
        ]);
    }

    public function transcribe(Request $request, AiAgentService $aiAgentService)
    {
        $validated = $request->validate([
            'audio' => 'required|file|mimetypes:audio/mpeg,audio/mp4,audio/wav,audio/x-wav,audio/webm,audio/ogg|max:20480',
            'language' => 'nullable|string|max:16',
            'provider' => 'nullable|string|max:32',
            'prompt' => 'nullable|string|max:2000',
        ]);

        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        $plan = $this->buildAssistantPlanStatus($profile);
        if (!($plan['has_access'] ?? false)) {
            return response()->json(['message' => $plan['message'] ?? 'Personal assistant access is inactive.'], 403);
        }

        $path = null;
        try {
            $file = $request->file('audio');
            $path = $file->storeAs('tmp/personal-assistant', Str::uuid() . '.' . $file->getClientOriginalExtension(), 'local');
            $absolutePath = storage_path('app/' . $path);

            $prompt = trim((string) ($validated['prompt'] ?? ''));
            if ($prompt === '') {
                $prompt = $this->buildTranscriptionPrompt($profile);
            }

            $result = $aiAgentService->transcribeAudio($absolutePath, [
                'language' => $validated['language'] ?? $profile->preferred_language ?? 'en',
                'provider' => $validated['provider'] ?? 'auto',
                'prompt' => $prompt,
            ]);

            if (!$result || empty($result['text'])) {
                return response()->json(['message' => 'Unable to transcribe audio right now.'], 422);
            }

            $rawText = trim((string) $result['text']);
            $edited = $this->applyCorrectionsText($rawText, $profile->correction_map ?? []);

            return response()->json([
                'transcript' => $rawText,
                'edited_transcript' => $edited,
                'provider_used' => $result['provider_used'] ?? null,
                'meta' => $result['meta'] ?? null,
            ]);
        } finally {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }

    public function processCommand(Request $request, AiAgentService $aiAgentService)
    {
        $validated = $request->validate([
            'input_text' => 'nullable|string|max:5000',
            'transcript' => 'nullable|string|max:5000',
            'edited_transcript' => 'nullable|string|max:5000',
            'with_tts' => 'nullable|boolean',
        ]);

        /** @var User $user */
        $user = $request->user();
        $profile = $this->getOrCreateProfile($user);

        $plan = $this->buildAssistantPlanStatus($profile);
        if (!($plan['has_access'] ?? false)) {
            return response()->json(['message' => $plan['message'] ?? 'Personal assistant access is inactive.'], 403);
        }

        if (!(bool) data_get($profile->settings, 'onboarding_completed', false)) {
            return response()->json(['message' => 'Complete onboarding voice training first.'], 422);
        }

        $edited = trim((string) ($validated['edited_transcript'] ?? ''));
        $input = trim((string) ($validated['input_text'] ?? ''));
        $rawTranscript = trim((string) ($validated['transcript'] ?? ''));

        $text = $edited !== '' ? $edited : $input;
        if ($text === '') {
            return response()->json(['message' => 'Type command or provide edited transcript.'], 422);
        }

        $this->learnCorrectionFromEdit($profile, $rawTranscript, $edited);

        $recentHistory = array_slice((array) data_get($profile->settings, 'recent_history', []), -20);
        $context = collect($recentHistory)->take(-6)->map(function ($item) {
            return ($item['role'] ?? 'user') . ': ' . ($item['text'] ?? '');
        })->values()->all();

        $parsed = $aiAgentService->parseAssistantCommand($text, [
            'language' => $profile->preferred_language ?? 'en',
            'context' => $context,
        ]);

        $result = is_array($parsed['result'] ?? null) ? $parsed['result'] : [];
        $execution = $this->executeParsedCommand($result, $text, $user);

        $reply = trim((string) ($execution['reply'] ?? ($result['reply'] ?? 'I understood your request.')));
        if ($reply === '') {
            $reply = 'I understood your request. Please confirm if you want me to proceed.';
        }

        $history = array_slice($recentHistory, -20);
        $history[] = ['role' => 'user', 'text' => $text, 'at' => now()->toDateTimeString()];
        $history[] = ['role' => 'assistant', 'text' => $reply, 'at' => now()->toDateTimeString(), 'intent' => $result['intent'] ?? 'unknown'];
        $history = array_slice($history, -20);

        $settings = is_array($profile->settings) ? $profile->settings : [];
        $settings['recent_history'] = $history;
        $profile->settings = $settings;
        $profile->last_used_at = now();
        $profile->save();

        $response = [
            'reply' => $reply,
            'status' => (string) ($execution['status'] ?? ''),
            'intent' => (string) ($result['intent'] ?? 'unknown'),
            'saved_item_id' => $execution['item_id'] ?? null,
            'history' => array_slice($history, -10),
        ];

        if ((bool) ($validated['with_tts'] ?? false)) {
            try {
                $speech = $aiAgentService->synthesizeSpeech($reply, [
                    'provider' => $profile->tts_provider ?? 'xtts',
                    'language' => $profile->preferred_language ?? 'en',
                ]);
                if ($speech && !empty($speech['audio_base64'])) {
                    $response['audio'] = [
                        'audio_base64' => $speech['audio_base64'],
                        'mime_type' => $speech['mime_type'] ?? 'audio/wav',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Mobile TTS generation failed', ['error' => $e->getMessage()]);
            }
        }

        return response()->json($response);
    }

    public function listItems(Request $request)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization();

        $query = PersonalAssistantItem::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id);

        if ($request->filled('type')) {
            $query->where('type', (string) $request->query('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('q')) {
            $needle = trim((string) $request->query('q'));
            $query->where(function ($inner) use ($needle) {
                $inner->where('title', 'like', '%' . $needle . '%')
                    ->orWhere('content', 'like', '%' . $needle . '%');
            });
        }

        $perPage = min(100, max(10, (int) $request->query('per_page', 30)));
        $items = $query->latest()->paginate($perPage);

        return response()->json($items->through(function (PersonalAssistantItem $item) {
            return $this->transformItem($item);
        }));
    }

    public function createItem(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string|max:24',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:10000',
            'status' => 'nullable|string|max:24',
            'due_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:80',
        ]);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization();

        $item = PersonalAssistantItem::create([
            'user_id' => $user->id,
            'organization_id' => $organization?->id,
            'type' => trim((string) $validated['type']),
            'title' => trim((string) ($validated['title'] ?? '')),
            'content' => trim((string) ($validated['content'] ?? '')),
            'status' => trim((string) ($validated['status'] ?? 'pending')),
            'due_at' => isset($validated['due_at']) ? Carbon::parse($validated['due_at']) : null,
            'meta' => [
                'source' => 'mobile_app',
                'memory' => true,
                'keywords' => collect($validated['tags'] ?? [])->map(fn ($t) => mb_strtolower(trim((string) $t)))->filter()->unique()->values()->all(),
            ],
        ]);

        $this->syncPersonalItemToVector($item);

        return response()->json([
            'message' => 'Item created successfully.',
            'item' => $this->transformItem($item),
        ]);
    }

    public function updateItem(Request $request, int $id)
    {
        $validated = $request->validate([
            'type' => 'nullable|string|max:24',
            'title' => 'nullable|string|max:255',
            'content' => 'nullable|string|max:10000',
            'status' => 'nullable|string|max:24',
            'due_at' => 'nullable|date',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:80',
        ]);

        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization();

        $item = PersonalAssistantItem::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $meta = is_array($item->meta) ? $item->meta : [];
        if (array_key_exists('tags', $validated)) {
            $meta['keywords'] = collect($validated['tags'] ?? [])->map(fn ($t) => mb_strtolower(trim((string) $t)))->filter()->unique()->values()->all();
        }
        $meta['memory'] = true;
        $meta['updated_via'] = 'mobile_app';

        $item->update([
            'type' => array_key_exists('type', $validated) ? trim((string) $validated['type']) : $item->type,
            'title' => array_key_exists('title', $validated) ? trim((string) ($validated['title'] ?? '')) : $item->title,
            'content' => array_key_exists('content', $validated) ? trim((string) ($validated['content'] ?? '')) : $item->content,
            'status' => array_key_exists('status', $validated) ? trim((string) ($validated['status'] ?? 'pending')) : $item->status,
            'due_at' => array_key_exists('due_at', $validated)
                ? (isset($validated['due_at']) ? Carbon::parse($validated['due_at']) : null)
                : $item->due_at,
            'meta' => $meta,
        ]);

        $this->syncPersonalItemToVector($item->fresh());

        return response()->json([
            'message' => 'Item updated successfully.',
            'item' => $this->transformItem($item->fresh()),
        ]);
    }

    public function deleteItem(Request $request, int $id)
    {
        /** @var User $user */
        $user = $request->user();
        $organization = $user->primaryOrganization();

        $item = PersonalAssistantItem::query()
            ->where('id', $id)
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Item not found.'], 404);
        }

        $item->delete();
        $this->deletePersonalItemFromVector($user->id, $id);

        return response()->json(['message' => 'Item deleted successfully.']);
    }

    private function executeParsedCommand(array $result, string $rawText, User $user): array
    {
        $intent = strtolower((string) ($result['intent'] ?? 'unknown'));
        $action = strtolower((string) ($result['action'] ?? ''));
        $entities = is_array($result['entities'] ?? null) ? $result['entities'] : [];
        $needsConfirmation = (bool) ($result['needs_confirmation'] ?? true);

        $organization = $user->primaryOrganization();

        $content = trim((string) ($entities['content'] ?? $entities['text'] ?? $rawText));
        $title = trim((string) ($entities['title'] ?? $entities['subject'] ?? Str::limit($content, 80, '')));
        $isShoppingList = str_contains(strtolower($rawText), 'shopping list') || str_contains(strtolower($rawText), 'grocery');
        $memoryKeywords = $this->extractMemoryKeywords($content . ' ' . $title);

        if (in_array($intent, ['notes', 'dictation'], true) || str_contains($action, 'note')) {
            $item = PersonalAssistantItem::create([
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'type' => 'note',
                'title' => $title !== '' ? $title : ($isShoppingList ? 'Shopping list' : 'Quick note'),
                'content' => $content,
                'status' => 'saved',
                'meta' => [
                    'source' => 'voice_assistant_mobile',
                    'memory' => true,
                    'memory_type' => $isShoppingList ? 'shopping_list' : 'note',
                    'keywords' => $memoryKeywords,
                ],
            ]);
            $this->syncPersonalItemToVector($item);

            return [
                'handled' => true,
                'item_id' => $item->id,
                'status' => 'Saved note #' . $item->id,
                'reply' => $isShoppingList
                    ? 'Saved your shopping list: ' . ($item->title ?: 'Shopping list') . '.'
                    : 'Saved your note: ' . ($item->title ?: 'Quick note') . '.',
            ];
        }

        if ($intent === 'reminder' || str_contains($action, 'remind')) {
            $dueAt = $this->parseReminderDate($entities);
            $item = PersonalAssistantItem::create([
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'type' => 'reminder',
                'title' => $title !== '' ? $title : 'Reminder',
                'content' => $content,
                'due_at' => $dueAt,
                'status' => 'pending',
                'meta' => [
                    'source' => 'voice_assistant_mobile',
                    'memory' => true,
                    'memory_type' => 'reminder',
                    'keywords' => $memoryKeywords,
                ],
            ]);
            $this->syncPersonalItemToVector($item);

            $dueText = $dueAt ? $dueAt->format('M d, Y H:i:s') : 'without a specific time';

            return [
                'handled' => true,
                'item_id' => $item->id,
                'status' => 'Saved reminder #' . $item->id,
                'reply' => 'Reminder created ' . $dueText . ': ' . ($item->title ?: 'Reminder') . '.',
            ];
        }

        if ($intent === 'task' || str_contains($action, 'task')) {
            $dueAt = $this->parseReminderDate($entities);
            $item = PersonalAssistantItem::create([
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'type' => 'task',
                'title' => $title !== '' ? $title : 'Task',
                'content' => $content,
                'due_at' => $dueAt,
                'status' => 'pending',
                'meta' => [
                    'source' => 'voice_assistant_mobile',
                    'memory' => true,
                    'memory_type' => $isShoppingList ? 'shopping_list' : 'task',
                    'keywords' => $memoryKeywords,
                ],
            ]);
            $this->syncPersonalItemToVector($item);

            return [
                'handled' => true,
                'item_id' => $item->id,
                'status' => 'Saved task #' . $item->id,
                'reply' => 'Task added: ' . ($item->title ?: 'Task') . '.',
            ];
        }

        if ($intent === 'daily_brief') {
            return [
                'handled' => true,
                'status' => 'Generated daily brief',
                'reply' => $this->buildDailyBrief($user->id, $organization?->id),
            ];
        }

        if ($intent === 'quick_search') {
            $query = trim((string) ($entities['query'] ?? $entities['term'] ?? $this->extractSearchQuery($rawText)));

            return [
                'handled' => true,
                'status' => 'Quick search completed',
                'reply' => $this->buildQuickSearchReply($user->id, $organization?->id, $query),
            ];
        }

        if ($intent === 'send_email' || str_contains($action, 'email')) {
            return $this->executeEmailAction($entities, $rawText, $needsConfirmation, $user->id, $organization?->id);
        }

        return [
            'handled' => false,
            'status' => 'No local action executed',
            'reply' => 'I parsed your request but could not map it to a local action yet.',
        ];
    }

    private function executeEmailAction(array $entities, string $rawText, bool $needsConfirmation, int $userId, ?int $organizationId): array
    {
        $recipient = $this->extractEmailRecipient($entities);
        $subject = trim((string) ($entities['subject'] ?? $entities['title'] ?? 'Voice Assistant Draft'));
        $body = trim((string) ($entities['body'] ?? $entities['content'] ?? $rawText));

        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            $draft = PersonalAssistantItem::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'type' => 'email_draft',
                'title' => $subject !== '' ? $subject : 'Email draft',
                'content' => $body,
                'status' => 'draft',
                'meta' => [
                    'recipient' => $recipient,
                    'source' => 'voice_assistant_mobile',
                    'missing' => 'recipient',
                ],
            ]);
            $this->syncPersonalItemToVector($draft);

            return [
                'handled' => true,
                'item_id' => $draft->id,
                'status' => 'Saved email draft #' . $draft->id,
                'reply' => 'I prepared an email draft, but I need a valid recipient email address before sending.',
            ];
        }

        if ($body === '') {
            $draft = PersonalAssistantItem::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'type' => 'email_draft',
                'title' => $subject !== '' ? $subject : 'Email draft',
                'content' => '',
                'status' => 'draft',
                'meta' => [
                    'recipient' => $recipient,
                    'source' => 'voice_assistant_mobile',
                    'missing' => 'body',
                ],
            ]);
            $this->syncPersonalItemToVector($draft);

            return [
                'handled' => true,
                'item_id' => $draft->id,
                'status' => 'Saved email draft #' . $draft->id,
                'reply' => 'I captured the recipient. Please provide the email message content to send.',
            ];
        }

        if ($needsConfirmation) {
            $draft = PersonalAssistantItem::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'type' => 'email_draft',
                'title' => $subject,
                'content' => $body,
                'status' => 'pending_confirmation',
                'meta' => [
                    'recipient' => $recipient,
                    'source' => 'voice_assistant_mobile',
                ],
            ]);
            $this->syncPersonalItemToVector($draft);

            return [
                'handled' => true,
                'item_id' => $draft->id,
                'status' => 'Email draft #' . $draft->id . ' pending confirmation',
                'reply' => 'I drafted the email to ' . $recipient . '. Please confirm if I should send it now.',
            ];
        }

        try {
            Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient)
                    ->subject($subject !== '' ? $subject : 'Voice Assistant Message');
            });

            $sent = PersonalAssistantItem::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'type' => 'email',
                'title' => $subject,
                'content' => $body,
                'status' => 'sent',
                'meta' => [
                    'recipient' => $recipient,
                    'source' => 'voice_assistant_mobile',
                    'sent_at' => now()->toISOString(),
                ],
            ]);
            $this->syncPersonalItemToVector($sent);

            return [
                'handled' => true,
                'item_id' => $sent->id,
                'status' => 'Email sent #' . $sent->id,
                'reply' => 'Email sent to ' . $recipient . ' successfully.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Personal assistant mobile email send failed', [
                'recipient' => $recipient,
                'error' => $e->getMessage(),
            ]);

            $failed = PersonalAssistantItem::create([
                'user_id' => $userId,
                'organization_id' => $organizationId,
                'type' => 'email_draft',
                'title' => $subject,
                'content' => $body,
                'status' => 'send_failed',
                'meta' => [
                    'recipient' => $recipient,
                    'source' => 'voice_assistant_mobile',
                    'error' => $e->getMessage(),
                ],
            ]);
            $this->syncPersonalItemToVector($failed);

            return [
                'handled' => true,
                'item_id' => $failed->id,
                'status' => 'Email send failed, draft #' . $failed->id . ' saved',
                'reply' => 'I could not send the email right now, but I saved it as a draft so you can retry.',
            ];
        }
    }

    private function parseReminderDate(array $entities): ?Carbon
    {
        $candidates = [
            $entities['due_at'] ?? null,
            $entities['reminder_at'] ?? null,
            $entities['datetime'] ?? null,
            $entities['date_time'] ?? null,
            $entities['date'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            try {
                return Carbon::parse($candidate);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return null;
    }

    private function buildDailyBrief(int $userId, ?int $organizationId): string
    {
        $todayStart = now()->startOfDay();
        $nextWeek = now()->copy()->addDays(7)->endOfDay();

        $reminders = PersonalAssistantItem::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('type', 'reminder')
            ->where('status', 'pending')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [$todayStart, $nextWeek])
            ->orderBy('due_at')
            ->limit(3)
            ->get();

        $tasks = PersonalAssistantItem::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where('type', 'task')
            ->where('status', 'pending')
            ->orderByRaw('due_at IS NULL, due_at ASC')
            ->limit(3)
            ->get();

        $parts = [];
        if ($reminders->isNotEmpty()) {
            $reminderLines = $reminders->map(function ($item) {
                return '- ' . ($item->title ?: 'Reminder') . ' at ' . $item->due_at?->format('M d, H:i');
            })->implode("\n");
            $parts[] = "Upcoming reminders:\n" . $reminderLines;
        }

        if ($tasks->isNotEmpty()) {
            $taskLines = $tasks->map(function ($item) {
                return '- ' . ($item->title ?: 'Task');
            })->implode("\n");
            $parts[] = "Pending tasks:\n" . $taskLines;
        }

        if (empty($parts)) {
            return 'You have no pending reminders or tasks for the coming week.';
        }

        return "Here is your brief:\n\n" . implode("\n\n", $parts);
    }

    private function buildQuickSearchReply(int $userId, ?int $organizationId, string $query): string
    {
        if ($query === '') {
            return 'Tell me what to search in your notes, reminders, or tasks.';
        }

        $candidates = PersonalAssistantItem::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->latest()
            ->limit(150)
            ->get();

        $needle = mb_strtolower(trim($query));
        $results = $candidates->filter(function ($item) use ($needle) {
            $title = mb_strtolower((string) ($item->title ?? ''));
            $content = mb_strtolower((string) ($item->content ?? ''));
            $keywords = collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($word) => mb_strtolower(trim((string) $word)))
                ->filter()
                ->all();

            if (str_contains($title, $needle) || str_contains($content, $needle)) {
                return true;
            }

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && (str_contains($keyword, $needle) || str_contains($needle, $keyword))) {
                    return true;
                }
            }

            return false;
        })->take(5)->values();

        if ($results->isEmpty()) {
            return 'I could not find anything matching "' . $query . '" in your saved items.';
        }

        $lines = $results->map(function ($item) {
            $label = strtoupper((string) $item->type);
            return '- [' . $label . '] ' . ((string) ($item->title ?: Str::limit((string) $item->content, 60)));
        })->implode("\n");

        return "I found these matches for \"{$query}\":\n" . $lines;
    }

    private function extractSearchQuery(string $text): string
    {
        $clean = preg_replace('/^(find|search|look up|lookup)\s+/i', '', trim($text));
        return trim((string) $clean);
    }

    private function extractEmailRecipient(array $entities): string
    {
        $candidate = $entities['to'] ?? $entities['recipient'] ?? $entities['email'] ?? '';

        if (is_array($candidate)) {
            $candidate = (string) ($candidate[0] ?? '');
        }

        return trim((string) $candidate);
    }

    private function getOrCreateProfile(User $user): PersonalAssistantProfile
    {
        $organization = $user->primaryOrganization();

        return PersonalAssistantProfile::firstOrCreate(
            [
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
            ],
            [
                'preferred_language' => 'en',
                'tts_provider' => 'xtts',
                'custom_vocabulary' => [],
                'correction_map' => [],
                'training_samples' => [],
                'settings' => [
                    'enable_voice_output' => true,
                    'enable_learning' => true,
                ],
            ]
        );
    }

    private function buildAssistantPlanStatus(PersonalAssistantProfile $profile): array
    {
        $settings = is_array($profile->settings) ? $profile->settings : [];
        $configuredTrialDays = (int) AdminSetting::get('assistant_trial_days', 14);
        $configuredMonthlyPrice = (string) AdminSetting::get('assistant_monthly_price_usd', '12');

        $changed = false;
        if (empty($settings['assistant_plan_status'])) {
            $settings['assistant_plan_status'] = 'trial';
            $changed = true;
        }

        if (empty($settings['assistant_trial_started_at'])) {
            $settings['assistant_trial_started_at'] = now()->toISOString();
            $changed = true;
        }

        if (empty($settings['assistant_trial_ends_at'])) {
            $settings['assistant_trial_ends_at'] = now()->addDays($configuredTrialDays)->toISOString();
            $changed = true;
        }

        if ($changed) {
            $profile->settings = $settings;
            $profile->save();
        }

        $status = (string) ($settings['assistant_plan_status'] ?? 'trial');
        $trialEnds = $settings['assistant_trial_ends_at'] ?? null;
        $trialEndAt = $trialEnds ? Carbon::parse($trialEnds) : null;
        $trialDaysLeft = $trialEndAt ? max(0, now()->diffInDays($trialEndAt, false)) : 0;

        if ($status === 'active') {
            return [
                'status' => 'active',
                'has_access' => true,
                'message' => 'Personal Assistant subscription is active at $' . $configuredMonthlyPrice . '/month.',
                'monthly_price' => $configuredMonthlyPrice,
                'trial_ends_at' => $trialEndAt?->toDateTimeString(),
                'trial_days_left' => $trialDaysLeft,
            ];
        }

        if ($status === 'trial') {
            $trialActive = $trialEndAt && now()->lte($trialEndAt);

            return [
                'status' => 'trial',
                'has_access' => (bool) $trialActive,
                'message' => $trialActive
                    ? 'Free trial active. Days left: ' . $trialDaysLeft . '. Monthly price after trial: $' . $configuredMonthlyPrice . '/month.'
                    : 'Free trial expired. Please activate monthly subscription ($' . $configuredMonthlyPrice . '/month) to continue using Personal Assistant.',
                'monthly_price' => $configuredMonthlyPrice,
                'trial_ends_at' => $trialEndAt?->toDateTimeString(),
                'trial_days_left' => $trialDaysLeft,
            ];
        }

        return [
            'status' => $status,
            'has_access' => false,
            'message' => 'Personal Assistant is currently unavailable for your plan.',
            'monthly_price' => $configuredMonthlyPrice,
            'trial_ends_at' => $trialEndAt?->toDateTimeString(),
            'trial_days_left' => $trialDaysLeft,
        ];
    }

    private function buildTranscriptionPrompt(PersonalAssistantProfile $profile): string
    {
        $words = collect((array) ($profile->custom_vocabulary ?? []))
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->take(30)
            ->values()
            ->all();

        if (empty($words)) {
            return '';
        }

        return 'Prefer these business terms when transcribing: ' . implode(', ', $words);
    }

    private function applyCorrectionsText(string $text, array $correctionMap): string
    {
        $output = $text;
        foreach ($correctionMap as $source => $target) {
            $source = trim((string) $source);
            $target = trim((string) $target);
            if ($source === '' || $target === '') {
                continue;
            }
            $output = str_ireplace($source, $target, $output);
        }

        return $output;
    }

    private function normalizeComparisonText(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9\s]/i', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);
        return trim((string) $normalized);
    }

    private function learnCorrectionFromEdit(PersonalAssistantProfile $profile, string $raw, string $edited): void
    {
        $raw = trim($raw);
        $edited = trim($edited);

        if ($raw === '' || $edited === '' || $raw === $edited) {
            return;
        }

        if ($this->normalizeComparisonText($raw) === $this->normalizeComparisonText($edited)) {
            return;
        }

        $corrections = is_array($profile->correction_map) ? $profile->correction_map : [];
        $settings = is_array($profile->settings) ? $profile->settings : [];
        $history = is_array($settings['auto_correction_pairs'] ?? null) ? $settings['auto_correction_pairs'] : [];

        $corrections[$raw] = $edited;
        $history[] = [
            'from' => $raw,
            'to' => $edited,
            'at' => now()->toISOString(),
            'source' => 'mobile_command_console',
        ];

        if (count($corrections) > 300) {
            $corrections = array_slice($corrections, -300, null, true);
        }

        $settings['auto_correction_pairs'] = array_slice($history, -300);

        $profile->correction_map = $corrections;
        $profile->settings = $settings;
        $profile->save();
    }

    private function extractMemoryKeywords(string $text): array
    {
        $hashtags = [];
        preg_match_all('/#([\p{L}\p{N}_-]{2,40})/u', $text, $matches);
        if (!empty($matches[1])) {
            $hashtags = collect($matches[1])
                ->map(fn ($tag) => mb_strtolower(trim((string) $tag)))
                ->filter(fn ($tag) => $tag !== '')
                ->unique()
                ->values()
                ->all();
        }

        $words = collect(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [])
            ->map(fn ($word) => trim((string) $word))
            ->filter(fn ($word) => mb_strlen($word) >= 3)
            ->reject(fn ($word) => in_array($word, [
                'the', 'and', 'for', 'with', 'from', 'that', 'this', 'your', 'you', 'are', 'was', 'were',
                'have', 'has', 'had', 'will', 'would', 'should', 'can', 'could', 'not', 'but', 'about',
                'into', 'onto', 'over', 'under', 'after', 'before', 'tomorrow', 'today', 'task', 'note',
                'reminder', 'list', 'shopping', 'grocery',
            ], true))
            ->unique()
            ->take(12)
            ->values()
            ->all();

        return array_values(array_unique(array_merge($hashtags, $words)));
    }

    private function syncPersonalItemToVector(PersonalAssistantItem $item): void
    {
        try {
            /** @var AiAgentService $aiAgentService */
            $aiAgentService = app(AiAgentService::class);
            $collectionName = $this->ensureUserMemoryCollection($aiAgentService, (int) $item->user_id);

            $keywords = collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($word) => trim((string) $word))
                ->filter(fn ($word) => $word !== '')
                ->values()
                ->all();

            $contentForEmbedding = implode("\n", array_filter([
                'Type: ' . (string) $item->type,
                'Title: ' . (string) ($item->title ?? ''),
                'Content: ' . (string) ($item->content ?? ''),
                !empty($keywords) ? ('Keywords: ' . implode(', ', $keywords)) : '',
                $item->due_at ? ('Due At: ' . $item->due_at->toDateTimeString()) : '',
                'Status: ' . (string) ($item->status ?? 'pending'),
            ]));

            $embedding = $aiAgentService->embed($contentForEmbedding);
            if (!$embedding || !is_array($embedding)) {
                return;
            }

            $payload = [
                'item_id' => 'pa_item_' . $item->id,
                'user_id' => (int) $item->user_id,
                'organization_id' => $item->organization_id,
                'type' => (string) $item->type,
                'title' => (string) ($item->title ?? ''),
                'content' => (string) ($item->content ?? ''),
                'status' => (string) ($item->status ?? 'pending'),
                'due_at' => $item->due_at?->toDateTimeString(),
                'keywords' => $keywords,
                'source' => 'personal_assistant_mobile',
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ];

            $aiAgentService->addToQdrant($collectionName, $embedding, $payload, 9000000 + (int) $item->id);
        } catch (\Throwable $e) {
            Log::warning('Personal memory sync to Qdrant failed', [
                'item_id' => $item->id,
                'user_id' => $item->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function deletePersonalItemFromVector(int $userId, int $itemId): void
    {
        try {
            /** @var AiAgentService $aiAgentService */
            $aiAgentService = app(AiAgentService::class);
            $collectionName = $this->buildUserMemoryCollectionName($userId);
            $aiAgentService->deleteDataFromQdrant($collectionName, ['pa_item_' . $itemId]);
        } catch (\Throwable $e) {
            Log::warning('Personal memory delete from Qdrant failed', [
                'item_id' => $itemId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function ensureUserMemoryCollection(AiAgentService $aiAgentService, int $userId): string
    {
        $collectionName = $this->buildUserMemoryCollectionName($userId);

        try {
            if (!$aiAgentService->collectionExists($collectionName)) {
                $aiAgentService->createCollection($collectionName, 768);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to ensure user memory collection', [
                'collection' => $collectionName,
                'error' => $e->getMessage(),
            ]);
        }

        return $collectionName;
    }

    private function buildUserMemoryCollectionName(int $userId): string
    {
        return 'pa_user_' . $userId;
    }

    private function transformItem(PersonalAssistantItem $item): array
    {
        return [
            'id' => $item->id,
            'type' => $item->type,
            'title' => $item->title,
            'content' => $item->content,
            'status' => $item->status,
            'due_at' => $item->due_at?->toDateTimeString(),
            'created_at' => $item->created_at?->toDateTimeString(),
            'updated_at' => $item->updated_at?->toDateTimeString(),
            'tags' => collect((array) data_get($item->meta, 'keywords', []))
                ->map(fn ($tag) => trim((string) $tag))
                ->filter(fn ($tag) => $tag !== '')
                ->values()
                ->all(),
            'meta' => $item->meta,
        ];
    }

    private function sentenceSamples(): array
    {
        return [
            'Please schedule a reminder for tomorrow at ten in the morning.',
            'Send an update email to our client about the pending proposal.',
            'Add a task to review invoices before Friday evening.',
            'Create a note that our monthly plan starts at twelve dollars.',
            'Find my recent notes about the diagnostics clinic project.',
        ];
    }

    private function phraseSamples(): array
    {
        return [
            'follow up',
            'calendar invite',
            'client proposal',
            'payment reminder',
            'monthly subscription',
            'diagnostic package',
            'voice assistant',
            'appointment booking',
        ];
    }

    private function paragraphSamples(): array
    {
        return [
            'Good morning team. Today we need to finalize the diagnostics package pricing, update the FAQ responses, and send confirmation emails to all pending clients before five PM.',
            'Please schedule a reminder for tomorrow at ten AM to review monthly subscriptions, verify payment follow-ups, and prepare the weekly report for management.',
            'Our personal assistant should capture voice commands clearly, transcribe them accurately, and help users create notes, reminders, and tasks without friction.',
            'When a customer asks about available plans, we should explain the free trial first, then present the monthly subscription with clear pricing and next steps.',
            'For support quality, we must validate each onboarding sample, apply user corrections, and continuously improve recognition for accent, phrasing, and speaking style.',
        ];
    }
}
