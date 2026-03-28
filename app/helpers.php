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

function billo_google_oauth_enabled(): bool
{
    return (new \App\Services\GoogleOAuthService())->isEnabled();
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

/** Allowed tags for Quill/admin-authored HTML on the marketing site. */
function billo_sanitize_landing_html(string $html): string
{
    $allowed = '<p><br><br/><strong><b><em><i><u><a><ul><ol><li><h2><h3><h4><blockquote><span>';
    $html = strip_tags($html, $allowed);

    return preg_replace('/\son\w+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html) ?? $html;
}

/** Rich landing field: sanitize then output raw HTML in templates (not billo_e). */
function billo_landing_html(string $key, string $default = ''): string
{
    return billo_sanitize_landing_html(billo_landing($key, $default));
}

/** Price line for marketing cards (active subscription_plans rows). */
function billo_format_plan_price_line(array $plan): string
{
    $amt = (float) ($plan['price_amount'] ?? 0);
    $cur = strtoupper((string) ($plan['currency'] ?? 'NGN'));
    $intv = (string) ($plan['billing_interval'] ?? 'monthly');
    $prefix = $cur === 'NGN' ? '₦' : $cur . ' ';
    if ($intv === 'lifetime' && $amt <= 0) {
        return 'Free';
    }
    $decimals = abs($amt - round($amt)) < 0.001 ? 0 : 2;
    $num = number_format($amt, $decimals);
    $suffix = match ($intv) {
        'monthly' => ' / month',
        'yearly' => ' / year',
        default => '',
    };

    return $prefix . $num . $suffix;
}

/** Session user email is listed in config platform.admin_emails (list of strings). */
function billo_invoice_pay_links_available(): bool
{
    return \App\Services\Payments\PaymentGatewayFactory::invoicePayLinksAvailable();
}

/**
 * True when the signed-in user has platform operator access:
 * active platform_admin_grants row and/or users.is_system_admin = 1.
 */
function billo_is_system_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    $uid = Session::get('user_id');
    if (!is_numeric($uid)) {
        return false;
    }

    /** @var int|null */
    static $cachedUid = null;
    /** @var bool|null */
    static $cached = null;
    $uid = (int) $uid;
    if ($cached !== null && $cachedUid === $uid) {
        return $cached;
    }
    $cachedUid = $uid;
    $grants = new \App\Repositories\PlatformAdminGrantRepository();
    if ($grants->userHasActiveGrant($uid)) {
        $cached = true;

        return true;
    }
    $cached = (new \App\Repositories\UserRepository())->userHasPlatformOperatorFlag($uid);

    return $cached;
}

/** Platform operator signed in without any organization membership (tenant tools hidden). */
function billo_operator_without_tenant(): bool
{
    if (!billo_is_system_admin()) {
        return false;
    }
    $oid = Session::get('organization_id');
    $role = Session::get('role');

    return (is_numeric($oid) ? (int) $oid : -1) === 0 && $role === 'platform_operator';
}

/**
 * Sidebar context for system admins: organization (tenant UX) vs platform (operator tools).
 * Everyone else is treated as organization.
 */
function billo_app_nav_mode(): string
{
    if (function_exists('billo_operator_without_tenant') && billo_operator_without_tenant()) {
        return 'platform';
    }
    if (!function_exists('billo_is_system_admin') || !billo_is_system_admin()) {
        return 'organization';
    }
    $m = (string) Session::get('app_nav_mode', 'organization');

    return $m === 'platform' ? 'platform' : 'organization';
}

/**
 * img[src] for organization invoice logo: remote URL or authenticated local route.
 *
 * @param array<string, mixed>|null $organization
 */
/**
 * Standard invoice using document-level VAT/WHT (not per-line tax).
 *
 * @param array<string, mixed> $invoice
 */
function billo_invoice_use_document_tax(array $invoice): bool
{
    if (($invoice['invoice_kind'] ?? 'invoice') !== 'invoice') {
        return false;
    }

    return (($invoice['tax_computation'] ?? 'line') === 'document');
}

/**
 * Amount the client should pay (net after WHT for document tax; otherwise invoice total).
 *
 * @param array<string, mixed> $invoice
 */
function billo_invoice_payable_amount(array $invoice): float
{
    if (billo_invoice_use_document_tax($invoice) && array_key_exists('net_payable', $invoice)) {
        return (float) ($invoice['net_payable'] ?? 0);
    }

    return (float) ($invoice['total'] ?? 0);
}

/**
 * Hide per-line tax % column when using document tax, or when org disabled line tax and no line tax applied.
 *
 * @param array<string, mixed> $organization
 * @param array<string, mixed> $invoice
 */
function billo_invoice_hide_tax_column(array $organization, array $invoice): bool
{
    if (billo_invoice_use_document_tax($invoice)) {
        return true;
    }

    if ((int) ($organization['invoice_tax_enabled'] ?? 1) !== 0) {
        return false;
    }

    return abs((float) ($invoice['tax_total'] ?? 0)) < 0.00001;
}

/**
 * Human-readable bank transfer lines from organization branding (PDF / print / show).
 *
 * @param array<string, mixed> $organization
 * @return list<string>
 */
function billo_organization_bank_detail_lines(array $organization): array
{
    $lines = [];
    $bank = trim((string) ($organization['invoice_bank_name'] ?? ''));
    if ($bank !== '') {
        $lines[] = 'Bank: ' . $bank;
    }
    $acctName = trim((string) ($organization['invoice_bank_account_name'] ?? ''));
    if ($acctName !== '') {
        $lines[] = 'Account name: ' . $acctName;
    }
    $acctNum = trim((string) ($organization['invoice_bank_account_number'] ?? ''));
    if ($acctNum !== '') {
        $lines[] = 'Account number: ' . $acctNum;
    }

    return $lines;
}

/**
 * Public URL for an image stored under storage/landing/… (trusted, portraits, hero).
 */
function billo_landing_media_url(string $relative): string
{
    $relative = str_replace('\\', '/', trim($relative));
    if ($relative === '' || str_contains($relative, '..')) {
        return '';
    }
    if (preg_match('#^storage/landing/(trusted|portraits|hero)/[a-zA-Z0-9._-]+$#', $relative) !== 1) {
        return '';
    }
    $rest = substr($relative, strlen('storage/landing/'));

    return billo_url('/media/landing/' . $rest);
}

/**
 * Resolve a landing or CMS image reference for <img src>: https URL, absolute path, or storage/landing/… .
 */
function billo_resolve_public_image_src(string $ref): string
{
    $ref = trim($ref);
    if ($ref === '') {
        return '';
    }
    if (str_starts_with($ref, 'https://') || str_starts_with($ref, 'http://')) {
        return $ref;
    }
    if (str_starts_with($ref, '/')) {
        return $ref;
    }
    $u = billo_landing_media_url($ref);

    return $u !== '' ? $u : '';
}

function billo_organization_logo_display_url(?array $organization): ?string
{
    if ($organization === null) {
        return null;
    }
    $raw = trim((string) ($organization['invoice_logo_url'] ?? ''));
    if ($raw === '') {
        return null;
    }
    if (str_starts_with($raw, 'https://') || str_starts_with($raw, 'http://')) {
        return $raw;
    }

    return billo_url('/organization/logo');
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
