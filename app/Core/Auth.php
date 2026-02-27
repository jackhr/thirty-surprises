<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public static function checkSession(): void
    {
        $expiresAt = $_SESSION['expires_at'] ?? null;
        if (is_int($expiresAt) && $expiresAt < time()) {
            self::logout();
        }

        if (!empty($_SESSION['logged_in']) && !self::tokenIsValid((string) ($_SESSION['token'] ?? ''))) {
            self::logout();
        }
    }

    public static function login(array $user): void
    {
        $sessionSeconds = self::sessionLifetimeSeconds();
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = [
            'id' => (string) $user['id'],
            'name' => (string) $user['name'],
        ];
        $_SESSION['expires_at'] = time() + $sessionSeconds;
        $_SESSION['token'] = self::createToken((string) $user['id'], time() + $sessionSeconds);
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function user(): ?array
    {
        return !empty($_SESSION['logged_in']) ? ($_SESSION['user'] ?? null) : null;
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['logged_in']) && self::tokenIsValid((string) ($_SESSION['token'] ?? ''));
    }

    public static function requireLogin(): void
    {
        if (!self::isLoggedIn()) {
            Response::redirect('/');
        }
    }

    private static function createToken(string $userId, int $expiresAt): string
    {
        $payload = json_encode([
            'sub' => $userId,
            'exp' => $expiresAt,
        ], JSON_UNESCAPED_UNICODE);

        $encodedPayload = rtrim(strtr(base64_encode((string) $payload), '+/', '-_'), '=');
        $secret = Env::get('SECRET');
        $signature = hash_hmac('sha256', $encodedPayload, $secret, true);
        $encodedSignature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $encodedPayload . '.' . $encodedSignature;
    }

    private static function tokenIsValid(string $token): bool
    {
        if ($token === '' || !str_contains($token, '.')) {
            return false;
        }

        [$encodedPayload, $encodedSignature] = explode('.', $token, 2);

        $secret = Env::get('SECRET');
        $expectedSig = rtrim(strtr(base64_encode(hash_hmac('sha256', $encodedPayload, $secret, true)), '+/', '-_'), '=');
        if (!hash_equals($expectedSig, $encodedSignature)) {
            return false;
        }

        $rawPayload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
        if ($rawPayload === false) {
            return false;
        }

        $payload = json_decode($rawPayload, true);
        if (!is_array($payload)) {
            return false;
        }

        return isset($payload['exp']) && (int) $payload['exp'] >= time();
    }

    private static function sessionLifetimeSeconds(): int
    {
        $raw = Env::int('AUTH_SESSION_MAX_AGE', 86400000);

        if ($raw > 3600 * 24 * 365) {
            return (int) ceil($raw / 1000);
        }

        return max($raw, 300);
    }
}
