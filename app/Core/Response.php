<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $location, int $code = 302): never
    {
        header('Location: ' . $location, true, $code);
        exit;
    }
}
