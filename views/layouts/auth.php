<?php
declare(strict_types=1);
/** @var string $content */
/** @var string $title */
$pageTitle = $title ?? 'billo';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= billo_e($pageTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1E3A8A">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="layout-auth">
<a class="skip-link" href="#auth-panel">Skip to form</a>
<div class="auth-shell">
    <div class="auth-shell__brand">
        <a class="wordmark wordmark--light" href="<?= billo_e(billo_url('/')) ?>">billo</a>
        <p class="auth-shell__lead">FIRS-ready invoicing for Nigerian businesses—without the spreadsheet.</p>
        <ul class="auth-shell__bullets">
            <li>Multi-currency, bespoke PDFs</li>
            <li>Built for teams &amp; organizations</li>
            <li>Scale from freelancer to enterprise</li>
        </ul>
    </div>
    <div class="auth-shell__panel" id="auth-panel">
        <?= $content ?>
    </div>
</div>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
