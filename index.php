<?php

declare(strict_types=1);

/**
 * Entry when the web server document root is the project folder (not /public).
 * Fixes common 403/empty-index issues on shared hosting.
 */
require __DIR__ . '/public/index.php';
