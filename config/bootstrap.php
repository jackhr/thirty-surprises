<?php

declare(strict_types=1);

use App\Core\Env;

define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', BASE_PATH . '/public');

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once BASE_PATH . '/app/Core/helpers.php';

Env::load(BASE_PATH . '/.env');

$sessionName = Env::get('AUTH_SESSION_NAME', 'ash_surprises_session');
$rawLifetime = Env::int('AUTH_SESSION_MAX_AGE', 86400000);
$sessionLifetime = $rawLifetime > 3600 * 24 * 365 ? (int) ceil($rawLifetime / 1000) : max($rawLifetime, 300);

session_name($sessionName);
session_set_cookie_params([
    'lifetime' => $sessionLifetime,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

date_default_timezone_set(Env::get('APP_TIMEZONE', 'America/New_York') ?? 'America/New_York');
