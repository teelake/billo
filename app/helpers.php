<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;

function billo_url(string $path = '/'): string
{
    $base = rtrim((string) Config::get('app.url', ''), '/');
    $prefix = rtrim((string) Config::get('app.base_path', ''), '/');
    $path = '/' . ltrim($path, '/');
    if ($prefix !== '') {
        return $base . $prefix . $path;
    }

    return $base . $path;
}

/**
 * Static URL for files under public/assets. Handles both hosting layouts:
 * - DocumentRoot = .../public  → /{base}/assets/...
 * - DocumentRoot = project root → /{base}/public/assets/...
 *
 * Override with config app.assets_url_segment (e.g. "public" or "").
 */
function billo_asset(string $path): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    $relative = 'assets/' . $path;

    $forced = trim((string) Config::get('app.assets_url_segment', ''), '/');
    if ($forced !== '') {
        return billo_url($forced . '/' . $relative);
    }

    if (defined('BILLO_ROOT') && PHP_SAPI !== 'cli-server') {
        $docRaw = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $doc = $docRaw !== '' ? (realpath(rtrim($docRaw, '/\\')) ?: '') : '';
        $pub = realpath(BILLO_ROOT . DIRECTORY_SEPARATOR . 'public') ?: '';

        if ($doc !== '' && $pub !== '' && $doc === $pub) {
            return billo_url($relative);
        }

        if ($pub !== '' && is_file($pub . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative))) {
            return billo_url('public/' . $relative);
        }
    }

    return billo_url($relative);
}

function billo_e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/** Display name from config or platform_settings (DB). */
function billo_brand_name(): string
{
    return (string) Config::get('app.name', 'billo');
}

/** Marketing tagline; empty string if unset. */
function billo_brand_tagline(): string
{
    return (string) Config::get('brand.tagline', '');
}

function billo_support_email(): string
{
    return (string) Config::get('brand.support_email', '');
}

/** Marketing copy from platform_settings (landing.*), merged into config at boot. */
function billo_landing(string $key, string $default = ''): string
{
    $v = Config::get('landing.' . $key, null);
    if (is_string($v) && $v !== '') {
        return $v;
    }

    return $default;
}

/** Session user email is listed in config platform.admin_emails (list of strings). */
function billo_invoice_pay_links_available(): bool
{
    return \App\Services\Payments\PaymentGatewayFactory::invoicePayLinksAvailable();
}

function billo_is_platform_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    $raw = Config::get('platform.admin_emails', []);
    if (!is_array($raw) || $raw === []) {
        return false;
    }
    $email = strtolower(trim((string) Session::get('user_email', '')));
    if ($email === '') {
        return false;
    }
    foreach ($raw as $e) {
        if (strtolower(trim((string) $e)) === $email) {
            return true;
        }
    }

    return false;
}
