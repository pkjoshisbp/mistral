<?php

namespace App\Services;

use App\Models\VideoGenerationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VideoGenerationService
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.ai_agent.url', 'http://localhost:8111'), '/');
    }

    public function submitJob(VideoGenerationJob $job, array $payload): ?array
    {
        try {
            $response = Http::timeout(20)->post("{$this->baseUrl}/video/jobs", $payload);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Video job submission failed', [
                'job_id' => $job->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Video job submission exception', [
                'job_id' => $job->id,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    public function syncJobStatus(VideoGenerationJob $job): bool
    {
        if (!$job->backend_job_id) {
            return false;
        }

        try {
            $response = Http::timeout(15)->get("{$this->baseUrl}/video/jobs/{$job->backend_job_id}");

            if (!$response->successful()) {
                Log::warning('Video job status request failed', [
                    'job_id' => $job->id,
                    'backend_job_id' => $job->backend_job_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            $payload = $response->json();
            $job->fill($this->mapJobPayload($payload));
            $job->save();

            return true;
        } catch (\Throwable $e) {
            Log::warning('Video job status sync exception', [
                'job_id' => $job->id,
                'backend_job_id' => $job->backend_job_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function mapJobPayload(array $payload): array
    {
        $status = (string) ($payload['status'] ?? 'queued');

        return [
            'status' => $status,
            'backend_response' => $payload,
            'output_video_path' => $payload['output_video_path'] ?? null,
            'output_video_url' => $payload['output_video_url'] ?? null,
            'error_message' => $payload['error_message'] ?? null,
            'completed_at' => $status === 'completed' ? now() : null,
        ];
    }

    public function comfyuiStatus(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/video/comfyui-status");
            if ($response->successful()) {
                return $response->json() ?? ['available' => false];
            }
        } catch (\Throwable $e) {
            Log::warning('ComfyUI status check failed', ['error' => $e->getMessage()]);
        }

        return ['available' => false];
    }

    public function avatarCatalogStatus(): array
    {
        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/video/avatar-catalog");
            if ($response->successful()) {
                $payload = $response->json() ?? [];
                return [
                    'lipsync_mode' => (string) ($payload['lipsync_mode'] ?? 'local'),
                    'lipsync_enabled' => (bool) ($payload['lipsync_enabled'] ?? false),
                    'lipsync_url' => (string) ($payload['lipsync_url'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
            Log::warning('Avatar catalog status check failed', ['error' => $e->getMessage()]);
        }

        return [
            'lipsync_mode' => 'local',
            'lipsync_enabled' => false,
            'lipsync_url' => '',
        ];
    }
}
