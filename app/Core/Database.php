<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $driver = strtolower(Env::get('DB_DRIVER', 'mysql') ?? 'mysql');

        try {
            if ($driver === 'mysql') {
                $host = Env::get('DB_HOST', '127.0.0.1');
                $port = Env::get('DB_PORT', '3306');
                $database = Env::get('DB_NAME', 'thirty_surprises');
                $username = Env::get('DB_USER', 'root');
                $password = Env::get('DB_PASS', '');
                $charset = Env::get('DB_CHARSET', 'utf8mb4');

                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                $database = Env::get('DB_NAME', BASE_PATH . '/storage/database.sqlite');
                if ($database === ':memory:') {
                    $dsn = 'sqlite::memory:';
                } else {
                    $dbPath = str_starts_with($database, '/') ? $database : BASE_PATH . '/' . ltrim($database, '/');
                    $dir = dirname($dbPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0775, true);
                    }
                    if (!is_file($dbPath)) {
                        touch($dbPath);
                    }
                    $dsn = 'sqlite:' . $dbPath;
                }

                self::$connection = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
        } catch (PDOException $exception) {
            throw new RuntimeException('Database connection failed: ' . $exception->getMessage(), 0, $exception);
        }

        return self::$connection;
    }
}
