<?php
declare(strict_types=1);

use App\Core\Csrf;

/** @var array<string, string> $values */
/** @var list<string> $copy_keys */
/** @var list<string> $quill_keys */
/** @var list<array<string, mixed>> $faqs */
/** @var list<array<string, mixed>> $trusted_logos */
/** @var list<array<string, mixed>> $testimonials */
/** @var string $user_name */
/** @var string $role */
/** @var bool $show_team_nav */

$error = $error ?? '';
$success = $success ?? '';
$title = 'Landing content — billo';

$faqRows = $faqs ?? [];
if ($faqRows === []) {
    $faqRows = [
        ['question' => '', 'answer_html' => '', 'is_active' => 1],
        ['question' => '', 'answer_html' => '', 'is_active' => 1],
        ['question' => '', 'answer_html' => '', 'is_active' => 1],
    ];
}
$logoRows = $trusted_logos ?? [];
if ($logoRows === []) {
    $logoRows = [
        ['name' => '', 'image_url' => '', 'website_url' => '', 'is_active' => 1],
        ['name' => '', 'image_url' => '', 'website_url' => '', 'is_active' => 1],
    ];
}
$testRows = $testimonials ?? [];
if ($testRows === []) {
    $testRows = [
        ['quote_html' => '', 'author_name' => '', 'author_detail' => '', 'portrait_url' => '', 'is_active' => 1],
        ['quote_html' => '', 'author_name' => '', 'author_detail' => '', 'portrait_url' => '', 'is_active' => 1],
    ];
}

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

        <div class="landing-admin-tabs" role="tablist">
            <button type="button" class="landing-admin-tabs__btn is-active" data-landing-tab="copy" role="tab" aria-selected="true">Page copy</button>
            <button type="button" class="landing-admin-tabs__btn" data-landing-tab="faqs" role="tab" aria-selected="false">FAQs</button>
            <button type="button" class="landing-admin-tabs__btn" data-landing-tab="trusted" role="tab" aria-selected="false">Trusted by</button>
            <button type="button" class="landing-admin-tabs__btn" data-landing-tab="testimonials" role="tab" aria-selected="false">Testimonials</button>
        </div>

        <div class="welcome-card landing-admin-panel is-active" data-landing-panel="copy" role="tabpanel">
            <form method="post" action="<?= billo_e(billo_url('/platform/landing')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($copy_keys as $fullKey): ?>
                    <?php
                    $short = substr($fullKey, strlen('landing.'));
                    $fieldName = str_replace('.', '_', $fullKey);
                    $val = $values[$short] ?? '';
                    $label = ucwords(str_replace('_', ' ', $short));
                    $rows = str_contains($short, 'url') || str_contains($short, 'meta') ? 2 : 3;
                    ?>
                    <div class="field">
                        <label class="label" for="<?= billo_e($fieldName) ?>"><?= billo_e($label) ?></label>
                        <textarea class="input" id="<?= billo_e($fieldName) ?>" name="<?= billo_e($fieldName) ?>" rows="<?= $rows ?>"><?= billo_e($val) ?></textarea>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($quill_keys as $fullKey): ?>
                    <?php
                    $short = substr($fullKey, strlen('landing.'));
                    $fieldName = str_replace('.', '_', $fullKey);
                    $val = $values[$short] ?? '';
                    $label = ucwords(str_replace('_', ' ', $short)) . ' (rich text)';
                    ?>
                    <div class="field">
                        <label class="label" for="<?= billo_e($fieldName) ?>"><?= billo_e($label) ?></label>
                        <div class="quill-editor-wrap">
                            <div class="js-quill-mount" data-quill-for="<?= billo_e($fieldName) ?>"></div>
                            <textarea class="input js-quill-input" id="<?= billo_e($fieldName) ?>" name="<?= billo_e($fieldName) ?>" rows="4" hidden><?= billo_e($val) ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save page copy</button>
                </div>
            </form>
        </div>

        <div class="welcome-card landing-admin-panel" data-landing-panel="faqs" role="tabpanel" hidden>
            <p class="hint-banner" style="margin-bottom:1rem">Questions and answers shown on the public site. Empty rows are skipped when you save.</p>
            <form method="post" action="<?= billo_e(billo_url('/platform/landing/faqs')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($faqRows as $row): ?>
                    <div class="landing-repeat-block">
                        <div class="field">
                            <label class="label">Question</label>
                            <input class="input" type="text" name="faq_question[]" value="<?= billo_e((string) ($row['question'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label class="label">Answer (rich text)</label>
                            <?php $fid = 'faq_a_' . bin2hex(random_bytes(4)); ?>
                            <div class="quill-editor-wrap">
                                <div class="js-quill-mount" data-quill-for="<?= billo_e($fid) ?>"></div>
                                <textarea class="input js-quill-input" id="<?= billo_e($fid) ?>" name="faq_answer_html[]" rows="3" hidden><?= billo_e((string) ($row['answer_html'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div class="field field--inline">
                            <label class="label">Visible on site</label>
                            <select class="input input--sm" name="faq_active[]">
                                <?php $faqOn = (int) ($row['is_active'] ?? 1) === 1; ?>
                                <option value="1"<?= $faqOn ? ' selected' : '' ?>>Yes</option>
                                <option value="0"<?= !$faqOn ? ' selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save FAQs</button>
                </div>
            </form>
        </div>

        <div class="welcome-card landing-admin-panel" data-landing-panel="trusted" role="tabpanel" hidden>
            <p class="hint-banner" style="margin-bottom:1rem">Logo image URL (https or absolute path). Use for organizations that trust billo—often labeled “Trusted by” on SaaS sites.</p>
            <form method="post" action="<?= billo_e(billo_url('/platform/landing/trusted')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($logoRows as $row): ?>
                    <div class="landing-repeat-block">
                        <div class="field">
                            <label class="label">Name / alt text</label>
                            <input class="input" type="text" name="trusted_name[]" value="<?= billo_e((string) ($row['name'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label class="label">Image URL</label>
                            <input class="input" type="text" name="trusted_image_url[]" value="<?= billo_e((string) ($row['image_url'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label class="label">Website URL (optional)</label>
                            <input class="input" type="text" name="trusted_website_url[]" value="<?= billo_e((string) ($row['website_url'] ?? '')) ?>">
                        </div>
                        <div class="field field--inline">
                            <label class="label">Visible</label>
                            <select class="input input--sm" name="trusted_active[]">
                                <?php $trOn = (int) ($row['is_active'] ?? 1) === 1; ?>
                                <option value="1"<?= $trOn ? ' selected' : '' ?>>Yes</option>
                                <option value="0"<?= !$trOn ? ' selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save logos</button>
                </div>
            </form>
        </div>

        <div class="welcome-card landing-admin-panel" data-landing-panel="testimonials" role="tabpanel" hidden>
            <form method="post" action="<?= billo_e(billo_url('/platform/landing/testimonials')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($testRows as $row): ?>
                    <div class="landing-repeat-block">
                        <div class="field">
                            <label class="label">Quote (rich text)</label>
                            <?php $tid = 'tq_' . bin2hex(random_bytes(4)); ?>
                            <div class="quill-editor-wrap">
                                <div class="js-quill-mount" data-quill-for="<?= billo_e($tid) ?>"></div>
                                <textarea class="input js-quill-input" id="<?= billo_e($tid) ?>" name="testimonial_quote_html[]" rows="3" hidden><?= billo_e((string) ($row['quote_html'] ?? '')) ?></textarea>
                            </div>
                        </div>
                        <div class="field">
                            <label class="label">Author name</label>
                            <input class="input" type="text" name="testimonial_author_name[]" value="<?= billo_e((string) ($row['author_name'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label class="label">Role / company (optional)</label>
                            <input class="input" type="text" name="testimonial_author_detail[]" value="<?= billo_e((string) ($row['author_detail'] ?? '')) ?>">
                        </div>
                        <div class="field">
                            <label class="label">Portrait image URL (optional)</label>
                            <input class="input" type="text" name="testimonial_portrait_url[]" value="<?= billo_e((string) ($row['portrait_url'] ?? '')) ?>">
                        </div>
                        <div class="field field--inline">
                            <label class="label">Visible</label>
                            <select class="input input--sm" name="testimonial_active[]">
                                <?php $tsOn = (int) ($row['is_active'] ?? 1) === 1; ?>
                                <option value="1"<?= $tsOn ? ' selected' : '' ?>>Yes</option>
                                <option value="0"<?= !$tsOn ? ' selected' : '' ?>>No</option>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="form-actions">
                    <button type="submit" class="btn btn--primary">Save testimonials</button>
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
    <link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= billo_e(billo_asset('css/app.css')) ?>">
</head>
<body class="<?= billo_e($bodyClass) ?>">
<?= $content ?>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function () {
  var toolbar = [
    [{ header: [2, 3, false] }],
    ['bold', 'italic', 'underline'],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean']
  ];
  function bindQuill(mount) {
    var id = mount.getAttribute('data-quill-for');
    var input = document.getElementById(id);
    if (!input) return;
    var quill = new Quill(mount, { theme: 'snow', modules: { toolbar: toolbar } });
    var html = input.value || '';
    if (html) quill.root.innerHTML = html;
    quill.on('text-change', function () {
      input.value = quill.root.innerHTML;
    });
  }
  document.querySelectorAll('.js-quill-mount').forEach(bindQuill);

  var tabBtns = document.querySelectorAll('[data-landing-tab]');
  var panels = document.querySelectorAll('[data-landing-panel]');
  function showTab(name) {
    tabBtns.forEach(function (b) {
      var on = b.getAttribute('data-landing-tab') === name;
      b.classList.toggle('is-active', on);
      b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    panels.forEach(function (p) {
      var on = p.getAttribute('data-landing-panel') === name;
      p.hidden = !on;
      p.classList.toggle('is-active', on);
    });
  }
  tabBtns.forEach(function (b) {
    b.addEventListener('click', function () {
      showTab(b.getAttribute('data-landing-tab'));
    });
  });
  if (location.hash === '#landing-faqs') showTab('faqs');
  else if (location.hash === '#landing-trusted') showTab('trusted');
  else if (location.hash === '#landing-testimonials') showTab('testimonials');
})();
</script>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
