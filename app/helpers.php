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

function billo_asset(string $path): string
{
    return billo_url('assets/' . ltrim($path, '/'));
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
