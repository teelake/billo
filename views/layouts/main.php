<?php
declare(strict_types=1);
/** @var string $content */
/** @var string $title */
$pageTitle = $title ?? billo_brand_name();
$bodyClass = $bodyClass ?? '';
$landingHasTrusted = $landingHasTrusted ?? false;
$landingHasTestimonials = $landingHasTestimonials ?? false;
$landingHasFaqs = $landingHasFaqs ?? false;
$brandTagline = billo_brand_tagline();
$heroSubFallback = 'Professional invoicing for Nigerian businesses—get paid faster with branded documents and room to grow into deeper compliance.';
$heroSubPlain = trim(preg_replace('/\s+/u', ' ', strip_tags(billo_landing('hero_subtitle', $heroSubFallback)))) ?: $heroSubFallback;
$metaDescription = $brandTagline !== '' ? $brandTagline : $heroSubPlain;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
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
            <a class="site-nav__link" href="<?= billo_e(billo_url('/#features')) ?>">Features</a>
            <a class="site-nav__link" href="<?= billo_e(billo_url('/#compliance')) ?>">Compliance</a>
            <a class="site-nav__link" href="<?= billo_e(billo_url('/#pricing')) ?>">Pricing</a>
            <?php if ($landingHasTrusted): ?>
                <a class="site-nav__link" href="<?= billo_e(billo_url('/#trusted')) ?>">Trusted by</a>
            <?php endif; ?>
            <?php if ($landingHasTestimonials): ?>
                <a class="site-nav__link" href="<?= billo_e(billo_url('/#testimonials')) ?>">Stories</a>
            <?php endif; ?>
            <?php if ($landingHasFaqs): ?>
                <a class="site-nav__link" href="<?= billo_e(billo_url('/#faqs')) ?>">FAQs</a>
            <?php endif; ?>
            <div class="site-nav__cta">
                <a class="btn btn--ghost" href="<?= billo_e(billo_url('/login')) ?>">Log in</a>
                <a class="btn btn--primary" href="<?= billo_e(billo_url('/signup')) ?>"><?= billo_e(billo_landing('pricing_cta_label', 'Start free')) ?></a>
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
            <span class="wordmark wordmark--footer"><?= billo_e(billo_brand_name()) ?></span>
            <p class="site-footer__tagline"><?= billo_e(billo_landing('footer_tagline', 'Simple enough for a market trader. Strong enough for a CFO.')) ?></p>
        </div>
        <div class="site-footer__cols">
            <div>
                <h3 class="site-footer__heading">Product</h3>
                <ul class="site-footer__list">
                    <li><a href="<?= billo_e(billo_url('/#features')) ?>">Features</a></li>
                    <li><a href="<?= billo_e(billo_url('/#compliance')) ?>">Compliance</a></li>
                    <li><a href="<?= billo_e(billo_url('/#pricing')) ?>">Pricing</a></li>
                    <li><a href="<?= billo_e(billo_url('/signup')) ?>">Sign up</a></li>
                </ul>
            </div>
            <div>
                <h3 class="site-footer__heading">Account</h3>
                <ul class="site-footer__list">
                    <li><a href="<?= billo_e(billo_url('/login')) ?>">Log in</a></li>
                    <?php if (billo_is_platform_admin() || billo_is_system_admin()): ?>
                        <li><a href="<?= billo_e(billo_url('/platform/landing')) ?>">Edit landing page</a></li>
                    <?php endif; ?>
                    <?php if (billo_is_system_admin()): ?>
                        <li><a href="<?= billo_e(billo_url('/system')) ?>">System admin</a></li>
                    <?php endif; ?>
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
