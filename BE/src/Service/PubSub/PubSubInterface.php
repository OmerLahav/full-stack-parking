<?php

declare(strict_types=1);

namespace App\Service\PubSub;

interface PubSubInterface
{
    public function publish(string $channel, array $message): void;
}
