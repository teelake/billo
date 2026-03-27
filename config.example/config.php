<?php

declare(strict_types=1);

/**
 * Copy this folder to `config/` (see project root) and edit values.
 * Optional overrides: copy local.example.php to config/local.php
 */
return [
    'app' => [
        'name' => 'billo',
        'env' => 'local',
        'debug' => true,
        /** Full origin, no trailing slash — used for redirects and emails */
        'url' => 'https://webspace.ng',
        /**
         * URL path where the app is mounted (no trailing slash).
         * Example: https://webspace.ng/billo → base_path is "/billo"
         * Set to "" if the document root is this app's public/ folder at domain root.
         */
        'base_path' => '/billo',
        /**
         * Force static asset prefix. Usually leave "" — billo_asset() detects DocumentRoot.
         * If CSS/JS 404, set "public" when the web root is the project folder (not /public).
         */
        'assets_url_segment' => '',
    ],
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'billo',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => 'billo_sess',
        'lifetime' => 60 * 60 * 24 * 7,
        'secure' => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ],
    /**
     * mail.driver: "log" (append to storage/logs/mail.log), "mail" (PHP mail()),
     * or "smtp" (use mail.smtp.*).
     */
    'mail' => [
        'driver' => 'log',
        'from_address' => 'noreply@example.com',
        'from_name' => 'billo',
        'smtp' => [
            'host' => '127.0.0.1',
            'port' => 587,
            'encryption' => 'tls',
            'username' => '',
            'password' => '',
            'timeout' => 20,
        ],
    ],
    'auth' => [
        'password_reset_ttl_minutes' => 60,
        'email_verification_ttl_hours' => 48,
        'invitation_ttl_days' => 7,
    ],
    /** Filled from DB platform_settings when present; can set here for local dev. */
    'brand' => [
        'tagline' => '',
        'support_email' => '',
    ],
    /**
     * Emails (lowercase) allowed to edit marketing/landing content at /platform/landing.
     */
    'platform' => [
        'admin_emails' => [
            // 'you@example.com',
        ],
    ],
    /**
     * Invoice pay links: HMAC signing + one active gateway (paystack, stripe, or none).
     * Secrets are best kept in config/local.php.
     */
    'payments' => [
        /** paystack | stripe | none */
        'provider' => 'paystack',
        /** Random string; signs /pay?token= for public links */
        'link_signing_secret' => '',
        /** When the invoice has no client email, Paystack still needs an email */
        'fallback_payer_email' => '',
        'paystack' => [
            'secret_key' => '',
            /** optional; used later for inline card fields */
            'public_key' => '',
        ],
        /** Install: composer require stripe/stripe-php */
        'stripe' => [
            'secret_key' => '',
            'webhook_secret' => '',
        ],
    ],
];
