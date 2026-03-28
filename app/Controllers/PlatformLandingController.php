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
use App\Services\PlatformSettings;

final class PlatformLandingController extends Controller
{
    /** Plain text / line fields (escaped on the public site). */
    private const COPY_KEYS = [
        'landing.hero_eyebrow',
        'landing.hero_title_line1',
        'landing.hero_title_line2',
        'landing.hero_note',
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
        'landing.hero_image_url',
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

        View::render('platform/landing', [
            'values' => $values,
            'copy_keys' => self::COPY_KEYS,
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
        $active = $this->request->postStringList('faq_active');
        $rows = [];
        $n = max(count($questions), count($answers));
        for ($i = 0; $i < $n; $i++) {
            $q = isset($questions[$i]) ? trim($questions[$i]) : '';
            $a = isset($answers[$i]) ? billo_sanitize_landing_html($answers[$i]) : '';
            $on = isset($active[$i]) && $active[$i] === '1';
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
        $images = $this->request->postStringList('trusted_image_url');
        $webs = $this->request->postStringList('trusted_website_url');
        $active = $this->request->postStringList('trusted_active');
        $rows = [];
        $n = max(count($names), count($images));
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'name' => isset($names[$i]) ? trim($names[$i]) : '',
                'image_url' => isset($images[$i]) ? trim($images[$i]) : '',
                'website_url' => isset($webs[$i]) ? trim($webs[$i]) : '',
                'is_active' => isset($active[$i]) && $active[$i] === '1',
            ];
        }
        $this->landingBlocks->replaceTrustedLogos($rows);
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
        $portraits = $this->request->postStringList('testimonial_portrait_url');
        $active = $this->request->postStringList('testimonial_active');
        $rows = [];
        $n = max(count($quotes), count($authors));
        for ($i = 0; $i < $n; $i++) {
            $rows[] = [
                'quote_html' => isset($quotes[$i]) ? billo_sanitize_landing_html($quotes[$i]) : '',
                'author_name' => isset($authors[$i]) ? trim($authors[$i]) : '',
                'author_detail' => isset($details[$i]) ? trim($details[$i]) : '',
                'portrait_url' => isset($portraits[$i]) ? trim($portraits[$i]) : '',
                'is_active' => isset($active[$i]) && $active[$i] === '1',
            ];
        }
        $this->landingBlocks->replaceTestimonials($rows);
        Session::flash('success', 'Testimonials updated.');
        $this->redirect('/platform/landing#landing-testimonials');
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
