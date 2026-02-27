<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARN', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        if (!Env::bool('APP_LOG_ENABLED', true)) {
            return;
        }

        $path = self::resolvePath();
        $directory = dirname($path);

        try {
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $line = sprintf(
                "[%s] %s %s%s",
                gmdate('Y-m-d\TH:i:s\Z'),
                $level,
                $message,
                $context !== [] ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
            );

            file_put_contents($path, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable) {
            // Logging should never break normal app execution.
        }
    }

    private static function resolvePath(): string
    {
        $configured = trim((string) Env::get('APP_LOG_FILE', 'storage/logs/app.log'));
        if ($configured === '') {
            $configured = 'storage/logs/app.log';
        }

        if (str_starts_with($configured, '/')) {
            return $configured;
        }

        return BASE_PATH . '/' . ltrim($configured, '/');
    }
}
