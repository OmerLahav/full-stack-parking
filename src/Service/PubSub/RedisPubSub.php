<?php

declare(strict_types=1);

namespace App\Service\PubSub;

use App\Config\Config;
use Predis\Client;

class RedisPubSub implements PubSubInterface
{
    public const BROADCAST_QUEUE = 'ws_broadcast';

    private ?Client $client = null;

    public function publish(string $channel, array $message): void
    {
        try {
            $client = $this->getClient();
            $payload = json_encode(['channel' => $channel, 'data' => $message]);
            $client->rpush(self::BROADCAST_QUEUE, $payload);
        } catch (\Throwable $e) {
            error_log('Redis publish failed: ' . $e->getMessage());
        }
    }

    public function getClient(): Client
    {
        if ($this->client === null) {
            $host = Config::get('REDIS_HOST', 'localhost');
            $port = Config::get('REDIS_PORT', '6379');
            $this->client = new Client([
                'scheme' => 'tcp',
                'host' => $host,
                'port' => (int) $port,
            ]);
        }
        return $this->client;
    }
}
