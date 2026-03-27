<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use Throwable;

/**
 * SaaS-wide settings stored in MySQL (URLs, brand, mail defaults).
 * Falls back silently if the table is missing or DB errors.
 */
final class PlatformSettings
{
    public static function applyFromDatabase(): void
    {
        try {
            $stmt = Database::pdo()->query(
                'SELECT setting_key, setting_value FROM platform_settings'
            );
            /** @var array<string, string>|false $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            if ($rows === false || $rows === []) {
                return;
            }
        } catch (Throwable $e) {
            error_log('Billo platform_settings: ' . $e->getMessage());

            return;
        }

        $pick = static function (string $key) use ($rows): ?string {
            if (!array_key_exists($key, $rows)) {
                return null;
            }
            $v = trim((string) $rows[$key]);

            return $v !== '' ? $v : null;
        };

        $patch = [];

        if ($v = $pick('app.public_url')) {
            $patch['app']['url'] = rtrim($v, '/');
        }
        if ($v = $pick('app.base_path')) {
            $patch['app']['base_path'] = $v === '/' ? '' : rtrim($v, '/');
        }
        if ($v = $pick('brand.name')) {
            $patch['app']['name'] = $v;
        }
        if ($v = $pick('brand.tagline')) {
            $patch['brand']['tagline'] = $v;
        }
        if ($v = $pick('brand.support_email')) {
            $patch['brand']['support_email'] = $v;
        }
        if ($v = $pick('mail.from_address')) {
            $patch['mail']['from_address'] = $v;
        }
        if ($v = $pick('mail.from_name')) {
            $patch['mail']['from_name'] = $v;
        }

        if ($patch !== []) {
            Config::extend($patch);
        }
    }
}
