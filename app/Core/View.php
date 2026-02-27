<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class View
{
    public static function render(string $template, array $data = [], int $status = 200): never
    {
        $viewPath = BASE_PATH . '/app/Views/' . ltrim($template, '/') . '.php';

        if (!is_file($viewPath)) {
            http_response_code(500);
            echo 'View not found: ' . htmlspecialchars($template, ENT_QUOTES, 'UTF-8');
            exit;
        }

        http_response_code($status);

        extract($data, EXTR_SKIP);

        try {
            include $viewPath;
        } catch (Throwable $exception) {
            http_response_code(500);
            echo 'Error rendering view.';
        }

        exit;
    }
}
