#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * WebSocket server for real-time parking updates.
 * Polls Redis queue for reservation changes and broadcasts to connected clients.
 *
 * Run: php bin/websocket-server.php
 */

$baseDir = dirname(__DIR__);
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

require $baseDir . '/vendor/autoload.php';

use App\Config\Config;
use App\Service\PubSub\RedisPubSub;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;

$host = Config::get('WS_HOST', '0.0.0.0');
$port = (int) Config::get('WS_PORT', '8081');

class ParkingWebSocketHandler implements MessageComponentInterface
{
    private \SplObjectStorage $clients;
    private \Predis\Client $redis;
    private \React\EventLoop\LoopInterface $loop;

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->loop = Loop::get();
        $pubSub = new RedisPubSub();
        $this->redis = $pubSub->getClient();

        $this->loop->addPeriodicTimer(0.2, [$this, 'processBroadcastQueue']);
    }

    public function processBroadcastQueue(): void
    {
        try {
            while (true) {
                $payload = $this->redis->lpop(RedisPubSub::BROADCAST_QUEUE);
                if ($payload === null || $payload === false) {
                    break;
                }
                $decoded = json_decode($payload, true);
                $message = $decoded ? json_encode($decoded) : $payload;

                foreach ($this->clients as $client) {
                    if ($client instanceof ConnectionInterface) {
                        $client->send($message);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('WebSocket broadcast error: ' . $e->getMessage());
        }
    }

    public function onOpen(ConnectionInterface $conn): void
    {
        $this->clients->attach($conn);
    }

    public function onMessage(ConnectionInterface $from, $msg): void
    {
        // Clients can send ping for heartbeat
    }

    public function onClose(ConnectionInterface $conn): void
    {
        $this->clients->detach($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e): void
    {
        $conn->close();
    }
}

$handler = new ParkingWebSocketHandler();

$server = IoServer::factory(
    new HttpServer(new WsServer($handler)),
    $port,
    $host
);

echo "WebSocket server listening on ws://{$host}:{$port}\n";
$server->run();
