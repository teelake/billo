<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\LandingPageRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Services\LandingImageService;
use App\Services\PlatformSettings;

final class PlatformLandingController extends Controller
{
    /** Plain text / line fields (escaped on the public site). */
    private const COPY_KEYS = [
        'landing.hero_eyebrow',
        'landing.hero_title_line1',
        'landing.hero_title_line2',
        'landing.hero_note',
        'landing.hero_trust',
        'landing.hero_cta_primary',
        'landing.hero_cta_secondary',
        'landing.pricing_cta_label',
        'landing.cta_button_label',
        'landing.features_title',
        'landing.feature_1_title',
        'landing.feature_2_title',
        'landing.feature_3_title',
        'landing.feature_4_title',
        'landing.compliance_title',
        'landing.compliance_li1',
        'landing.compliance_li2',
        'landing.compliance_li3',
        'landing.callout_label',
        'landing.pricing_title',
        'landing.cta_title',
        'landing.footer_tagline',
        'landing.meta_title',
        'landing.hero_image_alt',
        'landing.faqs_section_title',
        'landing.trusted_section_title',
        'landing.testimonials_section_title',
    ];

    /** Rich HTML (Quill); sanitized on save and rendered with billo_landing_html(). */
    private const QUILL_KEYS = [
        'landing.hero_subtitle',
        'landing.features_lead',
        'landing.feature_1_text',
        'landing.feature_2_text',
        'landing.feature_3_text',
        'landing.feature_4_text',
        'landing.compliance_lead',
        'landing.callout_text',
        'landing.pricing_lead',
        'landing.pricing_card_text',
        'landing.cta_text',
    ];

    public function __construct(
        private Request $request,
        private PlatformSettingsRepository $settings = new PlatformSettingsRepository(),
        private LandingPageRepository $landingBlocks = new LandingPageRepository(),
    ) {
    }

