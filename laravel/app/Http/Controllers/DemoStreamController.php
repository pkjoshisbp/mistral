<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DemoStreamController extends Controller
{
    /**
     * Proxy FastAPI /llm/chat/stream forcing Vast.ai GPU.
     * SSE format: data: {"content":"token","done":false}\n\n
     *             data: {"content":"","done":true}\n\n
     */
    public function stream(Request $request): StreamedResponse
    {
        $messages = $request->json('messages', []);

        if (empty($messages)) {
            return response()->stream(function () {
                $this->initStreamOutput();
                echo "data: " . json_encode(['error' => 'No messages provided', 'done' => true]) . "\n\n";
                $this->streamFlush();
            }, 400, $this->sseHeaders());
        }

        $fastApiUrl = config('services.ai_agent.url', 'http://localhost:8111');
        $model      = 'llama3.1:8b';
        $postBody   = json_encode([
            'messages'     => $messages,
            'model'        => $model,
            'backend_type' => 'ollama',
            'options'      => [
                'use_vastai'  => true,
                'temperature' => 0.3,
            ],
        ]);

        Log::info('Demo stream chat started', [
            'messages_count' => count($messages),
            'endpoint'       => $fastApiUrl . '/llm/chat/stream',
            'model'          => $model,
            'use_vastai'     => true,
        ]);

        return response()->stream(function () use ($fastApiUrl, $postBody, $model) {
            $this->initStreamOutput();

            $sseBuffer    = '';
            $fullResponse = '';
            $hadOutput    = false;
            $hadDone      = false;

            $ch = curl_init($fastApiUrl . '/llm/chat/stream');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER         => false,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $postBody,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_TIMEOUT        => 90,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_WRITEFUNCTION  => function ($curl, $data) use (&$sseBuffer, &$fullResponse, &$hadOutput, &$hadDone) {
                    $sseBuffer .= $data;

                    $parts     = explode("\n\n", $sseBuffer);
                    $sseBuffer = array_pop($parts);

                    foreach ($parts as $part) {
                        $part = trim($part);
                        if ($part === '') continue;

                        foreach (preg_split('/\r?\n/', $part) as $line) {
                            $line = trim($line);
                            if (!str_starts_with($line, 'data: ')) continue;

                            $payload = json_decode(substr($line, 6), true);
                            if (!is_array($payload) || !empty($payload['error'])) continue;

                            $hasContent = isset($payload['content']) && $payload['content'] !== '';
                            if ($hasContent) {
                                $fullResponse .= $payload['content'];
                                $hadOutput = true;
                            }
                            if (!empty($payload['done'])) {
                                $hadDone = true;
                            }
                            if ($hasContent || !empty($payload['done'])) {
                                echo 'data: ' . json_encode($payload) . "\n\n";
                                $this->streamFlush();
                            }
                        }
                    }

                    return strlen($data);
                },
            ]);

            curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            $curlErr   = $curlErrNo ? curl_error($ch) : null;
            curl_close($ch);

            // Flush any remaining buffer
            if (trim($sseBuffer) !== '') {
                foreach (preg_split('/\r?\n/', trim($sseBuffer)) as $line) {
                    $line = trim($line);
                    if (!str_starts_with($line, 'data: ')) continue;
                    $payload = json_decode(substr($line, 6), true);
                    if (!is_array($payload) || !empty($payload['error'])) continue;
                    if (isset($payload['content']) && $payload['content'] !== '') {
                        $fullResponse .= $payload['content'];
                        $hadOutput = true;
                        echo 'data: ' . json_encode(['content' => $payload['content'], 'done' => false]) . "\n\n";
                        $this->streamFlush();
                    }
                    if (!empty($payload['done'])) {
                        $hadDone = true;
                        echo 'data: ' . json_encode(['content' => '', 'done' => true]) . "\n\n";
                        $this->streamFlush();
                    }
                }
            }

            if ($curlErrNo) {
                Log::error('Demo stream curl error', ['error' => $curlErr, 'model' => $model]);
                echo 'data: ' . json_encode(['error' => $curlErr, 'done' => true]) . "\n\n";
                $this->streamFlush();
                return;
            }

            if ($hadOutput && !$hadDone) {
                echo 'data: ' . json_encode(['content' => '', 'done' => true]) . "\n\n";
                $this->streamFlush();
            }

            Log::info('Demo stream completed', [
                'model'           => $model,
                'response_length' => strlen($fullResponse),
                'had_output'      => $hadOutput,
                'had_done'        => $hadDone,
            ]);
        }, 200, $this->sseHeaders());
    }

    private function initStreamOutput(): void
    {
        @ini_set('zlib.output_compression', '0');
        @ini_set('output_buffering', '0');
        @ini_set('implicit_flush', '1');
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
        @ob_implicit_flush(true);
    }

    private function streamFlush(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    private function sseHeaders(): array
    {
        return [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
            'Connection'        => 'keep-alive',
        ];
    }
}
