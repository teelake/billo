<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use PDO;
use Throwable;

/**
 * SaaS-wide settings in MySQL (override config after load).
 * See PlatformConfigurationStore for editable keys.
 */
final class PlatformSettings
{
    public static function applyFromDatabase(): void
    {
        try {
            $stmt = Database::pdo()->query('SELECT setting_key, setting_value FROM platform_settings');
            if ($stmt === false) {
                return;
            }
            /** @var list<array{setting_key:string, setting_value:string|null}> $list */
            $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('Billo platform_settings: ' . $e->getMessage());

            return;
        }

        $assoc = [];
        foreach ($list as $row) {
            $k = (string) ($row['setting_key'] ?? '');
            if ($k === '') {
                continue;
            }
            $assoc[$k] = $row['setting_value'] === null ? null : (string) $row['setting_value'];
        }

        if ($assoc === []) {
            return;
        }

        $patch = [];

        if (array_key_exists('app.public_url', $assoc)) {
            $v = trim((string) $assoc['app.public_url']);
            if ($v !== '') {
                $patch['app']['url'] = rtrim($v, '/');
            }
        }

        if (array_key_exists('app.base_path', $assoc)) {
            $raw = trim((string) ($assoc['app.base_path'] ?? ''));
            $patch['app']['base_path'] = ($raw === '' || $raw === '/') ? '' : rtrim($raw, '/');
        }

        if (array_key_exists('app.assets_url_segment', $assoc)) {
            $v = trim((string) $assoc['app.assets_url_segment']);
            $patch['app']['assets_url_segment'] = $v === '' ? '' : trim($v, '/');
        }

        if (array_key_exists('app.env', $assoc)) {
            $v = trim((string) $assoc['app.env']);
            if ($v !== '') {
                $patch['app']['env'] = $v;
            }
        }

        if (array_key_exists('app.debug', $assoc)) {
            $v = strtolower(trim((string) $assoc['app.debug']));
            $patch['app']['debug'] = in_array($v, ['1', 'true', 'yes', 'on'], true);
        }

        if (array_key_exists('brand.name', $assoc)) {
            $v = trim((string) $assoc['brand.name']);
            if ($v !== '') {
                $patch['app']['name'] = $v;
            }
        }

        if (array_key_exists('brand.tagline', $assoc)) {
            $patch['brand']['tagline'] = trim((string) $assoc['brand.tagline']);
        }

        if (array_key_exists('brand.support_email', $assoc)) {
            $v = trim((string) $assoc['brand.support_email']);
            if ($v !== '') {
                $patch['brand']['support_email'] = $v;
            }
        }

        if (array_key_exists('mail.driver', $assoc)) {
            $v = trim((string) $assoc['mail.driver']);
            if (in_array($v, ['log', 'mail', 'smtp'], true)) {
                $patch['mail']['driver'] = $v;
            }
        }

        if (array_key_exists('mail.from_address', $assoc)) {
            $v = trim((string) $assoc['mail.from_address']);
            if ($v !== '') {
                $patch['mail']['from_address'] = $v;
            }
        }

        if (array_key_exists('mail.from_name', $assoc)) {
            $v = trim((string) $assoc['mail.from_name']);
            if ($v !== '') {
                $patch['mail']['from_name'] = $v;
            }
        }

        foreach (['mail.smtp.host' => ['mail', 'smtp', 'host'], 'mail.smtp.username' => ['mail', 'smtp', 'username'], 'mail.smtp.password' => ['mail', 'smtp', 'password']] as $dbK => $path) {
            if (!array_key_exists($dbK, $assoc)) {
                continue;
            }
            $val = $assoc[$dbK];
            $patch[$path[0]][$path[1]][$path[2]] = $val === null ? '' : (string) $val;
        }

        if (array_key_exists('mail.smtp.port', $assoc)) {
            $raw = trim((string) ($assoc['mail.smtp.port'] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                $patch['mail']['smtp']['port'] = max(1, min(65535, (int) $raw));
            }
        }

        if (array_key_exists('mail.smtp.encryption', $assoc)) {
            $v = trim((string) ($assoc['mail.smtp.encryption'] ?? ''));
            if (in_array($v, ['tls', 'ssl', ''], true)) {
                $patch['mail']['smtp']['encryption'] = $v;
            }
        }

        if (array_key_exists('mail.smtp.timeout', $assoc)) {
            $raw = trim((string) ($assoc['mail.smtp.timeout'] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                $patch['mail']['smtp']['timeout'] = max(1, min(300, (int) $raw));
            }
        }

        if (array_key_exists('payments.provider', $assoc)) {
            $v = trim((string) $assoc['payments.provider']);
            if (in_array($v, ['none', 'paystack', 'stripe'], true)) {
                $patch['payments']['provider'] = $v;
            }
        }

        foreach (['payments.link_signing_secret', 'payments.fallback_payer_email'] as $dbK) {
            if (!array_key_exists($dbK, $assoc)) {
                continue;
            }
            $tail = substr($dbK, strlen('payments.'));
            $patch['payments'][$tail] = trim((string) ($assoc[$dbK] ?? ''));
        }

        foreach (['secret_key', 'public_key'] as $tail) {
            $dbK = 'payments.paystack.' . $tail;
            if (!array_key_exists($dbK, $assoc)) {
                continue;
            }
            $patch['payments']['paystack'][$tail] = $assoc[$dbK] === null ? '' : (string) $assoc[$dbK];
        }

        foreach (['secret_key', 'webhook_secret'] as $tail) {
            $dbK = 'payments.stripe.' . $tail;
            if (!array_key_exists($dbK, $assoc)) {
                continue;
            }
            $patch['payments']['stripe'][$tail] = $assoc[$dbK] === null ? '' : (string) $assoc[$dbK];
        }

        if (array_key_exists('session.name', $assoc)) {
            $v = trim((string) $assoc['session.name']);
            if ($v !== '') {
                $patch['session']['name'] = $v;
            }
        }

        if (array_key_exists('session.lifetime', $assoc)) {
            $raw = trim((string) ($assoc['session.lifetime'] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                $patch['session']['lifetime'] = (int) $raw;
            }
        }

        if (array_key_exists('session.secure', $assoc)) {
            $v = strtolower(trim((string) $assoc['session.secure']));
            $patch['session']['secure'] = in_array($v, ['1', 'true', 'yes', 'on'], true);
        }

        if (array_key_exists('session.httponly', $assoc)) {
            $v = strtolower(trim((string) $assoc['session.httponly']));
            $patch['session']['httponly'] = in_array($v, ['1', 'true', 'yes', 'on'], true);
        }

        if (array_key_exists('session.samesite', $assoc)) {
            $v = trim((string) $assoc['session.samesite']);
            if (in_array($v, ['Lax', 'Strict', 'None'], true)) {
                $patch['session']['samesite'] = $v;
            }
        }

        foreach (['password_reset_ttl_minutes', 'email_verification_ttl_hours', 'invitation_ttl_days'] as $authKey) {
            $dbK = 'auth.' . $authKey;
            if (!array_key_exists($dbK, $assoc)) {
                continue;
            }
            $raw = trim((string) ($assoc[$dbK] ?? ''));
            if ($raw !== '' && ctype_digit($raw)) {
                $patch['auth'][$authKey] = (int) $raw;
            }
        }

        if (array_key_exists('platform.admin_emails', $assoc)) {
            $raw = trim((string) ($assoc['platform.admin_emails'] ?? ''));
            if ($raw !== '') {
                try {
                    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $emails = [];
                        foreach ($decoded as $e) {
                            $e = strtolower(trim((string) $e));
                            if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                                $emails[] = $e;
                            }
                        }
                        if ($emails !== []) {
                            $patch['platform']['admin_emails'] = $emails;
                        }
                    }
                } catch (Throwable) {
                    // ignore bad JSON
                }
            }
        }

        $landing = [];
        foreach ($assoc as $rowKey => $rowVal) {
            if (!str_starts_with((string) $rowKey, 'landing.')) {
                continue;
            }
            $sub = substr((string) $rowKey, strlen('landing.'));
            if ($sub === '') {
                continue;
            }
            $val = trim((string) $rowVal);
            if ($val !== '') {
                $landing[$sub] = $val;
            }
        }
        if ($landing !== []) {
            $patch['landing'] = $landing;
        }

        if ($patch !== []) {
            Config::extend($patch);
        }
    }
}
