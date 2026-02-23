<?php

namespace App\Console\Commands;

use App\WebSockets\WidgetChatSocketServer;
use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Factory as LoopFactory;
use React\Socket\SocketServer;

class RunWidgetWebSocketServer extends Command
{
    protected $signature = 'widget:websocket {--host=} {--port=} {--tls=} {--tls-cert=} {--tls-key=} {--tls-passphrase=}';

    protected $description = 'Run Ratchet WebSocket server for widget chat forwarding';

    public function handle(): int
    {
        $host = (string) ($this->option('host') ?: env('WIDGET_WEBSOCKET_HOST', '0.0.0.0'));
        $port = (int) ($this->option('port') ?: env('WIDGET_WEBSOCKET_PORT', 8090));
        $enableTls = $this->toBoolean($this->option('tls'), (bool) env('WIDGET_WEBSOCKET_TLS', true));
        $tlsCertPath = (string) ($this->option('tls-cert') ?: env('WIDGET_WEBSOCKET_TLS_CERT', '/var/www/clients/client1/web64/ssl/ai-chat.support-le.crt'));
        $tlsKeyPath = (string) ($this->option('tls-key') ?: env('WIDGET_WEBSOCKET_TLS_KEY', '/var/www/clients/client1/web64/ssl/ai-chat.support-le.key'));
        $tlsPassphrase = (string) ($this->option('tls-passphrase') ?: env('WIDGET_WEBSOCKET_TLS_PASSPHRASE', ''));

        if ($port < 1 || $port > 65535) {
            $this->error('Invalid port. Please provide a value between 1 and 65535.');
            return self::FAILURE;
        }

        if ($enableTls) {
            if (!is_file($tlsCertPath) || !is_readable($tlsCertPath)) {
                $this->error("TLS certificate not found or not readable: {$tlsCertPath}");
                return self::FAILURE;
            }

            if (!is_file($tlsKeyPath) || !is_readable($tlsKeyPath)) {
                $this->error("TLS key not found or not readable: {$tlsKeyPath}");
                return self::FAILURE;
            }
        }

        $scheme = $enableTls ? 'wss' : 'ws';
        $this->info("Starting widget websocket server on {$scheme}://{$host}:{$port}");

        if ($enableTls) {
            $loop = LoopFactory::create();

            $socketContext = [
                'tls' => [
                    'local_cert' => $tlsCertPath,
                    'local_pk' => $tlsKeyPath,
                    'verify_peer' => false,
                    'allow_self_signed' => true,
                ],
            ];

            if ($tlsPassphrase !== '') {
                $socketContext['tls']['passphrase'] = $tlsPassphrase;
            }

            $socketUri = "tls://{$host}:{$port}";
            $socket = new SocketServer($socketUri, $socketContext, $loop);
            $server = new IoServer(
                new HttpServer(new WsServer(new WidgetChatSocketServer())),
                $socket,
                $loop
            );

            $server->run();

            return self::SUCCESS;
        }

        $server = IoServer::factory(
            new HttpServer(new WsServer(new WidgetChatSocketServer())),
            $port,
            $host
        );

        $server->run();

        return self::SUCCESS;
    }

    protected function toBoolean(mixed $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? $default;
    }
}
