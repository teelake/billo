<?php
declare(strict_types=1);

/** @var list<array<string, mixed>> $landing_plans */
/** @var array<int, list<array<string, mixed>>> $plan_features */
/** @var list<array<string, mixed>> $landing_faqs */
/** @var list<array<string, mixed>> $landing_logos */
/** @var list<array<string, mixed>> $landing_testimonials */

$landing_plans = $landing_plans ?? [];
$plan_features = $plan_features ?? [];
$landing_faqs = $landing_faqs ?? [];
$landing_logos = $landing_logos ?? [];
$landing_testimonials = $landing_testimonials ?? [];

$heroImg = trim(billo_landing('hero_image_url', ''));
$heroImgOk = $heroImg !== '' && (str_starts_with($heroImg, 'https://') || str_starts_with($heroImg, 'http://') || str_starts_with($heroImg, '/'));

$title = billo_landing('meta_title', 'billo — NRS-compliant invoice software for Nigeria');
ob_start();
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__copy">
            <p class="eyebrow"><?= billo_e(billo_landing('hero_eyebrow', 'Nigeria · SMEs · Enterprises')) ?></p>
            <h1 class="hero__title">
                <span class="text-gradient"><?= billo_e(billo_landing('hero_title_line1', 'NRS-compliant')) ?></span>
                <?= billo_e(billo_landing('hero_title_line2', 'invoice software')) ?>
            </h1>
            <div class="hero__subtitle landing-rich"><?= billo_landing_html('hero_subtitle', '<p>Create compliant invoices, get paid faster, and give your brand a premium edge—whether you’re a solo freelancer or a growing company.</p>') ?></div>
            <div class="hero__actions">
                <a class="btn btn--primary btn--lg" href="<?= billo_e(billo_url('/signup')) ?>">Start free</a>
                <a class="btn btn--secondary btn--lg" href="<?= billo_e(billo_url('/login')) ?>">Log in</a>
            </div>
            <p class="hero__note"><?= billo_e(billo_landing('hero_note', 'Finish setup after you sign up—get working in minutes.')) ?></p>
        </div>
        <div class="hero__visual<?= $heroImgOk ? ' hero__visual--photo' : '' ?>" aria-hidden="<?= $heroImgOk ? 'false' : 'true' ?>">
            <?php if ($heroImgOk): ?>
                <img class="hero__photo" src="<?= billo_e($heroImg) ?>" alt="" width="560" height="420" loading="lazy" decoding="async">
            <?php else: ?>
            <div class="invoice-card">
                <div class="invoice-card__header">
                    <span class="invoice-card__logo"></span>
                    <span class="invoice-card__badge">Paid</span>
                </div>
                <div class="invoice-card__rows">
                    <div class="invoice-card__row"><span></span><span></span></div>
                    <div class="invoice-card__row"><span></span><span></span></div>
                    <div class="invoice-card__row invoice-card__row--accent"><span></span><span></span></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="features" class="section">
    <div class="container">
        <header class="section__header">
            <h2 class="section__title"><?= billo_e(billo_landing('features_title', 'Everything you need to bill with confidence')) ?></h2>
            <div class="section__lead landing-rich"><?= billo_landing_html('features_lead', '<p>Clean URLs, multi-currency, branded templates—and room to grow into deeper compliance workflows.</p>') ?></div>
        </header>
        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">✓</div>
                <h3 class="feature-card__title"><?= billo_e(billo_landing('feature_1_title', 'Brand-perfect invoices')) ?></h3>
                <div class="feature-card__text landing-rich"><?= billo_landing_html('feature_1_text', '<p>Templates, fonts, colors, and your logo—so every PDF feels bespoke.</p>') ?></div>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon feature-card__icon--blue" aria-hidden="true">₦</div>
                <h3 class="feature-card__title"><?= billo_e(billo_landing('feature_2_title', 'Multi-currency')) ?></h3>
                <div class="feature-card__text landing-rich"><?= billo_landing_html('feature_2_text', '<p>Bill international clients without losing clarity at home.</p>') ?></div>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon feature-card__icon--gold" aria-hidden="true">◎</div>
                <h3 class="feature-card__title"><?= billo_e(billo_landing('feature_3_title', 'Built for teams')) ?></h3>
                <div class="feature-card__text landing-rich"><?= billo_landing_html('feature_3_text', '<p>Organizations, invites, and roles—owner to viewer.</p>') ?></div>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">⎙</div>
                <h3 class="feature-card__title"><?= billo_e(billo_landing('feature_4_title', 'Share anywhere')) ?></h3>
                <div class="feature-card__text landing-rich"><?= billo_landing_html('feature_4_text', '<p>Print-ready invoices and email-to-client from your dashboard—PDF via your browser where you need it.</p>') ?></div>
            </article>
        </div>
    </div>
