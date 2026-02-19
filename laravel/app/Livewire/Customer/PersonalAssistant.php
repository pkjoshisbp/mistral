<?php

namespace App\Livewire\Customer;

use App\Models\PersonalAssistantItem;
use App\Models\PersonalAssistantProfile;
use App\Models\AdminSetting;
use App\Services\AiAgentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class PersonalAssistant extends Component
{
    use WithFileUploads;

    public $audioFile;
    public $inputText = '';
    public $transcript = '';
    public $editedTranscript = '';
    public $assistantReply = '';
    public $audioReplyBase64 = '';
    public $audioReplyMimeType = 'audio/wav';

    public $preferredLanguage = 'en';
    public $onboardingAudioFile;
    public $onboardingTranscript = '';
    public $onboardingEditedTranscript = '';
    public $ttsProvider = 'xtts';
    public $customVocabularyText = '';
    public $correctionMapText = '';
    public $onboardingSample = '';

    public $history = [];
    public $savedItems = [];
    public $actionStatus = '';
    public $onboardingStatus = '';
    public $isProcessing = false;
    public $assistantPlanStatus = 'trial';
    public $trainingMode = 'sentences';
    public $selectedTrainingText = '';
    public $verifiedSampleCount = 0;
    public $onboardingCompleted = false;
    public $trainingRuns = [];
    public $minimumVerifiedSamples = 3;
    public $assistantTrialEndsAt = null;
    public $assistantTrialDaysLeft = 0;
    public $hasAssistantAccess = true;
    public $assistantPlanMessage = '';
    public $assistantMonthlyPrice = '12';

    protected $rules = [
        'preferredLanguage' => 'required|string|max:16',
        'ttsProvider' => 'required|string|max:32',
        'customVocabularyText' => 'nullable|string|max:4000',
        'correctionMapText' => 'nullable|string|max:4000',
    ];

    public function mount()
    {
        $this->loadProfile();
        $this->loadSavedItems();
        $this->setDefaultTrainingSample();
    }

    public function loadProfile(): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $profile = PersonalAssistantProfile::firstOrCreate(
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

        $this->preferredLanguage = $profile->preferred_language ?: 'en';
        $this->ttsProvider = $profile->tts_provider ?: 'xtts';
        $this->customVocabularyText = implode("\n", $profile->custom_vocabulary ?? []);

        $this->trainingRuns = array_slice($profile->settings['onboarding_runs'] ?? [], -40);
        $this->verifiedSampleCount = (int) ($profile->settings['verified_sample_count'] ?? 0);
        $this->onboardingCompleted = (bool) ($profile->settings['onboarding_completed'] ?? false);
        $savedMode = (string) ($profile->settings['training_mode'] ?? 'sentences');
        $this->trainingMode = in_array($savedMode, ['sentences', 'phrases', 'paragraphs'], true) ? $savedMode : 'sentences';
        $this->selectedTrainingText = (string) ($profile->settings['selected_training_text'] ?? '');
        $corrections = [];
        foreach (($profile->correction_map ?? []) as $source => $target) {
            $corrections[] = trim((string) $source) . ' => ' . trim((string) $target);
        }
        $this->correctionMapText = implode("\n", $corrections);
        $this->history = array_slice($profile->settings['recent_history'] ?? [], -20);
        $this->initializePlanStatus($profile);
    }

    public function setTrainingMode(string $mode): void
    {
        $this->trainingMode = in_array($mode, ['sentences', 'phrases', 'paragraphs'], true) ? $mode : 'sentences';
        $this->setDefaultTrainingSample();
        $this->persistTrainingUiState();
    }

    public function selectTrainingText(string $text): void
    {
        $this->selectedTrainingText = trim($text);
        $this->persistTrainingUiState();
    }

    public function saveProfile(): void
    {
        $this->validate();

        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $customVocabulary = collect(preg_split('/[\r\n,]+/', (string) $this->customVocabularyText) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values()
            ->all();

        $correctionMap = [];
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->correctionMapText) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, '=>')) {
                continue;
            }
            [$source, $target] = array_map('trim', explode('=>', $line, 2));
            if ($source !== '' && $target !== '') {
                $correctionMap[$source] = $target;
            }
        }

        $profile = PersonalAssistantProfile::firstOrNew([
            'user_id' => $user->id,
            'organization_id' => $organization?->id,
        ]);

        $profile->preferred_language = $this->preferredLanguage;
        $profile->tts_provider = $this->ttsProvider;
        $profile->custom_vocabulary = $customVocabulary;
        $profile->correction_map = $correctionMap;
        $profile->settings = array_merge($profile->settings ?? [], [
            'enable_voice_output' => true,
            'enable_learning' => true,
        ]);

        if (trim($this->onboardingSample) !== '') {
            $samples = $profile->training_samples ?? [];
            $samples[] = [
                'type' => 'free_speech',
                'text' => trim($this->onboardingSample),
                'captured_at' => now()->toISOString(),
            ];
            $profile->training_samples = array_slice($samples, -25);
        }

        $profile->save();

        session()->flash('success', 'Personal assistant profile saved successfully.');
    }

    public function transcribeVoice(AiAgentService $aiAgentService): void
    {
        $this->resetErrorBag();

        if (!$this->hasAssistantAccess) {
            $this->addError('audioFile', $this->assistantPlanMessage ?: 'Your personal assistant access is currently inactive.');
            return;
        }

        if (!$this->onboardingCompleted) {
            $this->addError('audioFile', 'Complete onboarding voice training first to unlock assistant console.');
            return;
        }

        if (!$this->audioFile) {
            $this->addError('audioFile', 'Please upload or record an audio clip first.');
            return;
        }

        $this->isProcessing = true;

        try {
            $path = $this->audioFile->storeAs('tmp/personal-assistant', Str::uuid() . '.' . $this->audioFile->getClientOriginalExtension(), 'local');
            $absolutePath = storage_path('app/' . $path);

            $profilePrompt = $this->buildTranscriptionPrompt();
            $result = $aiAgentService->transcribeAudio($absolutePath, [
                'language' => $this->preferredLanguage,
                'provider' => 'auto',
                'prompt' => $profilePrompt,
            ]);

            if (!$result || empty($result['text'])) {
                $this->addError('audioFile', 'Unable to transcribe audio right now. Please retry.');
                return;
            }

            $this->transcript = trim((string) $result['text']);
            $this->editedTranscript = $this->applyCorrections($this->transcript);
        } finally {
            if (!empty($path)) {
                Storage::disk('local')->delete($path);
            }
            $this->isProcessing = false;
        }
    }

    public function transcribeOnboardingSample(AiAgentService $aiAgentService): void
    {
        $this->resetErrorBag();
        $this->onboardingStatus = '';

        if (!$this->hasAssistantAccess) {
            $this->addError('onboardingAudioFile', $this->assistantPlanMessage ?: 'Your personal assistant access is currently inactive.');
            return;
        }

        if (!$this->onboardingAudioFile) {
            $this->addError('onboardingAudioFile', 'Record your voice for the selected text sample first.');
            return;
        }

        if (trim($this->selectedTrainingText) === '') {
            $this->addError('onboardingAudioFile', 'Please select a training sample first.');
            return;
        }

        $this->isProcessing = true;

        try {
            $path = $this->onboardingAudioFile->storeAs(
                'tmp/personal-assistant',
                Str::uuid() . '.' . $this->onboardingAudioFile->getClientOriginalExtension(),
                'local'
            );
            $absolutePath = storage_path('app/' . $path);

            $prompt = $this->buildOnboardingPrompt($this->selectedTrainingText);
            $result = $aiAgentService->transcribeAudio($absolutePath, [
                'language' => $this->preferredLanguage,
                'provider' => 'auto',
                'prompt' => $prompt,
            ]);

            if (!$result || empty($result['text'])) {
                $this->addError('onboardingAudioFile', 'Unable to transcribe this training sample right now. Please retry.');
                return;
            }

            $this->onboardingTranscript = trim((string) $result['text']);
            $this->onboardingEditedTranscript = $this->applyCorrections($this->onboardingTranscript);

            $normalizedExpected = $this->normalizeComparisonText((string) $this->selectedTrainingText);
            $normalizedRaw = $this->normalizeComparisonText((string) $this->onboardingTranscript);
            if ($normalizedExpected !== '' && $normalizedExpected === $normalizedRaw) {
                $this->saveOnboardingCorrection();
                $this->onboardingStatus = 'Exact match detected. Sample auto-saved and verified.';
            }
        } finally {
            if (!empty($path)) {
                Storage::disk('local')->delete($path);
            }
            $this->isProcessing = false;
        }
    }

    public function saveOnboardingCorrection(): void
    {
        $this->onboardingStatus = '';

        $sourceText = trim((string) $this->selectedTrainingText);
        $raw = trim((string) $this->onboardingTranscript);
        $corrected = trim((string) $this->onboardingEditedTranscript);

        if ($sourceText === '' || $raw === '' || $corrected === '') {
            $this->addError('onboardingAudioFile', 'Select sample, transcribe, then edit/correct before saving.');
            return;
        }

        $user = Auth::user();
        $organization = $user?->primaryOrganization();
        $profile = PersonalAssistantProfile::where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->first();

        if (!$profile) {
            $this->addError('onboardingAudioFile', 'Profile not found. Please reload the page.');
            return;
        }

        $settings = $profile->settings ?? [];
        $runs = $settings['onboarding_runs'] ?? [];
        $normalizedExpected = $this->normalizeComparisonText($sourceText);
        $normalizedCorrected = $this->normalizeComparisonText($corrected);
        $isMatch = $normalizedExpected !== '' && $normalizedExpected === $normalizedCorrected;

        $runs[] = [
            'mode' => $this->trainingMode,
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

        $corrections = $profile->correction_map ?? [];
        if ($raw !== $corrected) {
            $corrections[$raw] = $corrected;
            $profile->correction_map = $corrections;
            $this->correctionMapText = collect($corrections)
                ->map(fn ($target, $source) => trim((string) $source) . ' => ' . trim((string) $target))
                ->implode("\n");
        }

        $verifiedCount = count(array_filter($verified));
        $onboardingCompleted = $verifiedCount >= $this->minimumVerifiedSamples;

        $settings['onboarding_runs'] = $runs;
        $settings['verified_samples'] = $verified;
        $settings['verified_sample_count'] = $verifiedCount;
        $settings['onboarding_completed'] = $onboardingCompleted;
        $settings['training_mode'] = $this->trainingMode;
        $settings['selected_training_text'] = $this->selectedTrainingText;

        $profile->settings = $settings;
        $profile->save();

        $this->trainingRuns = array_slice($runs, -40);
        $this->verifiedSampleCount = $verifiedCount;
        $this->onboardingCompleted = $onboardingCompleted;

        $this->onboardingStatus = $isMatch
            ? 'Training saved. This sample is now verified.'
            : 'Training saved. Adjust and retry this sample until it matches exactly.';
    }

    public function processCommand(AiAgentService $aiAgentService): void
    {
        $this->resetErrorBag();
        $this->actionStatus = '';

        if (!$this->hasAssistantAccess) {
            $this->addError('inputText', $this->assistantPlanMessage ?: 'Your personal assistant access is currently inactive.');
            return;
        }

        if (!$this->onboardingCompleted) {
            $this->addError('inputText', 'Complete onboarding voice training first to unlock assistant commands.');
            return;
        }

        $text = trim($this->editedTranscript !== '' ? $this->editedTranscript : $this->inputText);
        if ($text === '') {
            $this->addError('inputText', 'Type a command or transcribe voice first.');
            return;
        }

        $this->isProcessing = true;

        try {
            $user = Auth::user();
            $organization = $user?->primaryOrganization();

            $context = collect($this->history)->take(-6)->map(function ($item) {
                return ($item['role'] ?? 'user') . ': ' . ($item['text'] ?? '');
            })->values()->all();

            $parsed = $aiAgentService->parseAssistantCommand($text, [
                'language' => $this->preferredLanguage,
                'context' => $context,
            ]);

            $result = is_array($parsed['result'] ?? null) ? $parsed['result'] : [];
            $execution = $this->executeParsedCommand($result, $text);

            $reply = trim((string) ($execution['reply'] ?? ($result['reply'] ?? 'I understood your request.')));
            if ($reply === '') {
                $reply = 'I understood your request. Please confirm if you want me to proceed.';
            }

            $this->assistantReply = $reply;
            $this->actionStatus = (string) ($execution['status'] ?? '');
            $this->history[] = ['role' => 'user', 'text' => $text, 'at' => now()->toDateTimeString()];
            $this->history[] = ['role' => 'assistant', 'text' => $reply, 'at' => now()->toDateTimeString(), 'intent' => $result['intent'] ?? 'unknown'];
            $this->history = array_slice($this->history, -20);

            $speech = null;
            try {
                $speech = $aiAgentService->synthesizeSpeech($reply, [
                    'provider' => $this->ttsProvider,
                    'language' => $this->preferredLanguage,
                ]);
            } catch (\Throwable $e) {
                $speech = null;
            }

            if ($speech && !empty($speech['audio_base64'])) {
                $this->audioReplyBase64 = $speech['audio_base64'];
                $this->audioReplyMimeType = $speech['mime_type'] ?? 'audio/wav';
            } else {
                $this->audioReplyBase64 = '';
                $this->audioReplyMimeType = 'audio/wav';
            }

            $this->persistLastUsed();
            $this->persistRecentHistory();
            $this->loadSavedItems();
        } catch (\Throwable $e) {
            $this->assistantReply = 'I could not process that command right now. Please try again.';
            $this->actionStatus = 'Error: ' . $e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    private function executeParsedCommand(array $result, string $rawText): array
    {
        $intent = strtolower((string) ($result['intent'] ?? 'unknown'));
        $action = strtolower((string) ($result['action'] ?? ''));
        $entities = is_array($result['entities'] ?? null) ? $result['entities'] : [];
        $needsConfirmation = (bool) ($result['needs_confirmation'] ?? true);

        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $content = trim((string) ($entities['content'] ?? $entities['text'] ?? $rawText));
        $title = trim((string) ($entities['title'] ?? $entities['subject'] ?? Str::limit($content, 80, '')));

        if (in_array($intent, ['notes', 'dictation'], true) || str_contains($action, 'note')) {
            $item = PersonalAssistantItem::create([
                'user_id' => $user->id,
                'organization_id' => $organization?->id,
                'type' => 'note',
                'title' => $title !== '' ? $title : 'Quick note',
                'content' => $content,
                'status' => 'saved',
                'meta' => ['source' => 'voice_assistant'],
            ]);

            return [
                'handled' => true,
                'status' => 'Saved note #' . $item->id,
                'reply' => 'Saved your note: ' . ($item->title ?: 'Quick note') . '.',
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
                'meta' => ['source' => 'voice_assistant'],
            ]);

            $dueText = $dueAt ? $dueAt->format('M d, Y H:i:s') : 'without a specific time';

            return [
                'handled' => true,
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
                'meta' => ['source' => 'voice_assistant'],
            ]);

            return [
                'handled' => true,
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

        if (in_array($intent, ['send_email', 'calendar', 'appointment'], true)) {
            return [
                'handled' => true,
                'status' => 'Parsed action (integration pending)',
                'reply' => 'I parsed your ' . $intent . ' request. Live integration for this action is the next step, so I saved nothing yet. Please confirm details and I will prepare it for execution.',
            ];
        }

        return [
            'handled' => false,
            'status' => 'No local action executed',
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
                    'source' => 'voice_assistant',
                    'missing' => 'recipient',
                ],
            ]);

            return [
                'handled' => true,
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
                    'source' => 'voice_assistant',
                    'missing' => 'body',
                ],
            ]);

            return [
                'handled' => true,
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
                    'source' => 'voice_assistant',
                ],
            ]);

            return [
                'handled' => true,
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
                    'source' => 'voice_assistant',
                    'sent_at' => now()->toISOString(),
                ],
            ]);

            return [
                'handled' => true,
                'status' => 'Email sent #' . $sent->id,
                'reply' => 'Email sent to ' . $recipient . ' successfully.',
            ];
        } catch (\Throwable $e) {
            Log::warning('Personal assistant email send failed', [
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
                    'source' => 'voice_assistant',
                    'error' => $e->getMessage(),
                ],
            ]);

            return [
                'handled' => true,
                'status' => 'Email send failed, draft #' . $failed->id . ' saved',
                'reply' => 'I could not send the email right now, but I saved it as a draft so you can retry.',
            ];
        }
    }

    private function extractEmailRecipient(array $entities): string
    {
        $candidate = $entities['to'] ?? $entities['recipient'] ?? $entities['email'] ?? '';

        if (is_array($candidate)) {
            $candidate = (string) ($candidate[0] ?? '');
        }

        return trim((string) $candidate);
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

        $results = PersonalAssistantItem::query()
            ->where('user_id', $userId)
            ->where('organization_id', $organizationId)
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', '%' . $query . '%')
                    ->orWhere('content', 'like', '%' . $query . '%');
            })
            ->latest()
            ->limit(5)
            ->get();

        if ($results->isEmpty()) {
            return 'I could not find anything matching "' . $query . '" in your saved items.';
        }

        $lines = $results->map(function ($item) {
            $label = strtoupper($item->type);
            return '- [' . $label . '] ' . ($item->title ?: Str::limit((string) $item->content, 60));
        })->implode("\n");

        return "I found these matches for \"{$query}\":\n" . $lines;
    }

    private function extractSearchQuery(string $text): string
    {
        $clean = preg_replace('/^(find|search|look up|lookup)\s+/i', '', trim($text));
        return trim((string) $clean);
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

    public function getCurrentTrainingSamplesProperty(): array
    {
        return match ($this->trainingMode) {
            'phrases' => $this->phraseSamples(),
            'paragraphs' => $this->paragraphSamples(),
            default => $this->sentenceSamples(),
        };
    }

    private function setDefaultTrainingSample(): void
    {
        $samples = $this->currentTrainingSamples;
        if (trim((string) $this->selectedTrainingText) === '' || !in_array($this->selectedTrainingText, $samples, true)) {
            $this->selectedTrainingText = $samples[0] ?? '';
        }
    }

    private function persistTrainingUiState(): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $profile = PersonalAssistantProfile::where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->first();

        if (!$profile) {
            return;
        }

        $settings = $profile->settings ?? [];
        $settings['training_mode'] = $this->trainingMode;
        $settings['selected_training_text'] = $this->selectedTrainingText;
        $profile->settings = $settings;
        $profile->save();
    }

    private function normalizeComparisonText(string $text): string
    {
        $normalized = mb_strtolower(trim($text));
        $normalized = preg_replace('/[^a-z0-9\s]/i', '', $normalized);
        $normalized = preg_replace('/\s+/', ' ', (string) $normalized);
        return trim((string) $normalized);
    }

    private function buildOnboardingPrompt(string $expectedText): string
    {
        $parts = [];

        $expectedText = trim($expectedText);
        if ($expectedText !== '') {
            $parts[] = 'Expected sample text: ' . $expectedText;
        }

        $vocabularyPrompt = $this->buildTranscriptionPrompt();
        if ($vocabularyPrompt !== '') {
            $parts[] = $vocabularyPrompt;
        }

        if (!empty($this->trainingRuns)) {
            $recentCorrections = collect($this->trainingRuns)
                ->reverse()
                ->take(5)
                ->map(function ($run) {
                    $from = trim((string) ($run['transcript'] ?? ''));
                    $to = trim((string) ($run['corrected'] ?? ''));
                    return ($from !== '' && $to !== '' && $from !== $to) ? ($from . ' => ' . $to) : null;
                })
                ->filter()
                ->values()
                ->all();

            if (!empty($recentCorrections)) {
                $parts[] = 'Recent corrections: ' . implode('; ', $recentCorrections);
            }
        }

        return implode(' | ', $parts);
    }

    private function buildTranscriptionPrompt(): string
    {
        $words = collect(preg_split('/[\r\n,]+/', (string) $this->customVocabularyText) ?: [])
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

    private function applyCorrections(string $text): string
    {
        $output = $text;
        $lines = preg_split('/\r\n|\r|\n/', (string) $this->correctionMapText) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !str_contains($line, '=>')) {
                continue;
            }

            [$source, $target] = array_map('trim', explode('=>', $line, 2));
            if ($source === '' || $target === '') {
                continue;
            }

            $output = str_ireplace($source, $target, $output);
        }

        return $output;
    }

    private function persistLastUsed(): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        PersonalAssistantProfile::where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->update(['last_used_at' => now()]);
    }

    private function persistRecentHistory(): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $profile = PersonalAssistantProfile::where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->first();

        if (!$profile) {
            return;
        }

        $settings = $profile->settings ?? [];
        $settings['recent_history'] = array_slice($this->history, -20);
        $profile->settings = $settings;
        $profile->save();
    }

    private function initializePlanStatus(PersonalAssistantProfile $profile): void
    {
        $settings = $profile->settings ?? [];
        $configuredTrialDays = (int) AdminSetting::get('assistant_trial_days', 14);
        $configuredMonthlyPrice = (string) AdminSetting::get('assistant_monthly_price_usd', '12');
        $this->assistantMonthlyPrice = $configuredMonthlyPrice;
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

        $this->assistantPlanStatus = (string) ($settings['assistant_plan_status'] ?? 'trial');
        $trialEnds = $settings['assistant_trial_ends_at'] ?? null;
        $trialEndAt = $trialEnds ? Carbon::parse($trialEnds) : null;

        $this->assistantTrialEndsAt = $trialEndAt ? $trialEndAt->toDateTimeString() : null;
        $this->assistantTrialDaysLeft = $trialEndAt ? max(0, now()->diffInDays($trialEndAt, false)) : 0;

        if ($this->assistantPlanStatus === 'active') {
            $this->hasAssistantAccess = true;
            $this->assistantPlanMessage = 'Personal Assistant subscription is active at $' . $this->assistantMonthlyPrice . '/month.';
            return;
        }

        if ($this->assistantPlanStatus === 'trial') {
            $trialActive = $trialEndAt && now()->lte($trialEndAt);
            $this->hasAssistantAccess = (bool) $trialActive;
            $this->assistantPlanMessage = $trialActive
                ? 'Free trial active. Days left: ' . $this->assistantTrialDaysLeft . '. Monthly price after trial: $' . $this->assistantMonthlyPrice . '/month.'
                : 'Free trial expired. Please activate monthly subscription ($' . $this->assistantMonthlyPrice . '/month) to continue using Personal Assistant.';
            return;
        }

        $this->hasAssistantAccess = false;
        $this->assistantPlanMessage = 'Personal Assistant is currently unavailable for your plan.';
    }

    private function loadSavedItems(): void
    {
        $user = Auth::user();
        $organization = $user?->primaryOrganization();

        $this->savedItems = PersonalAssistantItem::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization?->id)
            ->latest()
            ->limit(12)
            ->get()
            ->map(function (PersonalAssistantItem $item) {
                return [
                    'type' => $item->type,
                    'title' => $item->title,
                    'content' => $item->content,
                    'status' => $item->status,
                    'due_at' => $item->due_at?->toDateTimeString(),
                    'created_at' => $item->created_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.customer.personal-assistant')->layout('layouts.customer', [
            'title' => 'Personal Assistant',
        ]);
    }
}
