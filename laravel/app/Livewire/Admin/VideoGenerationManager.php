<?php

namespace App\Livewire\Admin;

use App\Models\Organization;
use App\Models\VideoGenerationJob;
use App\Services\VideoGenerationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class VideoGenerationManager extends Component
{
    use WithFileUploads;

    public $selectedOrganization = '';
    public $title = '';
    public $globalPrompt = '';
    public $language = 'en';
    public $speaker = '';
    // Avatar settings (global — applied to every scene)
    public $avatarId       = '';             // '' = none | f1..f4 | m1..m4 | custom
    public $avatarPosition = 'bottom-right'; // bottom-right | bottom-left | bottom-center | top-right | top-left | center-right
    public $avatarSize     = 'medium';       // small | medium | large
    public $avatarShape    = 'circle';       // circle | rounded | rectangle
    public $avatarCustomUrl = '';            // URL when avatarId == 'custom'
    public $aspectRatio = '16:9';
    public $outputQuality = 'hd';
    public array $scenes = [];
    public array $referenceImages = [];
    public array $comfyuiStatus = [];
    public ?int $previewJobId = null;
    public string $storyboardText = '';
    public bool $showPastePanel = true;
    public string $lipsyncMode = 'unknown';
    public bool $lipsyncEnabled = false;
    public string $lipsyncUrl = '';

    public function mount(): void
    {
        $this->resetComposer();
        $this->refreshComfyuiStatus();
        $this->refreshAvatarCatalogStatus();
    }

    protected function rules(): array
    {
        return [
            'selectedOrganization' => 'required|exists:organizations,id',
            'title' => 'required|string|min:3|max:120',
            'globalPrompt' => 'nullable|string|max:3000',
            'language' => 'required|string|max:10',
            'speaker'       => 'nullable|string|max:80',
            'avatarId'      => 'nullable|string|max:20',
            'avatarPosition'=> 'nullable|string|in:bottom-right,bottom-left,bottom-center,top-right,top-left,center-right,center-left',
            'avatarSize'    => 'nullable|string|in:small,medium,large',
            'avatarShape'   => 'nullable|string|in:circle,rounded,rectangle',
            'avatarCustomUrl' => 'nullable|url|max:1000',
            'aspectRatio' => 'required|in:16:9,9:16,1:1',
            'outputQuality' => 'required|in:standard,hd,fullhd',
            'scenes.*.title' => 'nullable|string|max:80',
            'scenes.*.input_mode' => 'nullable|string|in:static,text,image,both',
            'scenes.*.video_mode' => 'nullable|string|in:animate,preserve',
            'scenes.*.prompt' => 'nullable|string|max:2000',
            'scenes.*.voiceover_text' => 'nullable|string|max:3000',
            'scenes.*.duration_seconds' => 'required|integer|min:4|max:45',
            'referenceImages.*' => 'nullable|image|max:10240',
            'scenes.*.reference_image_urls_text' => 'nullable|string|max:5000',
        ];
    }

    public function getOrganizationsProperty()
    {
        return Organization::orderBy('name')->get();
    }

    public function getJobsProperty()
    {
        return VideoGenerationJob::query()
            ->with(['organization', 'creator'])
            ->latest()
            ->limit(20)
            ->get();
    }

    public function addScene(): void
    {
        if (count($this->scenes) >= 12) {
            session()->flash('error', 'Limit the storyboard to 12 scenes per video.');
            return;
        }

        $this->scenes[] = $this->makeScene(count($this->scenes) + 1);
    }

    public function removeScene(int $index): void
    {
        if (count($this->scenes) <= 1) {
            session()->flash('error', 'At least one scene is required.');
            return;
        }

        unset($this->scenes[$index], $this->referenceImages[$index]);
        $this->scenes = array_values($this->scenes);
        $this->referenceImages = array_values($this->referenceImages);
    }

    public function resetComposer(): void
    {
        $this->title = '';
        $this->globalPrompt = '';
        $this->language = 'en';
        $this->speaker = '';
        $this->avatarId        = '';
        $this->avatarPosition  = 'bottom-right';
        $this->avatarSize      = 'medium';
        $this->avatarShape     = 'circle';
        $this->avatarCustomUrl = '';
        $this->aspectRatio = '16:9';
        $this->outputQuality = 'hd';
        $this->referenceImages = [];
        $this->scenes = [
            $this->makeScene(1),
            $this->makeScene(2),
            $this->makeScene(3),
        ];
    }

    public function submit(): void
    {
        $this->validate();

        $normalizedScenes = [];
        $totalDuration = 0;

        foreach ($this->scenes as $index => $scene) {
            $prompt = trim((string) ($scene['prompt'] ?? ''));
            $voiceoverText = trim((string) ($scene['voiceover_text'] ?? ''));
            $duration = (int) ($scene['duration_seconds'] ?? 0);
            $title = trim((string) ($scene['title'] ?? ''));
            $referenceImageUrl = null;
            $referenceImagePath = null;
            $referenceImageUrls = [];
            $referenceImagePaths = [];

            // Uploaded file (single per scene)
            if (isset($this->referenceImages[$index]) && $this->referenceImages[$index]) {
                $storedPath = $this->referenceImages[$index]->store('video-generation/reference-images', 'public');
                $referenceImageUrl = url(Storage::url($storedPath));
                $referenceImagePath = storage_path('app/public/' . $storedPath);
                $referenceImageUrls[] = $referenceImageUrl;
                $referenceImagePaths[] = $referenceImagePath;
            }

            // URL list (newline-separated textarea)
            $urlsText = trim((string) ($scene['reference_image_urls_text'] ?? ''));
            if ($urlsText !== '') {
                foreach (preg_split('/\r?\n/', $urlsText) as $line) {
                    $line = trim($line);
                    if ($line !== '' && filter_var($line, FILTER_VALIDATE_URL)) {
                        $referenceImageUrls[] = $line;
                    }
                }
            }

            if ($prompt === '' && $voiceoverText === '' && empty($referenceImageUrls)) {
                continue;
            }

            $totalDuration += $duration;

            $normalizedScenes[] = [
                'title' => $title !== '' ? $title : 'Scene ' . ($index + 1),
                'input_mode' => trim((string) ($scene['input_mode'] ?? 'static')) ?: 'static',
                'video_mode' => trim((string) ($scene['video_mode'] ?? 'animate')) ?: 'animate',
                'prompt' => $prompt,
                'voiceover_text' => $voiceoverText,
                'duration_seconds' => $duration,
                'reference_image_url' => $referenceImageUrl,
                'reference_image_path' => $referenceImagePath,
                'reference_image_urls' => $referenceImageUrls,
                'reference_image_paths' => $referenceImagePaths,
            ];
        }

        if (empty($normalizedScenes)) {
            session()->flash('error', 'Add at least one usable scene before submitting.');
            return;
        }

        if ($totalDuration > 180) {
            session()->flash('error', 'The stitched video must stay within 3 minutes.');
            return;
        }

        $organization = Organization::find($this->selectedOrganization);
        if (!$organization) {
            session()->flash('error', 'Selected organization was not found.');
            return;
        }

        $job = VideoGenerationJob::create([
            'organization_id' => $organization->id,
            'created_by' => Auth::id(),
            'title' => $this->title,
            'status' => 'submitting',
            'target_duration_seconds' => $totalDuration,
            'aspect_ratio' => $this->aspectRatio,
            'language' => $this->language,
            'speaker' => $this->speaker,
            'scenes' => $normalizedScenes,
            'settings' => [
                'global_prompt'  => $this->globalPrompt,
                'render_mode'    => 'storyboard-composer',
                'vast_ready'     => true,
                'output_quality' => $this->outputQuality,
                'avatar' => [
                    'id'         => $this->avatarId ?: null,
                    'position'   => $this->avatarPosition,
                    'size'       => $this->avatarSize,
                    'shape'      => $this->avatarShape,
                    'custom_url' => $this->avatarCustomUrl ?: null,
                ],
            ],
            'requested_at' => now(),
        ]);

        $payload = [
            'job_id' => (string) $job->id,
            'organization_id' => $organization->id,
            'organization_slug' => $organization->slug,
            'title' => $this->title,
            'language' => $this->language,
            'speaker' => $this->speaker,
            'aspect_ratio' => $this->aspectRatio,
            'output_quality' => $this->outputQuality,
            'target_duration_seconds' => $totalDuration,
            'global_prompt' => $this->globalPrompt,
            'scenes' => $normalizedScenes,
            'settings' => [
                'output_quality' => $this->outputQuality,
                'avatar' => [
                    'id'         => $this->avatarId ?: null,
                    'position'   => $this->avatarPosition,
                    'size'       => $this->avatarSize,
                    'shape'      => $this->avatarShape,
                    'custom_url' => $this->avatarCustomUrl ?: null,
                ],
            ],
            'source' => 'laravel-admin',
        ];

        $response = app(VideoGenerationService::class)->submitJob($job, $payload);

        if (!$response) {
            $job->update([
                'status' => 'failed',
                'error_message' => 'FastAPI video pipeline could not accept the request.',
            ]);
            session()->flash('error', 'Submission failed. Check the backend logs and video pipeline settings.');
            return;
        }

        $job->update([
            'status' => $response['status'] ?? 'queued',
            'backend_job_id' => $response['job_id'] ?? (string) $job->id,
            'backend_response' => $response,
            'error_message' => $response['error_message'] ?? null,
            'output_video_path' => $response['output_video_path'] ?? null,
            'output_video_url' => $response['output_video_url'] ?? null,
            'completed_at' => ($response['status'] ?? null) === 'completed' ? now() : null,
        ]);

        session()->flash('message', 'Video generation job submitted successfully.');
        $this->resetComposer();
    }

    public function refreshComfyuiStatus(): void
    {
        try {
            $response = app(VideoGenerationService::class)->comfyuiStatus();
            $this->comfyuiStatus = $response ?? ['available' => false];
        } catch (\Exception $e) {
            $this->comfyuiStatus = ['available' => false, 'error' => $e->getMessage()];
        }
    }

    public function refreshAvatarCatalogStatus(): void
    {
        try {
            $status = app(VideoGenerationService::class)->avatarCatalogStatus();
            $this->lipsyncMode = (string) ($status['lipsync_mode'] ?? 'unknown');
            $this->lipsyncEnabled = (bool) ($status['lipsync_enabled'] ?? false);
            $this->lipsyncUrl = (string) ($status['lipsync_url'] ?? '');
        } catch (\Exception $e) {
            $this->lipsyncMode = 'unknown';
            $this->lipsyncEnabled = false;
            $this->lipsyncUrl = '';
        }
    }

    public function refreshJobStatus(int $jobId): void
    {
        $job = VideoGenerationJob::find($jobId);
        if (!$job) {
            session()->flash('error', 'Video job not found.');
            return;
        }

        if (!app(VideoGenerationService::class)->syncJobStatus($job)) {
            session()->flash('error', 'Could not refresh the selected job right now.');
            return;
        }

        session()->flash('message', 'Video job status refreshed.');
    }

    public function setPreview(?int $jobId): void
    {
        $this->previewJobId = $jobId;
    }

    public function deleteJob(int $jobId): void
    {
        $job = VideoGenerationJob::find($jobId);
        if (!$job) {
            session()->flash('error', 'Job not found.');
            return;
        }

        // Delete the output video file (absolute path stored by FastAPI)
        if ($job->output_video_path && file_exists($job->output_video_path)) {
            @unlink($job->output_video_path);
        }

        // Delete the temp scene directory
        if ($job->backend_job_id) {
            $tmpDir = rtrim(config('filesystems.disks.local.root'), '/') . '/video-generation/tmp/' . $job->backend_job_id;
            if (is_dir($tmpDir)) {
                \Illuminate\Support\Facades\File::deleteDirectory($tmpDir);
            }
            // Delete the FastAPI job JSON file
            $jobJson = rtrim(config('filesystems.disks.local.root'), '/') . '/video-generation/jobs/' . $job->backend_job_id . '.json';
            if (file_exists($jobJson)) {
                @unlink($jobJson);
            }
        }

        // Close inline preview if this job was being previewed
        if ($this->previewJobId === $jobId) {
            $this->previewJobId = null;
        }

        $job->delete();
        session()->flash('message', 'Video job and file deleted successfully.');
    }

    public function parseStoryboard(): void
    {
        $text = $this->storyboardText;
        if (trim($text) === '') {
            session()->flash('error', 'Paste your storyboard script first.');
            return;
        }

        $lines = preg_split('/\r?\n/', $text);
        $scenes = [];
        $current = null;
        $section = null; // 'images' | 'prompt' | 'voiceover'

        // Parse header globals
        foreach ($lines as $line) {
            if (preg_match('/Output quality[:\s]+([\w]+)/i', $line, $m)) {
                $q = strtolower(trim($m[1]));
                if (in_array($q, ['standard', 'hd', 'fullhd'])) {
                    $this->outputQuality = $q;
                }
            }
            if (preg_match('/Aspect ratio[:\s]+([\d:]+)/i', $line, $m)) {
                $r = trim($m[1]);
                if (in_array($r, ['16:9', '9:16', '1:1'])) {
                    $this->aspectRatio = $r;
                }
            }
            if (preg_match('/Global style prompt[:\s]+(.+)/i', $line, $m)) {
                $this->globalPrompt = trim($m[1]);
            }
            if (preg_match('/^Voice[:\s]+(.+)/i', trim($line), $m)) {
                $this->speaker = $this->mapVoicePreferenceToSpeaker(trim($m[1]));
            }
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Separator line — reset section tracker
            if (preg_match('/^[─\-─]{10,}/', $trimmed)) {
                $section = null;
                continue;
            }

            // Scene heading: "Scene N — Title (X sec)"
            if (preg_match('/^Scene\s+(\d+)\s*[—\-–]+\s*(.+?)\s*(\((\d+)\s*sec\))?$/iu', $trimmed, $m)) {
                if ($current !== null) {
                    $scenes[] = $current;
                }
                $duration = isset($m[4]) ? (int) $m[4] : 10;
                $current = [
                    'title'   => 'Scene ' . $m[1] . ' — ' . trim(preg_replace('/\(\d+\s*sec\)/i', '', $m[2])),
                    'input_mode' => 'image',
                    'video_mode' => 'animate',
                    'prompt'  => '',
                    'voiceover_text' => '',
                    'duration_seconds' => max(4, $duration),
                    'reference_image_urls_text' => '',
                ];
                $section = null;
                continue;
            }

            if ($current === null) continue;

            // Section labels
            if (preg_match('/^Images?\s*(\(one per line[^)]*\))?\s*:?$/i', $trimmed)) {
                $section = 'images';
                continue;
            }
            if (preg_match('/^Prompt\s*:?$/i', $trimmed)) {
                $section = 'prompt';
                continue;
            }
            if (preg_match('/^Voice[- ]?over(?:\s+text)?\s*:?$/i', $trimmed)) {
                $section = 'voiceover';
                continue;
            }
            if (preg_match('/^Motion\s+mode\s*:\s*(animate|preserve)\s*$/i', $trimmed, $m)) {
                $current['video_mode'] = strtolower($m[1]);
                continue;
            }

            if ($trimmed === '') continue;

            if ($section === 'images' && preg_match('/^https?:\/\//i', $trimmed)) {
                $current['reference_image_urls_text'] .= ($current['reference_image_urls_text'] ? "\n" : '') . $trimmed;
                continue;
            }
            if ($section === 'prompt') {
                $current['prompt'] .= ($current['prompt'] ? ' ' : '') . $trimmed;
                continue;
            }
            if ($section === 'voiceover') {
                $current['voiceover_text'] .= ($current['voiceover_text'] ? ' ' : '') . $trimmed;
                continue;
            }
        }

        if ($current !== null) {
            $scenes[] = $current;
        }

        if (empty($scenes)) {
            session()->flash('error', 'Could not parse any scenes. Check the format matches the storyboard template.');
            return;
        }

        // Set input_mode based on whether images are present
        foreach ($scenes as &$s) {
            if (!empty(trim($s['reference_image_urls_text'])) && !empty(trim($s['prompt']))) {
                $s['input_mode'] = 'both';
            } elseif (!empty(trim($s['reference_image_urls_text']))) {
                $s['input_mode'] = 'image';
            } elseif (!empty(trim($s['prompt']))) {
                $s['input_mode'] = 'text';
            } else {
                $s['input_mode'] = 'static';
            }

            $s['video_mode'] = $this->inferVideoMode($s);
        }
        unset($s);

        $this->scenes = $scenes;
        $this->referenceImages = [];
        $this->showPastePanel = false;
        session()->flash('message', count($scenes) . ' scenes loaded from script. Review and submit.');
    }

    public function pollProcessingJobs(): void
    {
        $processing = VideoGenerationJob::whereIn('status', ['processing', 'queued', 'submitting'])
            ->pluck('id');
        foreach ($processing as $id) {
            try {
                $job = VideoGenerationJob::find($id);
                if ($job) {
                    app(VideoGenerationService::class)->syncJobStatus($job);
                }
            } catch (\Exception) {}
        }
    }

    public function render()
    {
        return view('livewire.admin.video-generation-manager')
            ->layout('layouts.admin');
    }

    protected function inferVideoMode(array $scene): string
    {
        $explicit = strtolower(trim((string) ($scene['video_mode'] ?? '')));
        if ($explicit === 'preserve') {
            return 'preserve';
        }

        $prompt = strtolower((string) ($scene['prompt'] ?? ''));
        $refs = strtolower((string) ($scene['reference_image_urls_text'] ?? ''));
        $textualPromptCues = [
            'screenshot', 'widget', 'dashboard', 'settings', 'admin', 'backend', 'panel',
            'website', 'webpage', 'interface', 'shopify', 'wordpress', 'logo', 'pricing',
            'faq', 'knowledge base', 'search bar', 'text',
        ];
        $textualRefCues = [
            'cdn.shopify.com', 'website-files.com', '/images/onboarding/', 'widget-settings',
            'screenshot-', '.png', '.webp',
        ];

        foreach ($textualPromptCues as $cue) {
            if (str_contains($prompt, $cue)) {
                return 'preserve';
            }
        }
        foreach ($textualRefCues as $cue) {
            if (str_contains($refs, $cue)) {
                return 'preserve';
            }
        }

        return 'animate';
    }

    /**
     * Map a free-text voice preference (from storyboard header) to an Edge TTS ShortName.
     * The value stored in $this->speaker is passed directly to FastAPI → edge-tts, so we
     * return the exact ShortName rather than a legacy display label.
     */
    protected function mapVoicePreferenceToSpeaker(string $voiceText): string
    {
        $voice = strtolower(trim($voiceText));
        if ($voice === '') {
            return '';
        }

        // Already an Edge TTS ShortName — pass through
        if (str_contains($voiceText, 'Neural')) {
            return $voiceText;
        }

        // Exact / legacy name mappings → Edge TTS ShortNames
        $map = [
            // Short-form IDs
            'female_1'       => 'en-IN-NeerjaExpressiveNeural',
            'female_2'       => 'hi-IN-SwaraNeural',
            'male_1'         => 'en-IN-PrabhatNeural',
            'male_2'         => 'en-US-BrianNeural',
            // Old XTTS / Indic legacy names → nearest Edge TTS equivalent
            'suad qasim'     => 'en-IN-NeerjaExpressiveNeural',
            'chanda madan'   => 'hi-IN-SwaraNeural',
            'kumar dahl'     => 'en-IN-PrabhatNeural',
            'damien black'   => 'en-US-BrianNeural',
            'neerja'         => 'en-IN-NeerjaExpressiveNeural',
            'prabhat'        => 'en-IN-PrabhatNeural',
            'swara'          => 'hi-IN-SwaraNeural',
            'madhur'         => 'hi-IN-MadhurNeural',
        ];

        foreach ($map as $needle => $value) {
            if (str_contains($voice, $needle)) {
                return $value;
            }
        }

        // Keyword-based fallbacks
        $isIndian = str_contains($voice, 'indian') || str_contains($voice, 'south asian')
                 || str_contains($voice, 'hindi') || str_contains($voice, 'accent');
        $isMale   = str_contains($voice, 'male') && !str_contains($voice, 'female');

        if ($isIndian) {
            return $isMale ? 'en-IN-PrabhatNeural' : 'en-IN-NeerjaExpressiveNeural';
        }

        if ($isMale) {
            return 'en-US-BrianNeural';
        }

        if (str_contains($voice, 'female')) {
            return 'en-US-AvaNeural';
        }

        // Default: expressive Indian English female
        return 'en-IN-NeerjaExpressiveNeural';
    }

    protected function makeScene(int $number): array
    {
        return [
            'title' => 'Scene ' . $number,
            'input_mode' => 'static',   // static | text | image | both
            'video_mode' => 'animate',  // animate | preserve
            'prompt' => '',
            'voiceover_text' => '',
            'duration_seconds' => 10,
            'reference_image_urls_text' => '',  // newline-separated URLs (one per image)
        ];
    }
}
