<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $connection = null;

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $host = \App\Config\Config::get('DB_HOST', 'localhost');
            $port = \App\Config\Config::get('DB_PORT', '3306');
            $name = \App\Config\Config::get('DB_NAME', 'smart_parking');
            $user = \App\Config\Config::get('DB_USER', 'root');
            $pass = \App\Config\Config::get('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

            try {
                self::$connection = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $e) {
                throw new \RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }
        return self::$connection;
    }
}
