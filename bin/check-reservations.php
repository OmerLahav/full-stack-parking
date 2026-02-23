#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Quick check: list reservations in the database.
 * Run: php bin/check-reservations.php
 * With Docker: docker compose exec api php bin/check-reservations.php
 */

$baseDir = dirname(__DIR__);
$envFile = $baseDir . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        [$name, $value] = explode('=', $line, 2);
        putenv(trim($name) . '=' . trim($value, " \t\n\r\0\x0B\"'"));
    }
}

require $baseDir . '/vendor/autoload.php';

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'smart_parking';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$stmt = $pdo->query('SELECT id, user_id, spot_id, start_time, end_time, status FROM reservations ORDER BY id DESC LIMIT 20');
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Reservations in database (latest 20):\n";
echo str_repeat('-', 80) . "\n";
if (empty($rows)) {
    echo "No reservations found.\n";
    exit(0);
}
foreach ($rows as $r) {
    printf("id=%s user_id=%s spot_id=%s %s - %s status=%s\n",
        $r['id'], $r['user_id'], $r['spot_id'], $r['start_time'], $r['end_time'], $r['status']);
}
echo str_repeat('-', 80) . "\n";
echo "Total: " . count($rows) . " row(s)\n";
