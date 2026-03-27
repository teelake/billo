<?php
declare(strict_types=1);

/** @var string $message */
$m = isset($message) ? (string) $message : 'Something went wrong.';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment — <?= billo_e(billo_brand_name()) ?></title>
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="layout-main" style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem">
<div class="welcome-card" style="max-width:28rem">
    <h1 class="page-head__title" style="font-size:1.25rem;margin-bottom:.75rem">Payment unavailable</h1>
    <p style="margin:0;color:var(--color-text-secondary, #475569)"><?= billo_e($m) ?></p>
    <p style="margin-top:1.25rem">
        <a class="btn btn--secondary" href="<?= billo_e(billo_url('/')) ?>">Home</a>
    </p>
</div>
</body>
</html>
