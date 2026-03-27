<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\PlatformSettingsRepository;
use App\Services\PlatformSettings;

final class PlatformLandingController extends Controller
{
    private const KEYS = [
        'landing.hero_eyebrow',
        'landing.hero_title_line1',
        'landing.hero_title_line2',
        'landing.hero_subtitle',
        'landing.hero_note',
        'landing.features_title',
        'landing.features_lead',
        'landing.feature_1_title',
        'landing.feature_1_text',
        'landing.feature_2_title',
        'landing.feature_2_text',
        'landing.feature_3_title',
        'landing.feature_3_text',
        'landing.feature_4_title',
        'landing.feature_4_text',
        'landing.compliance_title',
        'landing.compliance_lead',
        'landing.compliance_li1',
        'landing.compliance_li2',
        'landing.compliance_li3',
        'landing.callout_label',
        'landing.callout_text',
        'landing.pricing_title',
        'landing.pricing_lead',
        'landing.pricing_card_title',
        'landing.pricing_card_text',
        'landing.cta_title',
        'landing.cta_text',
        'landing.footer_tagline',
        'landing.meta_title',
    ];

    public function __construct(
        private Request $request,
        private PlatformSettingsRepository $settings = new PlatformSettingsRepository(),
    ) {
    }

    public function edit(): void
    {
        $this->requirePlatformAdmin();
        $ctx = $this->authContext();
        $values = [];
        foreach (self::KEYS as $key) {
            $short = substr($key, strlen('landing.'));
            $values[$short] = billo_landing($short, '');
        }

        View::render('platform/landing', [
            'values' => $values,
            'keys' => self::KEYS,
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

        foreach (self::KEYS as $key) {
            $field = str_replace('.', '_', $key);
            $raw = $this->request->input($field, '');
            $val = $raw !== null ? trim($raw) : '';
            $this->settings->upsert($key, $val !== '' ? $val : null);
        }

        PlatformSettings::applyFromDatabase();
        Session::flash('success', 'Landing page content saved.');
        $this->redirect('/platform/landing');
    }

    private function requirePlatformAdmin(): void
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Sign in to manage the platform.');
            $this->redirect('/login');
        }
        if (!billo_is_platform_admin()) {
            Session::flash('error', 'You do not have platform access.');
            $this->redirect('/dashboard');
        }
    }
}
