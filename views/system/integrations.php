<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

/** @var list<string> $db_setting_keys */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */
/** @var string $error */
/** @var string $success */

$error = $error ?? '';
$success = $success ?? '';
$title = 'NRS integration — billo';

$dbKeys = $db_setting_keys ?? [];
$hasDb = static function (string $k) use ($dbKeys): bool {
    return in_array($k, $dbKeys, true);
};

$nrsBase = (string) Config::get('nrs.api_base_url', '');
$nrsPath = (string) Config::get('nrs.invoices_path', '/invoices');
$tokFromDb = $hasDb('nrs.bearer_token');

ob_start();
?>
<section class="app-dashboard">
    <?php
    $active = 'system-integrations';
    $user_email = (string) \App\Core\Session::get('user_email', '');
    include dirname(__DIR__) . '/partials/app_topbar.php';
    ?>
    <div class="container app-dashboard__body">
        <?php if ($error !== ''): ?>
            <div class="alert alert--error" role="alert" style="margin-bottom:1rem"><?= billo_e($error) ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="alert alert--success" role="alert" style="margin-bottom:1rem"><?= billo_e($success) ?></div>
        <?php endif; ?>

        <div class="page-head">
            <div>
                <p class="eyebrow eyebrow--dark">platform operator</p>
                <h1 class="page-head__title">NRS integration</h1>
                <p class="page-head__lead">Configure the outbound API once for the whole platform. Workspaces only turn sync on and supply their tenant reference if their <strong>subscription plan</strong> allows NRS.</p>
            </div>
            <a class="btn btn--secondary" href="<?= billo_e(billo_url('/system/plans')) ?>">Subscription plans</a>
        </div>

        <form class="config-form" method="post" action="<?= billo_e(billo_url('/system/integrations')) ?>">
            <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">

            <fieldset class="config-form__section welcome-card">
                <legend class="config-form__legend">API connection</legend>
                <div class="config-form__grid">
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">NRS API base URL (no trailing slash)</span>
                        <input type="url" name="cfg_nrs_api_base_url" class="input" value="<?= billo_e($nrsBase) ?>" maxlength="500" placeholder="https://api.example.com/v1" autocomplete="off">
                    </label>
                    <label class="config-form__field">
                        <span class="config-form__label">Invoices path</span>
                        <input type="text" name="cfg_nrs_invoices_path" class="input" value="<?= billo_e($nrsPath) ?>" maxlength="190" placeholder="/invoices" autocomplete="off">
                    </label>
                    <label class="config-form__field config-form__field--full">
                        <span class="config-form__label">Bearer token (optional)</span>
                        <input type="password" name="cfg_nrs_bearer_token" class="input" value="" maxlength="2000" autocomplete="off" placeholder="<?= $tokFromDb ? 'Leave blank to keep the saved token' : 'Paste token if the NRS endpoint requires it' ?>">
                        <span class="hint" style="display:block;margin-top:0.35rem"><?= $tokFromDb ? 'A token is stored in platform settings.' : 'Not stored yet.' ?></span>
                        <label class="hint" style="display:block;margin-top:0.5rem"><input type="checkbox" name="clr_nrs_bearer_token" value="1"> Clear saved token</label>
                    </label>
                </div>
                <p class="hint" style="margin:1rem 0 0">Sent invoices are POSTed to <code>{base URL}{path}</code> with JSON <code>invoice</code> plus <code>nrs_tenant_external_id</code> from each workspace.</p>
                <button type="submit" class="btn btn--primary" style="margin-top:1rem">Save NRS settings</button>
            </fieldset>
        </form>
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
