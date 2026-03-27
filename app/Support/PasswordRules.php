<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Password policy for signup and password reset. Keep client-side hints in app.js in sync.
 */
final class PasswordRules
{
    public const MIN_LENGTH = 10;

    public const MAX_LENGTH = 128;

    /**
     * @return non-empty-string|null Error message, or null when acceptable.
     */
    public static function validate(string $password): ?string
    {
        $len = self::len($password);
        if ($len < self::MIN_LENGTH) {
            return 'Password must be at least ' . self::MIN_LENGTH . ' characters.';
        }
        if ($len > self::MAX_LENGTH) {
            return 'Password must be at most ' . self::MAX_LENGTH . ' characters.';
        }
        if (!preg_match('/\p{L}/u', $password)) {
            return 'Password must include at least one letter.';
        }
        if (!preg_match('/\d/', $password)) {
            return 'Password must include at least one number.';
        }
        if (!preg_match('/[A-Z]/', $password) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            return 'Password must include an uppercase letter or a symbol (e.g. ! @ #).';
        }

        return null;
    }

    private static function len(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int) mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }
}
