<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $name */
/** @var string $email */
/** @var string $organization_name */
/** @var array<string, mixed>|null $invite */
$error = $error ?? '';
$organization_name = $organization_name ?? '';
$invite = isset($invite) && is_array($invite) ? $invite : null;
$isInvite = $invite !== null;
$title = $isInvite ? 'Join organization — billo' : 'Sign up — billo';
ob_start();
?>
<div class="auth-card auth-card--signup">
    <h1 class="auth-card__title"><?= $isInvite ? 'Join your team' : 'Create your workspace' ?></h1>
    <p class="auth-card__subtitle">
        <?= $isInvite
            ? 'You’re invited to ' . billo_e((string) ($invite['organization_name'] ?? '')) . '.'
            : 'Fast signup—you can add branding and tax settings after you’re in.' ?>
    </p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/signup')) ?>" novalidate data-signup-form>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <?php if ($isInvite): ?>
            <div class="field">
                <label class="label" for="email">Work email</label>
                <input class="input" id="email" name="email" type="email" inputmode="email" autocomplete="email" required value="<?= billo_e($email) ?>" aria-describedby="email-feedback" readonly aria-readonly="true">
                <p class="field-feedback" id="email-feedback" role="status" aria-live="polite"></p>
            </div>
            <div class="field">
                <label class="label" for="name">Your name</label>
                <input class="input" id="name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= billo_e($name) ?>" aria-describedby="name-feedback">
                <p class="field-feedback" id="name-feedback" role="status" aria-live="polite"></p>
            </div>
        <?php else: ?>
            <div class="field">
                <label class="label" for="email">Work email</label>
                <input class="input" id="email" name="email" type="email" inputmode="email" autocomplete="email" required value="<?= billo_e($email) ?>" aria-describedby="email-feedback">
                <p class="field-feedback" id="email-feedback" role="status" aria-live="polite"></p>
            </div>
            <div class="field">
                <label class="label" for="organization_name">Organization name</label>
                <input class="input" id="organization_name" name="organization_name" type="text" autocomplete="organization" required maxlength="200" value="<?= billo_e($organization_name) ?>" aria-describedby="organization-feedback">
                <p class="field-feedback" id="organization-feedback" role="status" aria-live="polite"></p>
            </div>
            <div class="field">
                <label class="label" for="name">Your name</label>
                <input class="input" id="name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= billo_e($name) ?>" aria-describedby="name-feedback">
                <p class="field-feedback" id="name-feedback" role="status" aria-live="polite"></p>
            </div>
        <?php endif; ?>
        <div class="signup-password-block">
            <div class="field-grid field-grid--signup-pw">
                <div class="field">
                    <label class="label" for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="password-hint password-strength">
                </div>
                <div class="field">
                    <label class="label" for="password_confirm">Confirm password</label>
                    <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="password-confirm-feedback">
                    <p class="field-feedback" id="password-confirm-feedback" role="status" aria-live="polite"></p>
                </div>
            </div>
            <p class="hint signup-password-block__hint" id="password-hint">10+ characters, letters and numbers, plus an uppercase letter or symbol (e.g. ! @ #).</p>
            <p class="field-feedback" id="password-strength" role="status" aria-live="polite"></p>
        </div>
        <button class="btn btn--primary btn--block" type="submit"><?= $isInvite ? 'Join organization' : 'Create account' ?></button>
    </form>
    <p class="auth-card__footer">Already have an account? <a href="<?= billo_e(billo_url('/login')) ?>">Log in</a></p>
</div>
<?php
$content = ob_get_clean();
$auth_body_class = 'layout-auth--signup';
require dirname(__DIR__) . '/layouts/auth.php';
