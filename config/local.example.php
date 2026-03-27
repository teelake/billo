<?php

declare(strict_types=1);

/**
 * Copy to config/local.php and adjust for your environment.
 */
return [
    'app' => [
        'env' => 'production',
        'debug' => false,
        'url' => 'https://webspace.ng',
        'base_path' => '/billo',
    ],
    'db' => [
        'host' => 'localhost',
        'database' => 'your_db',
        'username' => 'your_user',
        'password' => 'your_password',
    ],
    'session' => [
        'secure' => true,
    ],
    'mail' => [
        'driver' => 'smtp',
        'from_address' => 'noreply@yourdomain.com',
        'from_name' => 'billo',
        'smtp' => [
            'host' => 'smtp.yourhost.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'smtp_user',
            'password' => 'smtp_pass',
        ],
    ],
];
