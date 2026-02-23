#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Background worker: Auto-releases stale reservations (end_time has passed).
 * Runs every 60 seconds. Logs actions as required.
 *
 * Run: php bin/stale-checker.php
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
use App\Database\Database;
use App\Repository\ReservationRepository;
use App\Repository\ParkingSpotRepository;
use App\Service\PubSub\RedisPubSub;

$reservationRepo = new ReservationRepository();
$spotRepo = new ParkingSpotRepository();
$pubSub = new RedisPubSub();

$intervalSeconds = 60;

echo "[" . date('Y-m-d H:i:s') . "] Stale reservation checker started (interval: {$intervalSeconds}s)\n";

while (true) {
    try {
        $stale = $reservationRepo->findStale();

        foreach ($stale as $reservation) {
            $spot = $spotRepo->findById((int) $reservation['spot_id']);
            $spotNumber = $spot['spot_number'] ?? $reservation['spot_id'];
            $reservationId = $reservation['id'];

            $reservationRepo->markCompletedById($reservationId);

            $logMsg = sprintf(
                "Auto-released Spot #%d (Reservation ID %d)",
                $spotNumber,
                $reservationId
            );
            echo "[" . date('Y-m-d H:i:s') . "] {$logMsg}\n";

            $reservation['status'] = 'Completed';
            $pubSub->publish('reservation_change', [
                'change' => 'completed',
                'reservation' => $reservation,
            ]);
        }
    } catch (Throwable $e) {
        echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    }

    sleep($intervalSeconds);
}
