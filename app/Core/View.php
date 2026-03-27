<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = []): void
    {
        $viewsDir = dirname(__DIR__, 2) . '/views';
        $path = $viewsDir . '/' . $template . '.php';
        if (!is_file($path)) {
            http_response_code(500);
            echo 'View not found.';
            return;
        }
        extract($data, EXTR_SKIP);
        include $path;
    }
}
