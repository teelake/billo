<?php

declare(strict_types=1);

/**
 * Shared hosting: put ONLY this folder’s files in public_html/billo/
 * and keep app/config/vendor/storage ABOVE the web root.
 * Then copy billo-root.php.example → billo-root.php and set the absolute path to that private folder.
 */
if (!defined('BILLO_ROOT')) {
    $override = __DIR__ . DIRECTORY_SEPARATOR . 'billo-root.php';
    if (is_file($override)) {
        $path = require $override;
        if (is_string($path) && $path !== '') {
            $resolved = realpath($path);
            if ($resolved !== false && is_file($resolved . '/app/bootstrap.php')) {
                define('BILLO_ROOT', $resolved);
            }
        }
    }
}
if (!defined('BILLO_ROOT')) {
    define('BILLO_ROOT', dirname(__DIR__));
}

require BILLO_ROOT . '/app/error_bootstrap.php';
billo_setup_error_logging(BILLO_ROOT);

require BILLO_ROOT . '/app/bootstrap.php';
