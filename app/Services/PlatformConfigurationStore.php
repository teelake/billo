<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Repositories\PlatformSettingsRepository;

/**
 * Persists platform operator edits into platform_settings (overrides file config).
 * Secrets: empty submit = leave unchanged; optional clr_* = remove DB override.
 */
final class PlatformConfigurationStore
{
    public function __construct(
        private PlatformSettingsRepository $settings = new PlatformSettingsRepository(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function save(Request $request): array
    {
        $errors = [];

        $this->saveTriBool($request, 'app.debug');
        $this->saveTriBool($request, 'session.secure');
        $this->saveTriBool($request, 'session.httponly');

        $this->saveStringOrRemove($request, 'app.env', $errors, null, 32);
        $this->saveUrlOrRemove($request, 'app.public_url', $errors);
        $this->saveBasePathOrRemove($request, 'app.base_path', $errors);
        $this->saveStringOrRemove($request, 'app.assets_url_segment', $errors, null, 64);

        $this->saveStringOrRemove($request, 'brand.name', $errors, null, 120);
        $this->saveStringOrRemove($request, 'brand.tagline', $errors, null, 500);
        $this->saveEmailOrRemove($request, 'brand.support_email', $errors);

        $driver = $this->trimmed($request, 'mail.driver');
        if ($driver !== null) {
            if (!in_array($driver, ['log', 'mail', 'smtp'], true)) {
                $errors[] = 'Mail driver must be log, mail, or smtp.';
            } else {
                $this->settings->upsert('mail.driver', $driver);
            }
        } else {
            $this->settings->upsert('mail.driver', null);
        }

        $this->saveEmailOrRemove($request, 'mail.from_address', $errors);
        $this->saveStringOrRemove($request, 'mail.from_name', $errors, null, 120);
        $this->saveStringOrRemove($request, 'mail.smtp.host', $errors, null, 255);
        $this->savePortOrRemove($request, 'mail.smtp.port', $errors);
        $enc = $this->trimmed($request, 'mail.smtp.encryption');
        if ($enc !== null) {
            if (!in_array($enc, ['', 'tls', 'ssl'], true)) {
                $errors[] = 'SMTP encryption must be tls, ssl, or empty.';
            } else {
                $this->settings->upsert('mail.smtp.encryption', $enc === '' ? null : $enc);
            }
        } else {
            $this->settings->upsert('mail.smtp.encryption', null);
        }
        $this->saveStringOrRemove($request, 'mail.smtp.username', $errors, null, 255);
        $this->savePositiveIntOrRemove($request, 'mail.smtp.timeout', $errors, 1, 300);
        $this->saveSecret($request, 'mail.smtp.password', $errors);

        $provider = $this->trimmed($request, 'payments.provider');
        if ($provider !== null) {
            if (!in_array($provider, ['none', 'paystack', 'stripe'], true)) {
                $errors[] = 'Payments provider must be none, paystack, or stripe.';
            } else {
                $this->settings->upsert('payments.provider', $provider);
            }
        } else {
            $this->settings->upsert('payments.provider', null);
        }
        $this->saveEmailOrRemove($request, 'payments.fallback_payer_email', $errors);
        $this->saveSecret($request, 'payments.link_signing_secret', $errors);
        $this->saveSecret($request, 'payments.paystack.secret_key', $errors);
        $this->saveSecret($request, 'payments.paystack.public_key', $errors);
        $this->saveSecret($request, 'payments.stripe.secret_key', $errors);
        $this->saveSecret($request, 'payments.stripe.webhook_secret', $errors);

        $this->saveStringOrRemove($request, 'session.name', $errors, '/^[a-zA-Z0-9_-]+$/', 64);
        $this->savePositiveIntOrRemove($request, 'session.lifetime', $errors, 60, 60 * 60 * 24 * 400);
        $ss = $this->trimmed($request, 'session.samesite');
        if ($ss !== null) {
            if (!in_array($ss, ['Lax', 'Strict', 'None'], true)) {
                $errors[] = 'SameSite must be Lax, Strict, or None.';
            } else {
                $this->settings->upsert('session.samesite', $ss);
            }
        } else {
            $this->settings->upsert('session.samesite', null);
        }

        $this->savePositiveIntOrRemove($request, 'auth.password_reset_ttl_minutes', $errors, 5, 10080);
        $this->savePositiveIntOrRemove($request, 'auth.email_verification_ttl_hours', $errors, 1, 720);
        $this->savePositiveIntOrRemove($request, 'auth.invitation_ttl_days', $errors, 1, 90);

        $this->saveAdminEmails($request, $errors);

        return $errors;
    }

    private static function inputKey(string $dbKey): string
    {
        return 'cfg_' . str_replace('.', '_', $dbKey);
    }

    private static function clearKey(string $dbKey): string
    {
        return 'clr_' . str_replace('.', '_', $dbKey);
    }

    private function trimmed(Request $request, string $dbKey): ?string
    {
        $field = self::inputKey($dbKey);
        $v = $request->input($field);
        if ($v === null) {
            return null;
        }
        $t = trim($v);

        return $t === '' ? null : $t;
    }

    private function wantsClear(Request $request, string $dbKey): bool
    {
        return $request->input(self::clearKey($dbKey)) === '1';
    }

    /** Empty = remove DB override (use file); 0 / 1 = stored. */
    private function saveTriBool(Request $request, string $dbKey): void
    {
        $v = $request->input(self::inputKey($dbKey));
        if ($v === null || $v === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }

        $this->settings->upsert($dbKey, $v === '1' ? '1' : '0');
    }

    /**
     * @param ?callable(string): bool $extraCheck returns false if invalid (caller should add error)
     */
    private function saveStringOrRemove(
        Request $request,
        string $dbKey,
        array &$errors,
        ?string $regex,
        int $maxLen,
    ): void {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $val = trim($raw);
        if (strlen($val) > $maxLen) {
            $errors[] = "Value too long for {$dbKey}.";

            return;
        }
        if ($regex !== null && !preg_match($regex, $val)) {
            $errors[] = "Invalid format for {$dbKey}.";

            return;
        }
        $this->settings->upsert($dbKey, $val);
    }

    private function saveUrlOrRemove(Request $request, string $dbKey, array &$errors): void
    {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $val = rtrim(trim($raw), '/');
        if (strlen($val) > 500 || !filter_var($val, FILTER_VALIDATE_URL)) {
            $errors[] = 'Public URL must be a valid http(s) URL.';

            return;
        }
        $this->settings->upsert($dbKey, $val);
    }

    private function saveBasePathOrRemove(Request $request, string $dbKey, array &$errors): void
    {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $val = trim($raw);
        if ($val !== '/' && $val !== '' && $val[0] !== '/') {
            $errors[] = 'Base path must start with / or be empty for root.';

            return;
        }
        $norm = $val === '/' || $val === '' ? '/' : rtrim($val, '/');
        $this->settings->upsert($dbKey, $norm);
    }

    private function saveEmailOrRemove(Request $request, string $dbKey, array &$errors): void
    {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $val = strtolower(trim($raw));
        if (!filter_var($val, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email for {$dbKey}.";

            return;
        }
        $this->settings->upsert($dbKey, $val);
    }

    private function savePortOrRemove(Request $request, string $dbKey, array &$errors): void
    {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $p = (int) trim($raw);
        if ($p < 1 || $p > 65535) {
            $errors[] = 'SMTP port must be between 1 and 65535.';

            return;
        }
        $this->settings->upsert($dbKey, (string) $p);
    }

    private function savePositiveIntOrRemove(
        Request $request,
        string $dbKey,
        array &$errors,
        int $min,
        int $max,
    ): void {
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $n = (int) trim($raw);
        if ($n < $min || $n > $max) {
            $errors[] = "{$dbKey} must be between {$min} and {$max}.";

            return;
        }
        $this->settings->upsert($dbKey, (string) $n);
    }

    private function saveSecret(Request $request, string $dbKey, array &$errors): void
    {
        if ($this->wantsClear($request, $dbKey)) {
            $this->settings->upsert($dbKey, null);

            return;
        }
        $field = self::inputKey($dbKey);
        $raw = $request->input($field);
        if ($raw === null || $raw === '') {
            return;
        }
        $val = trim($raw);
        if ($val === '') {
            return;
        }
        if (strlen($val) > 2000) {
            $errors[] = "Value too long for {$dbKey} (max 2000 characters).";

            return;
        }
        $this->settings->upsert($dbKey, $val);
    }

    private function saveAdminEmails(Request $request, array &$errors): void
    {
        $field = self::inputKey('platform.admin_emails');
        $raw = $request->input($field);
        if ($raw === null || trim($raw) === '') {
            $this->settings->upsert('platform.admin_emails', null);

            return;
        }
        $parts = preg_split('/[\s,]+/', strtolower(trim($raw))) ?: [];
        $emails = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '') {
                continue;
            }
            if (!filter_var($p, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid landing admin email: {$p}.";

                return;
            }
            $emails[$p] = true;
        }
        $list = array_keys($emails);
        if ($list === []) {
            $this->settings->upsert('platform.admin_emails', null);

            return;
        }
        try {
            $this->settings->upsert('platform.admin_emails', json_encode($list, JSON_THROW_ON_ERROR));
        } catch (\JsonException) {
            $errors[] = 'Could not store admin emails.';
        }
    }
}
