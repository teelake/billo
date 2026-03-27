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
<div class="auth-card">
    <h1 class="auth-card__title"><?= $isInvite ? 'Join your team' : 'Create your workspace' ?></h1>
    <p class="auth-card__subtitle">
        <?= $isInvite
            ? 'You’re invited to ' . billo_e((string) ($invite['organization_name'] ?? '')) . '.'
            : 'Fast signup—you can add branding and tax settings after you’re in.' ?>
    </p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/signup')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <div class="field">
            <label class="label" for="name">Your name</label>
            <input class="input" id="name" name="name" type="text" autocomplete="name" required maxlength="120" value="<?= billo_e($name) ?>">
        </div>
        <?php if (!$isInvite): ?>
            <div class="field">
                <label class="label" for="organization_name">Organization name</label>
                <input class="input" id="organization_name" name="organization_name" type="text" autocomplete="organization" required maxlength="200" value="<?= billo_e($organization_name) ?>">
            </div>
        <?php endif; ?>
        <div class="field">
            <label class="label" for="email">Work email</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= billo_e($email) ?>"<?= $isInvite ? ' readonly aria-readonly="true"' : '' ?>>
        </div>
        <div class="field-grid">
            <div class="field">
                <label class="label" for="password">Password</label>
                <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="10" aria-describedby="password-hint">
                <p class="hint" id="password-hint">At least 10 characters.</p>
            </div>
            <div class="field">
                <label class="label" for="password_confirm">Confirm password</label>
                <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="10">
            </div>
        </div>
        <button class="btn btn--primary btn--block" type="submit"><?= $isInvite ? 'Join organization' : 'Create account' ?></button>
    </form>
    <p class="auth-card__footer">Already have an account? <a href="<?= billo_e(billo_url('/login')) ?>">Log in</a></p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/auth.php';
