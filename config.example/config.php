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
];
