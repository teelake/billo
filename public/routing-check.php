<?php

declare(strict_types=1);

/**
 * Temporary diagnostic: visit https://yoursite.com/billo/routing-check.php
 * If this 404s, the web root is wrong or the URL path does not point at /public.
 * Delete this file when finished.
 */
header('Content-Type: text/plain; charset=UTF-8');
echo "Billo routing check — PHP is running.\n\n";
echo 'REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo 'SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? '') . "\n";
echo 'DOCUMENT_ROOT=' . ($_SERVER['DOCUMENT_ROOT'] ?? '') . "\n\n";
echo "Expected: config app.base_path matches the folder after your domain.\n";
echo "Example: URL .../billo/login  →  base_path '/billo'\n";