</section>

<section id="compliance" class="section section--tint">
    <div class="container split">
        <div>
            <h2 class="section__title"><?= billo_e(billo_landing('compliance_title', 'Compliance-first, optional by design')) ?></h2>
            <div class="section__lead landing-rich">
                <?= billo_landing_html('compliance_lead', '<p>billo is positioned for NRS-aligned invoicing. Not everyone needs full tax mode on day one—toggle what applies when your business is ready.</p>') ?>
            </div>
            <ul class="checklist">
                <li><?= billo_e(billo_landing('compliance_li1', 'Nigeria-focused workflows and copy')) ?></li>
                <li><?= billo_e(billo_landing('compliance_li2', 'Integration-ready architecture for e-filing partners')) ?></li>
                <li><?= billo_e(billo_landing('compliance_li3', 'Tenant-isolated data by organization')) ?></li>
            </ul>
        </div>
        <div class="callout-card">
            <p class="callout-card__label"><?= billo_e(billo_landing('callout_label', 'Trust layer')) ?></p>
            <div class="callout-card__text landing-rich">
                <?= billo_landing_html('callout_text', '<p>Deep blue for stability, green for growth—UI crafted for outdoor screens and busy shops, not just boardrooms.</p>') ?>
            </div>
        </div>
    </div>
</section>

<section id="pricing" class="section">
    <div class="container">
        <header class="section__header">
            <h2 class="section__title"><?= billo_e(billo_landing('pricing_title', 'Pricing that scales with you')) ?></h2>
            <div class="section__lead landing-rich"><?= billo_landing_html('pricing_lead', '<p>Plans, limits, and your payment provider are configured from the platform—so you always ship the right offer.</p>') ?></div>
        </header>
        <?php if (count($landing_plans) > 0): ?>
            <div class="plan-grid plan-grid--marketing">
                <?php foreach ($landing_plans as $plan): ?>
                    <?php
                    $pid = (int) ($plan['id'] ?? 0);
                    $items = $pid > 0 ? ($plan_features[$pid] ?? []) : [];
                    $desc = trim((string) ($plan['description'] ?? ''));
                    ?>
                    <article class="plan-card plan-card--marketing">
                        <h3 class="plan-card__name"><?= billo_e((string) ($plan['name'] ?? 'Plan')) ?></h3>
                        <p class="plan-card__price"><?= billo_e(billo_format_plan_price_line($plan)) ?></p>
                        <?php if ($desc !== ''): ?>
                            <p class="plan-card__desc"><?= billo_e($desc) ?></p>
                        <?php endif; ?>
                        <?php if ($items !== []): ?>
                            <ul class="plan-card__features">
                                <?php foreach ($items as $it): ?>
                                    <li>
                                        <strong><?= billo_e((string) ($it['label'] ?? '')) ?></strong>
                                        <?php
                                        $det = trim((string) ($it['detail'] ?? ''));
                                        if ($det !== ''): ?>
                                            <span class="plan-card__feature-detail"><?= billo_e($det) ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <a class="btn btn--primary btn--block" href="<?= billo_e(billo_url('/signup')) ?>">Get started</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="narrow">
                <div class="pricing-card">
                    <h3 class="pricing-card__title"><?= billo_e(billo_landing('pricing_card_title', 'Launch offers')) ?></h3>
                    <div class="pricing-card__text landing-rich"><?= billo_landing_html('pricing_card_text', '<p>Free tier and paid plans with invoice volume, seats, and storage limits—announced when you open the waitlist.</p>') ?></div>
                    <a class="btn btn--primary" href="<?= billo_e(billo_url('/signup')) ?>">Create your organization</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if (count($landing_logos) > 0): ?>
