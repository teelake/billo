<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var string $error */
/** @var string $success */
/** @var string $email */
/** @var bool $invited */
$error = $error ?? '';
$email = $email ?? '';
$success = $success ?? '';
$invited = !empty($invited);
$title = 'Log in — billo';
ob_start();
?>
<div class="auth-card">
    <h1 class="auth-card__title">Welcome back</h1>
    <p class="auth-card__subtitle">Sign in to your organization.</p>
    <?php if ($invited): ?>
        <div class="alert alert--success" role="status">You have a pending invitation. Sign in with the invited email, or <a href="<?= billo_e(billo_url('/signup?invited=1')) ?>">create an account</a> if you’re new.</div>
    <?php endif; ?>
    <?php if ($error !== ''): ?>
        <div class="alert alert--error" role="alert"><?= billo_e($error) ?></div>
    <?php endif; ?>
    <?php if ($success !== ''): ?>
        <div class="alert alert--success" role="alert"><?= billo_e($success) ?></div>
    <?php endif; ?>
    <form class="form" method="post" action="<?= billo_e(billo_url('/login')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
        <div class="field">
            <label class="label" for="email">Email</label>
            <input class="input" id="email" name="email" type="email" autocomplete="email" required value="<?= billo_e($email) ?>">
        </div>
        <div class="field">
            <label class="label" for="password">Password</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Log in</button>
    </form>
    <p class="auth-card__meta"><a href="<?= billo_e(billo_url('/forgot-password')) ?>">Forgot password?</a></p>
    <p class="auth-card__footer">New to billo? <a href="<?= billo_e(billo_url($invited ? '/signup?invited=1' : '/signup')) ?>">Create an account</a></p>
</div>
<?php
$content = ob_get_clean();
require dirname(__DIR__) . '/layouts/auth.php';
