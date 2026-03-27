<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, string> $values */
/** @var list<string> $keys */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Landing content — billo';
ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'platform';
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <div class="page-head">
            <div>
                <h1 class="page-head__title">Marketing & landing page</h1>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/dashboard')) ?>">Dashboard</a>
        </div>

        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert" style="margin-bottom:1rem"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <div class="welcome-card">
            <form method="post" action="<?= billo_e(billo_url('/platform/landing')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($keys as $fullKey): ?>
                    <?php
                    $short = substr($fullKey, strlen('landing.'));
                    $fieldName = str_replace('.', '_', $fullKey);
                    $val = $values[$short] ?? '';
                    $label = ucwords(str_replace('_', ' ', $short));
                    ?>
                    <div class="field">
                        <label class="label" for="<?= billo_e($fieldName) ?>"><?= billo_e($label) ?></label>
                        <textarea class="input" id="<?= billo_e($fieldName) ?>" name="<?= billo_e($fieldName) ?>" rows="<?= str_contains($short, 'meta_title') ? 2 : 3 ?>"><?= billo_e($val) ?></textarea>
                    </div>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();
$bodyClass = 'app-body';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= billo_e($title) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="<?= billo_e($bodyClass) ?>">
<?= $content ?>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
