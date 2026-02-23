<?php

namespace App\WebSockets;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;
use SplObjectStorage;

class WidgetChatSocketServer implements MessageComponentInterface
{
    protected SplObjectStorage $clients;

    public function __construct()
    {
        $this->clients = new SplObjectStorage();
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn, [
            'connected_at' => now()->toDateTimeString(),
        ]);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        $payload = json_decode((string) $msg, true);

        if (!is_array($payload)) {
            $this->sendPayload($from, ['error' => 'Invalid JSON payload.']);
            return;
        }

        if (($payload['type'] ?? null) === 'ping') {
            $this->sendPayload($from, ['type' => 'pong']);
            return;
        }

        $orgId = $payload['org_id'] ?? null;
        $message = trim((string) ($payload['message'] ?? ''));

        if (!$orgId || $message === '') {
            $this->sendPayload($from, ['error' => 'Missing org_id or message.']);
            return;
        }

        $requestPayload = $payload;
        unset($requestPayload['org_id']);

        try {
            $endpoint = rtrim(config('app.url'), '/') . '/widget/' . urlencode((string) $orgId) . '/chat';

            $response = Http::timeout((int) env('WIDGET_WEBSOCKET_FORWARD_TIMEOUT', 65))
                ->acceptJson()
                ->withHeaders([
                    'X-Widget-WebSocket' => '1',
                ])
                ->post($endpoint, $requestPayload);

            if (!$response->ok()) {
                $errorMessage = data_get($response->json(), 'error')
                    ?: data_get($response->json(), 'message')
                    ?: ('HTTP ' . $response->status());

                $this->sendPayload($from, ['error' => $errorMessage]);
                return;
            }

            $responseBody = $response->json();
            $content = trim((string) ($responseBody['response'] ?? ''));

            if ($content === '') {
                $this->sendPayload($from, ['error' => 'Empty AI response.']);
                return;
            }

            foreach (str_split($content, 500) as $chunk) {
                $this->sendPayload($from, ['content' => $chunk]);
            }

            $this->sendPayload($from, ['done' => true]);
        } catch (\Throwable $e) {
            Log::error('Widget websocket forward failed', [
                'error' => $e->getMessage(),
                'org_id' => $orgId,
            ]);

            $this->sendPayload($from, ['error' => 'Websocket server failed to process the request.']);
        }
    }

    public function onClose(ConnectionInterface $conn): void
    {
        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        Log::error('Widget websocket connection error', ['error' => $e->getMessage()]);

        if ($this->clients->contains($conn)) {
            $this->clients->detach($conn);
        }

        $conn->close();
    }

    protected function sendPayload(ConnectionInterface $connection, array $payload): void
    {
        $connection->send(json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
