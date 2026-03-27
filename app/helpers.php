<?php

declare(strict_types=1);

use App\Core\Config;

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
