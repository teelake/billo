<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $token */
$error = $error ?? '';
$title = 'New password — billo';
ob_start();
?>
<div class="auth-card">
    <h1 class="auth-card__title">Choose a new password</h1>
    <p class="auth-card__subtitle">Same rules as signup: 10+ characters, letters and numbers, plus an uppercase letter or a symbol.</p>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/reset-password')) ?>" novalidate data-password-reset-form>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <input type="hidden" name="token" value="<?= billo_e($token) ?>">
        <div class="field">
            <label class="label" for="password">New password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="password-hint password-strength">
            <p class="hint" id="password-hint">Letters, numbers, and an uppercase letter or symbol.</p>
            <p class="field-feedback" id="password-strength" role="status" aria-live="polite"></p>
        </div>
        <div class="field">
            <label class="label" for="password_confirm">Confirm password</label>
            <input class="input" id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required minlength="10" maxlength="128" aria-describedby="password-confirm-feedback">
            <p class="field-feedback" id="password-confirm-feedback" role="status" aria-live="polite"></p>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Update password</button>
    </form>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/auth.php';
