<?php

declare(strict_types=1);

namespace App\Config;

class Config
{
    private static ?array $env = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        if (self::$env === null) {
            self::loadEnv();
        }
        return self::$env[$key] ?? getenv($key) ?: $default;
    }

    private static function loadEnv(): void
    {
        self::$env = [];
        $envFile = dirname(__DIR__, 2) . '/.env';
        if (!file_exists($envFile)) {
            return;
        }
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                [$name, $value] = explode('=', $line, 2);
                self::$env[trim($name)] = trim($value, " \t\n\r\0\x0B\"'");
            }
        }
    }
}