    public function edit(): void
    {
        $this->requirePlatformAdmin();
        $ctx = $this->authContext();
        $values = [];
        foreach (array_merge(self::COPY_KEYS, self::QUILL_KEYS) as $key) {
            $short = substr($key, strlen('landing.'));
            $values[$short] = billo_landing($short, '');
        }
        $values['hero_image_url'] = billo_landing('hero_image_url', '');

        $copyKeysForm = array_values(array_filter(
            self::COPY_KEYS,
            static fn (string $k): bool => $k !== 'landing.hero_image_url'
        ));

        View::render('platform/landing', [
            'values' => $values,
            'copy_keys' => $copyKeysForm,
            'quill_keys' => self::QUILL_KEYS,
            'faqs' => $this->landingBlocks->listFaqsAdmin(),
            'trusted_logos' => $this->landingBlocks->listTrustedLogosAdmin(),
            'testimonials' => $this->landingBlocks->listTestimonialsAdmin(),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) ($ctx['role'] ?? 'owner'),
            'show_team_nav' => in_array($ctx['role'] ?? '', ['owner', 'admin'], true),
        ]);
    }

    public function save(): void
    {
        $this->requirePlatformAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/platform/landing');
        }

        foreach (self::COPY_KEYS as $key) {
            $field = str_replace('.', '_', $key);
            $raw = $this->request->input($field, '');
            $val = $raw !== null ? trim($raw) : '';
            $this->settings->upsert($key, $val !== '' ? $val : null);
        }

        $heroDir = LandingImageService::landingRoot() . DIRECTORY_SEPARATOR . 'hero';
        $removeHero = isset($_POST['remove_hero_image']) && (string) $_POST['remove_hero_image'] === '1';
        if ($removeHero) {
            LandingImageService::removeDirContents($heroDir);
            $this->settings->upsert('landing.hero_image_url', null);
        } elseif (!empty($_FILES['hero_image_file']) && is_array($_FILES['hero_image_file'])) {
            $fe = (int) ($_FILES['hero_image_file']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe === UPLOAD_ERR_OK) {
                if (!is_dir($heroDir) && !mkdir($heroDir, 0755, true) && !is_dir($heroDir)) {
                    Session::flash('error', 'Could not create hero image folder.');
                    $this->redirect('/platform/landing');
                }
                LandingImageService::removeDirContents($heroDir);
                $r = LandingImageService::processAndSave(
                    $_FILES['hero_image_file'],
                    $heroDir . DIRECTORY_SEPARATOR . 'hero',
                    LandingImageService::PROFILE_HERO
                );
                if (!($r['ok'] ?? false)) {
                    Session::flash('error', $r['error'] ?? 'Hero image could not be processed.');
                    $this->redirect('/platform/landing');
                }
                $this->settings->upsert('landing.hero_image_url', 'storage/landing/hero/hero.' . $r['ext']);
            } elseif ($fe !== UPLOAD_ERR_NO_FILE) {
                Session::flash('error', 'Hero image upload failed.');
                $this->redirect('/platform/landing');
            }
        }

        foreach (self::QUILL_KEYS as $key) {
            $field = str_replace('.', '_', $key);
            $raw = $_POST[$field] ?? '';
            $html = is_string($raw) ? billo_sanitize_landing_html($raw) : '';
            $this->settings->upsert($key, $html !== '' ? $html : null);
        }

        PlatformSettings::applyFromDatabase();
        Session::flash('success', 'Landing page content saved.');
        $this->redirect('/platform/landing');
    }

    public function saveFaqs(): void
    {
        $this->requirePlatformAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/platform/landing');
        }

        $questions = $this->request->postStringList('faq_question');
        $answers = $this->request->postStringList('faq_answer_html');
        $rows = [];
        $n = max(count($questions), count($answers));
        for ($i = 0; $i < $n; $i++) {
            $q = isset($questions[$i]) ? trim($questions[$i]) : '';
            $a = isset($answers[$i]) ? billo_sanitize_landing_html($answers[$i]) : '';
            $on = isset($_POST['faq_visible_' . $i]) && (string) $_POST['faq_visible_' . $i] === '1';
            $rows[] = ['question' => $q, 'answer_html' => $a, 'is_active' => $on];
        }
        $this->landingBlocks->replaceFaqs($rows);
        Session::flash('success', 'FAQs updated.');
        $this->redirect('/platform/landing#landing-faqs');
    }

    public function saveTrusted(): void
    {
        $this->requirePlatformAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/platform/landing');
        }

        $names = $this->request->postStringList('trusted_name');
        $existing = $this->request->postStringList('trusted_image_existing');
        $webs = $this->request->postStringList('trusted_website_url');
        $files = LandingImageService::normalizeFilesList('trusted_image_file');
        $n = max(count($names), count($existing), count($webs), count($files));

        $dir = LandingImageService::landingRoot() . DIRECTORY_SEPARATOR . 'trusted';
        $backup = LandingImageService::landingRoot() . DIRECTORY_SEPARATOR . '_bak_trusted_' . uniqid('', true);
        $hadDir = is_dir($dir);
        if ($hadDir && !@rename($dir, $backup)) {
            Session::flash('error', 'Could not stage trusted logo folder.');
            $this->redirect('/platform/landing#landing-trusted');
        }
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            if ($hadDir && is_dir($backup)) {
                @rename($backup, $dir);
            }
            Session::flash('error', 'Could not create trusted logo folder.');
            $this->redirect('/platform/landing#landing-trusted');
        }

        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $name = isset($names[$i]) ? trim($names[$i]) : '';
            $web = isset($webs[$i]) ? trim($webs[$i]) : '';
            $on = isset($_POST['trusted_visible_' . $i]) && (string) $_POST['trusted_visible_' . $i] === '1';
            $imgPath = '';

            $f = $files[$i] ?? null;
            if ($f !== null && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    $this->restoreLandingDir($dir, $backup, $hadDir);
                    Session::flash('error', 'A trusted logo upload failed (row ' . ($i + 1) . ').');
                    $this->redirect('/platform/landing#landing-trusted');
                }
                $r = LandingImageService::processAndSave($f, $dir . DIRECTORY_SEPARATOR . (string) $i, LandingImageService::PROFILE_TRUSTED);
                if (!($r['ok'] ?? false)) {
                    $this->restoreLandingDir($dir, $backup, $hadDir);
                    Session::flash('error', $r['error'] ?? 'Could not process a logo.');
                    $this->redirect('/platform/landing#landing-trusted');
                }
                $imgPath = 'storage/landing/trusted/' . $i . '.' . $r['ext'];
            } else {
                $ex = isset($existing[$i]) ? trim($existing[$i]) : '';
                if ($ex !== '' && (str_starts_with($ex, 'http://') || str_starts_with($ex, 'https://'))) {
                    $imgPath = $ex;
                } elseif ($ex !== '' && LandingImageService::isSafeStoredPath($ex, 'trusted')) {
                    $base = basename(str_replace('\\', '/', $ex));
                    $srcInBackup = $backup . DIRECTORY_SEPARATOR . $base;
                    if ($hadDir && is_file($srcInBackup)) {
                        $ext = pathinfo($base, PATHINFO_EXTENSION);
                        $dest = $dir . DIRECTORY_SEPARATOR . $i . '.' . $ext;
                        if (@copy($srcInBackup, $dest)) {
                            $imgPath = 'storage/landing/trusted/' . $i . '.' . $ext;
                        }
                    }
                }
            }

            if ($imgPath === '') {
                continue;
            }
            $rows[] = [
                'name' => $name !== '' ? $name : 'Partner',
                'image_url' => $imgPath,
                'website_url' => $web,
                'is_active' => $on,
            ];
        }

        try {
            $this->landingBlocks->replaceTrustedLogos($rows);
        } catch (\Throwable $e) {
            $this->restoreLandingDir($dir, $backup, $hadDir);
            throw $e;
        }

        if (is_dir($backup)) {
            LandingImageService::deleteDir($backup);
        }

        Session::flash('success', 'Trusted-by logos updated.');
        $this->redirect('/platform/landing#landing-trusted');
    }

    public function saveTestimonials(): void
    {
        $this->requirePlatformAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/platform/landing');
        }

        $quotes = $this->request->postStringList('testimonial_quote_html');
        $authors = $this->request->postStringList('testimonial_author_name');
        $details = $this->request->postStringList('testimonial_author_detail');
        $existing = $this->request->postStringList('testimonial_portrait_existing');
        $files = LandingImageService::normalizeFilesList('testimonial_portrait_file');
        $n = max(count($quotes), count($authors), count($existing), count($files));

        $dir = LandingImageService::landingRoot() . DIRECTORY_SEPARATOR . 'portraits';
        $backup = LandingImageService::landingRoot() . DIRECTORY_SEPARATOR . '_bak_portraits_' . uniqid('', true);
        $hadDir = is_dir($dir);
        if ($hadDir && !@rename($dir, $backup)) {
            Session::flash('error', 'Could not stage portrait folder.');
            $this->redirect('/platform/landing#landing-testimonials');
        }
        if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
            if ($hadDir && is_dir($backup)) {
                @rename($backup, $dir);
            }
            Session::flash('error', 'Could not create portrait folder.');
            $this->redirect('/platform/landing#landing-testimonials');
        }

        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $quote = isset($quotes[$i]) ? billo_sanitize_landing_html($quotes[$i]) : '';
            $author = isset($authors[$i]) ? trim($authors[$i]) : '';
            $detail = isset($details[$i]) ? trim($details[$i]) : '';
            $on = isset($_POST['testimonial_visible_' . $i]) && (string) $_POST['testimonial_visible_' . $i] === '1';
            $picPath = '';

            $f = $files[$i] ?? null;
            if ($f !== null && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                if (($f['error'] ?? 0) !== UPLOAD_ERR_OK) {
                    $this->restoreLandingDir($dir, $backup, $hadDir);
                    Session::flash('error', 'A portrait upload failed (row ' . ($i + 1) . ').');
                    $this->redirect('/platform/landing#landing-testimonials');
                }
                $r = LandingImageService::processAndSave($f, $dir . DIRECTORY_SEPARATOR . (string) $i, LandingImageService::PROFILE_PORTRAIT);
                if (!($r['ok'] ?? false)) {
                    $this->restoreLandingDir($dir, $backup, $hadDir);
                    Session::flash('error', $r['error'] ?? 'Could not process a portrait.');
                    $this->redirect('/platform/landing#landing-testimonials');
                }
                $picPath = 'storage/landing/portraits/' . $i . '.' . $r['ext'];
            } else {
                $ex = isset($existing[$i]) ? trim($existing[$i]) : '';
                if ($ex !== '' && (str_starts_with($ex, 'http://') || str_starts_with($ex, 'https://'))) {
                    $picPath = $ex;
                } elseif ($ex !== '' && LandingImageService::isSafeStoredPath($ex, 'portraits')) {
                    $base = basename(str_replace('\\', '/', $ex));
                    $srcInBackup = $backup . DIRECTORY_SEPARATOR . $base;
                    if ($hadDir && is_file($srcInBackup)) {
                        $ext = pathinfo($base, PATHINFO_EXTENSION);
                        $dest = $dir . DIRECTORY_SEPARATOR . $i . '.' . $ext;
                        if (@copy($srcInBackup, $dest)) {
                            $picPath = 'storage/landing/portraits/' . $i . '.' . $ext;
                        }
                    }
                }
            }

            $quotePlain = trim(preg_replace('/\s+/u', ' ', strip_tags($quote)));
            if ($author === '' && $quotePlain === '') {
                continue;
            }
            $rows[] = [
                'quote_html' => $quote !== '' ? $quote : '<p></p>',
                'author_name' => $author !== '' ? $author : 'Customer',
                'author_detail' => $detail !== '' ? $detail : null,
                'portrait_url' => $picPath,
                'is_active' => $on,
            ];
        }

        try {
            $this->landingBlocks->replaceTestimonials($rows);
        } catch (\Throwable $e) {
            $this->restoreLandingDir($dir, $backup, $hadDir);
            throw $e;
        }

        if (is_dir($backup)) {
            LandingImageService::deleteDir($backup);
        }

        Session::flash('success', 'Testimonials updated.');
        $this->redirect('/platform/landing#landing-testimonials');
    }

    private function restoreLandingDir(string $dir, string $backup, bool $hadBackupSource): void
    {
        LandingImageService::deleteDir($dir);
        if ($hadBackupSource && is_dir($backup)) {
            @rename($backup, $dir);
        }
    }

    private function requirePlatformAdmin(): void
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Sign in to manage the platform.');
            $this->redirect('/login');
        }
        if (!billo_is_platform_admin() && !billo_is_system_admin()) {
            Session::flash('error', 'You do not have platform access.');
            $this->redirect('/dashboard');
        }
    }
}
