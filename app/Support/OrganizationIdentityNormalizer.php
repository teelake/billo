<?php

declare(strict_types=1);

namespace App\Support;

/** Normalizes TIN, company registration, and website for duplicate detection (per country / global host). */
final class OrganizationIdentityNormalizer
{
    /** Letters and digits only, uppercase. Null if empty after strip. */
    public static function normalizeTaxId(?string $taxId): ?string
    {
        if ($taxId === null) {
            return null;
        }
        $s = strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', trim($taxId)));
        return $s === '' ? null : $s;
    }

    /** CAC / RC / BN etc.: alphanumeric only, uppercase. */
    public static function normalizeCompanyRegistration(?string $registration): ?string
    {
        if ($registration === null) {
            return null;
        }
        $s = strtoupper((string) preg_replace('/[^a-zA-Z0-9]/', '', trim($registration)));
        return $s === '' ? null : $s;
    }

    /**
     * Hostname only, lowercase, no leading "www.". Null if unparseable or empty.
     * Accepts full URL or bare domain (e.g. acme.ng, https://www.acme.ng/path).
     */
    public static function normalizeWebsiteHost(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $t = trim($input);
        if ($t === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $t)) {
            $t = 'https://' . $t;
        }
        $host = parse_url($t, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = strtolower($host);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }
        return $host === '' ? null : $host;
    }
}
