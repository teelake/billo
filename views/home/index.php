<?php
declare(strict_types=1);

$title = 'billo — FIRS-ready invoicing for Nigeria';
ob_start();
?>
<section class="hero">
    <div class="container hero__inner">
        <div class="hero__copy">
            <p class="eyebrow">Nigeria · SMEs · Enterprises</p>
            <h1 class="hero__title">Invoicing that stays on the right side of <span class="text-gradient">FIRS</span>.</h1>
            <p class="hero__subtitle">
                Create compliant invoices, get paid faster, and give your brand a premium edge—whether you’re a solo freelancer or a growing company.
            </p>
            <div class="hero__actions">
                <a class="btn btn--primary btn--lg" href="<?= billo_e(billo_url('/signup')) ?>">Start free</a>
                <a class="btn btn--secondary btn--lg" href="<?= billo_e(billo_url('/login')) ?>">Log in</a>
            </div>
            <p class="hero__note">Finish setup after you sign up—get working in minutes.</p>
        </div>
        <div class="hero__visual" aria-hidden="true">
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
        </div>
    </div>
</section>

<section id="features" class="section">
    <div class="container">
        <header class="section__header">
            <h2 class="section__title">Everything you need to bill with confidence</h2>
            <p class="section__lead">Clean URLs, multi-currency, branded templates—and room to grow into deeper compliance workflows.</p>
        </header>
        <div class="feature-grid">
            <article class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">✓</div>
                <h3 class="feature-card__title">Brand-perfect invoices</h3>
                <p class="feature-card__text">Templates, fonts, colors, and your logo—so every PDF feels bespoke.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon feature-card__icon--blue" aria-hidden="true">₦</div>
                <h3 class="feature-card__title">Multi-currency</h3>
                <p class="feature-card__text">Bill international clients without losing clarity at home.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon feature-card__icon--gold" aria-hidden="true">◎</div>
                <h3 class="feature-card__title">Built for teams</h3>
                <p class="feature-card__text">Organizations, invites, and roles—owner to viewer.</p>
            </article>
            <article class="feature-card">
                <div class="feature-card__icon" aria-hidden="true">⎙</div>
                <h3 class="feature-card__title">Share anywhere</h3>
                <p class="feature-card__text">PDF, image, email, WhatsApp-friendly flows (coming next).</p>
            </article>
        </div>
    </div>
</section>

<section id="compliance" class="section section--tint">
    <div class="container split">
        <div>
            <h2 class="section__title">Compliance-first, optional by design</h2>
            <p class="section__lead">
                billo is positioned for FIRS-aligned operations. Not everyone needs full tax mode on day one—toggle what applies when your business is ready.
            </p>
            <ul class="checklist">
                <li>Nigeria-focused workflows and copy</li>
                <li>Integration-ready architecture for e-filing partners</li>
                <li>Tenant-isolated data by organization</li>
            </ul>
        </div>
        <div class="callout-card">
            <p class="callout-card__label">Trust layer</p>
            <p class="callout-card__text">
                Deep blue for stability, green for growth—UI crafted for outdoor screens and busy shops, not just boardrooms.
            </p>
        </div>
    </div>
</section>

<section id="pricing" class="section">
    <div class="container narrow">
        <header class="section__header">
            <h2 class="section__title">Pricing that scales with you</h2>
            <p class="section__lead">Plans, limits, and Stripe billing are configured from the platform admin—so you always ship the right offer.</p>
        </header>
        <div class="pricing-card">
            <h3 class="pricing-card__title">Launch offers</h3>
            <p class="pricing-card__text">Free tier and paid plans with invoice volume, seats, and storage limits—announced when you open the waitlist.</p>
            <a class="btn btn--primary" href="<?= billo_e(billo_url('/signup')) ?>">Create your organization</a>
        </div>
    </div>
</section>

<section class="section section--cta">
    <div class="container cta-banner">
        <div>
            <h2 class="cta-banner__title">Ready for premium invoicing?</h2>
            <p class="cta-banner__text">Sign up in seconds. Finish company details when you’re inside.</p>
        </div>
        <a class="btn btn--on-dark btn--lg" href="<?= billo_e(billo_url('/signup')) ?>">Get started</a>
    </div>
</section>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/main.php';
