<?php

namespace App\Console\Commands;

use App\Support\VastAiConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

class CheckVastAiConnectivity extends Command
{
    protected $signature = 'vastai:check-connectivity';

    protected $description = 'Check Vast.ai tunnel connectivity and alert on repeated failures';

    public function handle(): int
    {
        $vastConfig = VastAiConfig::current();
        $ollamaUrl = rtrim((string) env('VASTAI_OLLAMA_HEALTH_URL', 'http://127.0.0.1:11435/api/tags'), '/');
        $whisperHost = (string) env('VASTAI_WHISPER_HOST', '127.0.0.1');
        $whisperPort = (int) env('VASTAI_WHISPER_PORT', 18081);
        $failureThreshold = (int) env('VASTAI_ALERT_FAILURE_THRESHOLD', 3);
        $alertEmail = (string) env('VASTAI_ALERT_EMAIL', 'pkjoshi.sbp@gmail.com');

        $ollamaOk = $this->checkHttp($ollamaUrl);
        $whisperOk = $this->checkTcp($whisperHost, $whisperPort);

        $restartAttempted = false;
        if (!($ollamaOk && $whisperOk)) {
            $restartAttempted = $this->restartTunnel($vastConfig);
            if ($restartAttempted) {
                $ollamaOk = $this->checkHttp($ollamaUrl);
                $whisperOk = $this->checkTcp($whisperHost, $whisperPort);
            }
        }

        $isHealthy = $ollamaOk && $whisperOk;

        $failureKey = 'vastai_connectivity_failures';
        $alertKey = 'vastai_connectivity_alert_sent';
        $statusKey = 'vastai_connectivity_status';

        if ($isHealthy) {
            Cache::forget($failureKey);
            Cache::forget($alertKey);
            Cache::put($statusKey, [
                'healthy' => true,
                'failures' => 0,
                'checked_at' => now()->toDateTimeString(),
                'ollama_ok' => true,
                'whisper_ok' => true,
            ], now()->addDays(1));
            Log::info('Vast.ai connectivity check passed', [
                'ollama_url' => $ollamaUrl,
                'whisper_host' => $whisperHost,
                'whisper_port' => $whisperPort,
                'configured_vast_host' => $vastConfig['host'],
                'configured_vast_port' => $vastConfig['port'],
                'restart_attempted' => $restartAttempted,
            ]);
            $this->info('Vast.ai connectivity OK');
            return self::SUCCESS;
        }

        $failures = (int) Cache::get($failureKey, 0) + 1;
        Cache::put($failureKey, $failures, now()->addDays(1));
        Cache::put($statusKey, [
            'healthy' => false,
            'failures' => $failures,
            'checked_at' => now()->toDateTimeString(),
            'ollama_ok' => $ollamaOk,
            'whisper_ok' => $whisperOk,
        ], now()->addDays(1));

        Log::warning('Vast.ai connectivity check failed', [
            'failures' => $failures,
            'threshold' => $failureThreshold,
            'ollama_ok' => $ollamaOk,
            'whisper_ok' => $whisperOk,
            'ollama_url' => $ollamaUrl,
            'whisper_host' => $whisperHost,
            'whisper_port' => $whisperPort,
            'configured_vast_host' => $vastConfig['host'],
            'configured_vast_port' => $vastConfig['port'],
            'configured_vast_user' => $vastConfig['user'],
            'restart_attempted' => $restartAttempted,
        ]);

        if ($failures >= $failureThreshold && !Cache::has($alertKey)) {
            $subject = 'Vast.ai connection issue - AI Chat Support';
            $body = implode("\n", [
                'Vast.ai connectivity issue detected.',
                'Please contact support.',
                '',
                'Checks failed consecutively: ' . $failures,
                'Configured Vast.ai host: ' . $vastConfig['user'] . '@' . $vastConfig['host'] . ':' . $vastConfig['port'],
                'Ollama tunnel URL: ' . $ollamaUrl,
                'Whisper tunnel: ' . $whisperHost . ':' . $whisperPort,
                'Timestamp: ' . now()->toDateTimeString(),
            ]);

            try {
                Mail::raw($body, function ($message) use ($alertEmail, $subject): void {
                    $message->to($alertEmail)->subject($subject);
                });

                Cache::put($alertKey, true, now()->addHours(12));
                Log::warning('Vast.ai connectivity alert email sent', [
                    'to' => $alertEmail,
                    'failures' => $failures,
                ]);
                $this->warn('Alert email sent: ' . $alertEmail);
            } catch (\Throwable $exception) {
                Log::error('Failed to send Vast.ai connectivity alert email', [
                    'error' => $exception->getMessage(),
                    'to' => $alertEmail,
                ]);
                $this->error('Failed to send alert email');
            }
        }

        $this->error('Vast.ai connectivity FAILED');
        return self::FAILURE;
    }

    private function restartTunnel(array $vastConfig): bool
    {
        $scriptPath = dirname(base_path()) . '/scripts/start-ollama-tunnel.sh';
        if (!is_file($scriptPath)) {
            return false;
        }

        try {
            VastAiConfig::writeShellEnvFile();

            $process = new Process(['bash', $scriptPath], dirname(base_path()), [
                'VAST_HOST' => (string) $vastConfig['host'],
                'VAST_PORT' => (string) $vastConfig['port'],
                'VAST_USER' => (string) $vastConfig['user'],
            ]);
            $process->setTimeout(30);
            $process->run();

            Log::info('Attempted Vast.ai tunnel restart from connectivity check', [
                'configured_vast_host' => $vastConfig['host'],
                'configured_vast_port' => $vastConfig['port'],
                'configured_vast_user' => $vastConfig['user'],
                'successful' => $process->isSuccessful(),
                'output' => trim($process->getOutput()),
                'error_output' => trim($process->getErrorOutput()),
            ]);

            return $process->isSuccessful();
        } catch (\Throwable $exception) {
            Log::warning('Failed to restart Vast.ai tunnel from connectivity check', [
                'configured_vast_host' => $vastConfig['host'],
                'configured_vast_port' => $vastConfig['port'],
                'configured_vast_user' => $vastConfig['user'],
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function checkHttp(string $url): bool
    {
        try {
            $response = Http::timeout(5)->get($url);
            return $response->ok();
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function checkTcp(string $host, int $port): bool
    {
        $connection = @fsockopen($host, $port, $errorNumber, $errorString, 3.0);
        if (!is_resource($connection)) {
            return false;
        }

        fclose($connection);
        return true;
    }
}
