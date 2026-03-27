<?php

declare(strict_types=1);

namespace App\Support;

final class SecureToken
{
    public static function plain(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
