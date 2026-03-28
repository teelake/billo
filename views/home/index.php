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
<section class="hero hero--premium">
    <div class="hero__aurora" aria-hidden="true"></div>
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
        <div class="hero__visual">
            <div class="hero__scene"<?= $heroImgOk ? '' : ' aria-hidden="true"' ?>>
                <div class="hero__float hero__float--coin" aria-hidden="true">
                    <svg viewBox="0 0 72 72" fill="none" xmlns="http://www.w3.org/2000/svg" class="hero__float-svg">
                        <defs>
                            <linearGradient id="hcoin" x1="10" y1="10" x2="62" y2="62"><stop stop-color="#16a34a"/><stop offset="1" stop-color="#1e3a8a"/></linearGradient>
                        </defs>
                        <circle cx="36" cy="36" r="32" stroke="url(#hcoin)" stroke-width="2.5" fill="rgba(255,255,255,0.72)"/>
                        <circle cx="36" cy="36" r="24" stroke="rgba(30,58,138,0.1)" stroke-width="1"/>
                        <text x="36" y="45" text-anchor="middle" font-family="Plus Jakarta Sans, Inter, system-ui, sans-serif" font-size="26" font-weight="800" fill="#15803d">₦</text>
                    </svg>
                </div>
                <div class="hero__float hero__float--check" aria-hidden="true">
                    <svg viewBox="0 0 64 64" class="hero__float-svg" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="6" y="6" width="52" height="52" rx="16" fill="rgba(22,163,74,0.12)" stroke="#16a34a" stroke-width="1.5"/>
                        <path d="M20 33l8 8 16-20" stroke="#15803d" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="hero__float hero__float--receipt" aria-hidden="true">
                    <svg viewBox="0 0 56 80" class="hero__float-svg" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M8 4h40v68l-6-4-6 4-6-4-6 4-6-4-6 4V4z" fill="rgba(255,255,255,0.85)" stroke="#cbd5e1" stroke-width="1.5"/>
                        <path d="M16 18h24M16 28h20M16 38h24M16 48h14" stroke="#94a3b8" stroke-width="2" stroke-linecap="round"/>
                        <path d="M16 60h24" stroke="#1e3a8a" stroke-width="2.5" stroke-linecap="round"/>
                    </svg>
                </div>
                <div class="hero__float hero__float--chart" aria-hidden="true">
                    <svg viewBox="0 0 70 56" class="hero__float-svg" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="4" width="62" height="48" rx="10" fill="rgba(30,58,138,0.06)" stroke="rgba(30,58,138,0.2)" stroke-width="1"/>
                        <path d="M14 38V22M28 38V14M42 38V26M56 38V18" stroke-linecap="round" stroke-width="5" stroke="#1e3a8a" opacity="0.35"/>
                        <path d="M14 38V24M28 38V16M42 38V28M56 38V20" stroke-linecap="round" stroke-width="5" stroke="url(#hbar)"/>
                        <defs><linearGradient id="hbar" x1="14" y1="40" x2="56" y2="12"><stop stop-color="#16a34a"/><stop offset="1" stop-color="#facc15"/></linearGradient></defs>
                    </svg>
                </div>
                <div class="hero__float hero__float--card" aria-hidden="true">
                    <svg viewBox="0 0 88 56" class="hero__float-svg" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="4" y="8" width="80" height="40" rx="10" fill="rgba(30,58,138,0.92)"/>
                        <rect x="4" y="8" width="80" height="12" rx="10" fill="#facc15" opacity="0.95"/>
                        <rect x="4" y="18" width="80" height="6" fill="#facc15" opacity="0.95"/>
                        <rect x="12" y="32" width="28" height="5" rx="2" fill="rgba(255,255,255,0.35)"/>
                        <rect x="12" y="40" width="18" height="4" rx="2" fill="rgba(255,255,255,0.2)"/>
                    </svg>
                </div>
                <div class="hero__float hero__float--ring" aria-hidden="true">
                    <svg viewBox="0 0 48 48" class="hero__float-svg hero__float-svg--spin" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="24" cy="24" r="20" stroke="rgba(22,163,74,0.25)" stroke-width="1.5"/>
                        <circle cx="24" cy="24" r="12" stroke="rgba(30,58,138,0.2)" stroke-width="1.5" stroke-dasharray="4 6"/>
                    </svg>
                </div>
                <div class="hero__frame<?= $heroImgOk ? ' hero__frame--photo' : '' ?>">
            <?php if ($heroImgOk): ?>
                <img class="hero__photo" src="<?= billo_e($heroImg) ?>" alt="<?= billo_e(billo_landing('hero_image_alt', billo_brand_name() . ' — product preview')) ?>" width="560" height="420" loading="lazy" decoding="async">
            <?php else: ?>
            <article class="invoice-card invoice-card--hero">
                <div class="invoice-card__sheen" aria-hidden="true"></div>
                <header class="invoice-card__head">
                    <div class="invoice-card__meta">
                        <span class="invoice-card__eyebrow">Invoice</span>
                        <span class="invoice-card__number">INV-2048</span>
                    </div>
                    <span class="invoice-card__badge invoice-card__badge--paid">Paid</span>
                </header>
                <div class="invoice-card__party">
                    <span class="invoice-card__logo" aria-hidden="true"></span>
                    <div class="invoice-card__party-text">
                        <span class="invoice-card__company">Acme Studio Ltd</span>
                        <span class="invoice-card__hint">Victoria Island · NGN</span>
                    </div>
                </div>
                <div class="invoice-card__ledger">
                    <div class="invoice-card__ledger-head">
                        <span>Description</span>
                        <span>Amount</span>
                    </div>
                    <div class="invoice-card__ledger-row">
                        <span>Brand &amp; invoice template</span>
                        <span>₦ 185,000</span>
                    </div>
                    <div class="invoice-card__ledger-row">
                        <span>Compliance review</span>
                        <span>₦ 92,500</span>
                    </div>
                    <div class="invoice-card__ledger-row invoice-card__ledger-row--accent">
                        <span>VAT (7.5%)</span>
                        <span>₦ 20,813</span>
                    </div>
                </div>
                <div class="invoice-card__total-row">
                    <span class="invoice-card__total-label">Total received</span>
                    <span class="invoice-card__total-value">₦ 298,313.00</span>
                </div>
                <div class="invoice-card__footer">
                    <span class="invoice-card__pill">NRS-ready</span>
                    <span class="invoice-card__pill invoice-card__pill--ghost">PDF · Email</span>
                    <span class="invoice-card__spark" aria-hidden="true"></span>
                </div>
            </article>
            <?php endif; ?>
                </div>
            </div>
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