<section id="trusted" class="section section--tint">
    <div class="container">
        <header class="section__header">
            <h2 class="section__title"><?= billo_e(billo_landing('trusted_section_title', 'Trusted by teams like yours')) ?></h2>
        </header>
        <ul class="trusted-strip" role="list">
            <?php foreach ($landing_logos as $logo): ?>
                <?php
                $src = trim((string) ($logo['image_url'] ?? ''));
                $name = (string) ($logo['name'] ?? '');
                $href = trim((string) ($logo['website_url'] ?? ''));
                if ($src === '') {
                    continue;
                }
                ?>
                <li class="trusted-strip__item">
                    <?php if ($href !== ''): ?>
                        <a class="trusted-strip__link" href="<?= billo_e($href) ?>" rel="noopener noreferrer" target="_blank">
                            <img class="trusted-strip__img" src="<?= billo_e($src) ?>" alt="<?= billo_e($name) ?>" loading="lazy" decoding="async">
                        </a>
                    <?php else: ?>
                        <img class="trusted-strip__img" src="<?= billo_e($src) ?>" alt="<?= billo_e($name) ?>" loading="lazy" decoding="async">
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
<?php endif; ?>

<?php if (count($landing_testimonials) > 0): ?>
<section id="testimonials" class="section">
    <div class="container">
        <header class="section__header">
            <h2 class="section__title"><?= billo_e(billo_landing('testimonials_section_title', 'What customers say')) ?></h2>
        </header>
        <div class="testimonial-grid">
            <?php foreach ($landing_testimonials as $t): ?>
                <?php
                $quote = (string) ($t['quote_html'] ?? '');
                $aname = (string) ($t['author_name'] ?? '');
                $adet = trim((string) ($t['author_detail'] ?? ''));
                $pic = trim((string) ($t['portrait_url'] ?? ''));
                ?>
                <blockquote class="testimonial-card">
                    <?php if ($pic !== '' && (str_starts_with($pic, 'http') || str_starts_with($pic, '/'))): ?>
                        <img class="testimonial-card__avatar" src="<?= billo_e($pic) ?>" alt="" width="56" height="56" loading="lazy">
                    <?php endif; ?>
                    <div class="testimonial-card__quote landing-rich"><?= billo_sanitize_landing_html($quote) ?></div>
                    <footer class="testimonial-card__meta">
                        <cite class="testimonial-card__author"><?= billo_e($aname) ?></cite>
                        <?php if ($adet !== ''): ?>
                            <span class="testimonial-card__role"><?= billo_e($adet) ?></span>
                        <?php endif; ?>
                    </footer>
                </blockquote>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (count($landing_faqs) > 0): ?>
<section id="faqs" class="section section--tint">
    <div class="container narrow-wide">
        <header class="section__header">
            <h2 class="section__title"><?= billo_e(billo_landing('faqs_section_title', 'Frequently asked questions')) ?></h2>
        </header>
        <div class="faq-accordion" role="list">
            <?php foreach ($landing_faqs as $i => $faq): ?>
                <?php
                $q = (string) ($faq['question'] ?? '');
                $a = (string) ($faq['answer_html'] ?? '');
                ?>
                <details class="faq-item" role="listitem"<?= $i === 0 ? ' open' : '' ?>>
                    <summary class="faq-item__q"><?= billo_e($q) ?></summary>
                    <div class="faq-item__a landing-rich"><?= billo_sanitize_landing_html($a) ?></div>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="section section--cta">
    <div class="container cta-banner">
        <div>
            <h2 class="cta-banner__title"><?= billo_e(billo_landing('cta_title', 'Ready for premium invoicing?')) ?></h2>
            <div class="cta-banner__text landing-rich"><?= billo_landing_html('cta_text', '<p>Sign up in seconds. Finish company details when you’re inside.</p>') ?></div>
        </div>
        <a class="btn btn--on-dark btn--lg" href="<?= billo_e(billo_url('/signup')) ?>">Get started</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
