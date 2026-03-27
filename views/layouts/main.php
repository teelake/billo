<?php
declare(strict_types=1);
/** @var string $content */
/** @var string $title */
$pageTitle = $title ?? billo_brand_name();
$bodyClass = $bodyClass ?? '';
$brandTagline = billo_brand_tagline();
$metaDescription = $brandTagline !== ''
    ? $brandTagline
    : 'NRS-aligned invoicing for Nigerian freelancers and businesses. Simple to use, built for compliance and growth.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= billo_e($pageTitle) ?></title>
    <meta name="description" content="<?= billo_e($metaDescription) ?>">
    <meta name="theme-color" content="#16A34A">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="layout-main <?= billo_e($bodyClass) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="container site-header__inner">
        <a class="wordmark" href="<?= billo_e(billo_url('/')) ?>"><?= billo_e(billo_brand_name()) ?></a>
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="Open menu">
            <span class="nav-toggle__bar"></span>
            <span class="nav-toggle__bar"></span>
        </button>
        <nav id="primary-nav" class="site-nav" aria-label="Primary">
            <a class="site-nav__link" href="#features">Features</a>
            <a class="site-nav__link" href="#compliance">Compliance</a>
            <a class="site-nav__link" href="#pricing">Pricing</a>
            <div class="site-nav__cta">
                <a class="btn btn--ghost" href="<?= billo_e(billo_url('/login')) ?>">Log in</a>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/signup')) ?>">Get started</a>
            </div>
        </nav>
    </div>
</header>
<main id="main">
    <?= $content ?>
</main>
<footer class="site-footer">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <span class="wordmark wordmark--footer">billo</span>
            <p class="site-footer__tagline">Simple enough for a market trader. Strong enough for a CFO.</p>
        </div>
        <div class="site-footer__cols">
            <div>
                <h3 class="site-footer__heading">Product</h3>
                <ul class="site-footer__list">
                    <li><a href="#features">Features</a></li>
                    <li><a href="<?= billo_e(billo_url('/signup')) ?>">Sign up</a></li>
                </ul>
            </div>
            <div>
                <h3 class="site-footer__heading">Account</h3>
                <ul class="site-footer__list">
                    <li><a href="<?= billo_e(billo_url('/login')) ?>">Log in</a></li>
                </ul>
            </div>
        </div>
    </div>
    <div class="container site-footer__bottom">
        <p>&copy; <?= (int) date('Y') ?> <?= billo_e(billo_brand_name()) ?>. Built for Nigeria.</p>
    </div>
</footer>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
