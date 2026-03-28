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
$values = $values ?? [];

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

$heroStored = trim((string) ($values['hero_image_url'] ?? ''));
$heroPreviewUrl = billo_resolve_public_image_src($heroStored);

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
            <form method="post" action="<?= billo_e(billo_url('/platform/landing')) ?>" class="form form--spaced" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <div class="landing-media-card">
                    <div class="landing-media-card__head">
                        <div>
                            <h2 class="landing-media-card__title">Hero image</h2>
                            <p class="landing-media-card__hint">Optional full-width visual next to the headline. JPEG/PNG/WebP, up to 2.5&nbsp;MB. Resized to max 1680px long edge, high-quality recompression.</p>
                        </div>
                    </div>
                    <div class="landing-upload-preview<?= $heroPreviewUrl !== '' ? ' has-image' : '' ?>" id="landing-hero-preview-wrap">
                        <img src="<?= $heroPreviewUrl !== '' ? billo_e($heroPreviewUrl) : '' ?>" alt="" class="landing-upload-preview__img<?= $heroPreviewUrl === '' ? ' landing-upload-preview__img--hidden' : '' ?>" id="landing-hero-preview-img" width="560" height="315" loading="lazy">
                        <span class="landing-upload-preview__placeholder<?= $heroPreviewUrl !== '' ? ' landing-upload-preview__placeholder--hidden' : '' ?>" id="landing-hero-preview-ph">No image selected</span>
                    </div>
                    <div class="landing-media-card__actions">
                        <label class="btn btn--secondary" for="landing_hero_image_file">Choose file</label>
                        <input class="sr-only" type="file" id="landing_hero_image_file" name="hero_image_file" accept="image/jpeg,image/png,image/gif,image/webp" data-landing-preview="landing-hero-preview-img" data-landing-preview-wrap="landing-hero-preview-wrap" data-landing-preview-ph="landing-hero-preview-ph">
                        <label class="landing-checkbox-pill">
                            <input type="checkbox" name="remove_hero_image" value="1"> Remove hero image
                        </label>
                    </div>
                </div>
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
            <p class="landing-panel-lead">Questions and answers on the public site. Empty rows are skipped when you save.</p>
            <form method="post" action="<?= billo_e(billo_url('/platform/landing/faqs')) ?>" class="form form--spaced">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($faqRows as $fi => $row): ?>
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
                        <div class="landing-toggle-row">
                            <span class="landing-toggle-row__label">Visible on site</span>
                            <?php $faqOn = (int) ($row['is_active'] ?? 1) === 1; ?>
                            <input type="hidden" name="faq_visible_<?= (int) $fi ?>" value="0">
                            <label class="field-toggle field-toggle--compact">
                                <input type="checkbox" name="faq_visible_<?= (int) $fi ?>" value="1" class="field-toggle__input"<?= $faqOn ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
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
            <p class="landing-panel-lead">Customer quotes with optional portrait. Images max 1&nbsp;MB; resized to max 384px for crisp avatars.</p>
            <form method="post" action="<?= billo_e(billo_url('/platform/landing/testimonials')) ?>" class="form form--spaced" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= billo_e(Csrf::token()) ?>">
                <?php foreach ($testRows as $ti => $row): ?>
                    <?php
                    $picRaw = trim((string) ($row['portrait_url'] ?? ''));
                    $picPreview = billo_resolve_public_image_src($picRaw);
                    ?>
                    <div class="landing-repeat-block landing-repeat-block--media">
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
                        <input type="hidden" name="testimonial_portrait_existing[]" value="<?= billo_e($picRaw) ?>">
                        <div class="field">
                            <span class="label">Portrait (optional)</span>
                            <div class="landing-upload-preview landing-upload-preview--avatar<?= $picPreview !== '' ? ' has-image' : '' ?>" id="ts-wrap-<?= (int) $ti ?>">
                                <img src="<?= $picPreview !== '' ? billo_e($picPreview) : '' ?>" alt="" class="landing-upload-preview__img<?= $picPreview === '' ? ' landing-upload-preview__img--hidden' : '' ?>" id="ts-img-<?= (int) $ti ?>" width="80" height="80" loading="lazy">
                                <span class="landing-upload-preview__placeholder<?= $picPreview !== '' ? ' landing-upload-preview__placeholder--hidden' : '' ?>" id="ts-ph-<?= (int) $ti ?>">Optional</span>
                            </div>
                            <label class="btn btn--secondary btn--sm" for="ts-file-<?= (int) $ti ?>">Choose portrait</label>
                            <input class="sr-only" type="file" id="ts-file-<?= (int) $ti ?>" name="testimonial_portrait_file[]" accept="image/jpeg,image/png,image/gif,image/webp" data-landing-preview="ts-img-<?= (int) $ti ?>" data-landing-preview-wrap="ts-wrap-<?= (int) $ti ?>" data-landing-preview-ph="ts-ph-<?= (int) $ti ?>">
                        </div>
                        <div class="landing-toggle-row">
                            <span class="landing-toggle-row__label">Visible</span>
                            <?php $tsOn = (int) ($row['is_active'] ?? 1) === 1; ?>
                            <input type="hidden" name="testimonial_visible_<?= (int) $ti ?>" value="0">
                            <label class="field-toggle field-toggle--compact">
                                <input type="checkbox" name="testimonial_visible_<?= (int) $ti ?>" value="1" class="field-toggle__input"<?= $tsOn ? ' checked' : '' ?>>
                                <span class="field-toggle__track" aria-hidden="true"><span class="field-toggle__thumb"></span></span>
                            </label>
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

  var maxTrusted = 1048576;
  var maxPortrait = 1048576;
  var maxHero = 2621440;
  document.querySelectorAll('input[type=file][data-landing-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var imgId = input.getAttribute('data-landing-preview');
      var wrapId = input.getAttribute('data-landing-preview-wrap');
      var phId = input.getAttribute('data-landing-preview-ph');
      var img = document.getElementById(imgId);
      var wrap = document.getElementById(wrapId);
      var ph = phId ? document.getElementById(phId) : null;
      var f = input.files && input.files[0];
      if (!img || !wrap) return;
      var lim = input.name === 'hero_image_file' ? maxHero : (input.name.indexOf('portrait') !== -1 ? maxPortrait : maxTrusted);
      if (!f) return;
      if (f.size > lim) {
        alert('File is too large for this field.');
        input.value = '';
        return;
      }
      var rd = new FileReader();
      rd.onload = function () {
        img.src = rd.result;
        img.classList.remove('landing-upload-preview__img--hidden');
        wrap.classList.add('has-image');
        if (ph) ph.classList.add('landing-upload-preview__placeholder--hidden');
      };
      rd.readAsDataURL(f);
    });
  });
})();
</script>
<script src="<?= billo_e(billo_asset('js/app.js')) ?>" defer></script>
</body>
</html>
