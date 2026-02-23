#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database migration runner.
 * Run: php bin/migrate.php
 * Requires .env to be configured.
 */

// Load config (from .env file or inherit from environment e.g. Docker)
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
// In Docker, env vars are passed directly; getenv() will use them

require $baseDir . '/vendor/autoload.php';

$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'smart_parking';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';

$dsn = "mysql:host={$host};port={$port};charset=utf8mb4";

echo "Connecting to database...\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}`");
    $pdo->exec("USE `{$name}`");
    echo "Database '{$name}' ready.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$migrationsDir = $baseDir . '/migrations';
$files = glob($migrationsDir . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    echo "Running {$name}...\n";
    $sql = file_get_contents($file);
    $pdo->exec($sql);
    echo "  Done.\n";
}

echo "Migrations completed.\n";
