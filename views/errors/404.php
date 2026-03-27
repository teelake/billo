<?php
declare(strict_types=1);

/** @var string $title */
$pageTitle = $title ?? 'Page not found';
$title = $pageTitle . ' — billo';
ob_start();
?>
<section class="section" style="padding-top: 4rem; padding-bottom: 4rem;">
    <div class="container narrow">
        <p class="eyebrow">404</p>
        <h1 class="section__title">This page doesn’t exist</h1>
        <p class="section__lead">Check the URL, or head back to the homepage.</p>
        <div style="margin-top: 2rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <a class="btn btn--primary" href="<?= billo_e(billo_url('/')) ?>">Home</a>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/login')) ?>">Log in</a>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
