<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Support\OrganizationIdentityNormalizer;
use PDOException;

final class OrganizationController extends Controller
{
    public function __construct(
        private Request $request,
        private OrganizationRepository $organizations = new OrganizationRepository(),
    ) {
    }

    public function edit(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        $org = $this->organizations->findById($ctx['organization_id']);
        if ($org === null) {
            Session::flash('error', 'Organization not found.');
            $this->redirect('/dashboard');
        }

        View::render('organization/edit', [
            'organization' => $org,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => true,
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function update(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/organization');
        }

        $payload = $this->validatedPayload((int) $ctx['organization_id']);
        if (is_string($payload)) {
            Session::flash('error', $payload);
            $this->redirect('/organization');
        }

        try {
            $this->organizations->updateBranding($ctx['organization_id'], $payload);
        } catch (PDOException $e) {
            $code = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
            if ($code === 1062) {
                Session::flash(
                    'error',
                    'Another workspace is already registered with this tax ID, company registration number (for your country), or website. If this is your business, contact support.'
                );
                $this->redirect('/organization');
            }
            throw $e;
        }
        Session::flash('success', 'Business details saved. They appear on printed invoices, PDFs, and emails.');
        $this->redirect('/organization');
    }

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }

    /**
     * @return array{
     *   legal_name:?string,
     *   billing_address_line1:?string,
     *   billing_address_line2:?string,
     *   billing_city:?string,
     *   billing_state:?string,
     *   billing_country:string,
     *   tax_id:?string,
     *   tax_id_normalized:?string,
     *   company_registration_number:?string,
     *   company_registration_normalized:?string,
     *   company_website:?string,
     *   company_website_host:?string,
     *   invoice_footer:?string,
     *   invoice_logo_url:?string
     * }|string
     */
    private function validatedPayload(int $organizationId): array|string
    {
        $legal = $this->trimOrNull($this->request->input('legal_name', ''), 200);
        $l1 = $this->trimOrNull($this->request->input('billing_address_line1', ''), 255);
        $l2 = $this->trimOrNull($this->request->input('billing_address_line2', ''), 255);
        $city = $this->trimOrNull($this->request->input('billing_city', ''), 120);
        $state = $this->trimOrNull($this->request->input('billing_state', ''), 120);
        $country = strtoupper(trim((string) ($this->request->input('billing_country', '') ?? 'NG')));
        if (strlen($country) !== 2) {
            $country = 'NG';
        }
        $tax = $this->trimOrNull($this->request->input('tax_id', ''), 64);
        $taxNorm = OrganizationIdentityNormalizer::normalizeTaxId($tax);
        $cac = $this->trimOrNull($this->request->input('company_registration_number', ''), 40);
        $cacNorm = OrganizationIdentityNormalizer::normalizeCompanyRegistration($cac);
        $website = $this->trimOrNull($this->request->input('company_website', ''), 255);
        $websiteHost = OrganizationIdentityNormalizer::normalizeWebsiteHost($website);
        if ($website !== null && $websiteHost === null) {
            return 'Enter a valid company website (URL or domain, e.g. https://example.com or example.ng).';
        }
        $footer = $this->trimOrNull($this->request->input('invoice_footer', ''), 65535);
        $logo = $this->trimOrNull($this->request->input('invoice_logo_url', ''), 500);

        if ($logo !== null) {
            if (str_starts_with($logo, 'https://')) {
                // ok
            } elseif (str_starts_with($logo, 'http://')) {
                return 'Logo URL must use https:// for remote images.';
            } elseif ($logo !== '' && str_contains($logo, '://')) {
                return 'Logo must be a full https URL or a project-relative path (no scheme).';
            }
        }

        if ($taxNorm !== null) {
            $dup = $this->organizations->findDuplicateTaxIdentity($country, $taxNorm, $organizationId);
            if ($dup !== null) {
                return 'Another workspace already uses this tax ID (TIN) for the selected country.';
            }
        }
        if ($cacNorm !== null) {
            $dup = $this->organizations->findDuplicateRegistration($country, $cacNorm, $organizationId);
            if ($dup !== null) {
                return 'Another workspace already uses this company registration number (e.g. CAC) for the selected country.';
            }
        }
        if ($websiteHost !== null) {
            $dup = $this->organizations->findDuplicateWebsiteHost($websiteHost, $organizationId);
            if ($dup !== null) {
                return 'Another workspace is already linked to this website domain.';
            }
        }

        return [
            'legal_name' => $legal,
            'billing_address_line1' => $l1,
            'billing_address_line2' => $l2,
            'billing_city' => $city,
            'billing_state' => $state,
            'billing_country' => $country,
            'tax_id' => $tax,
            'tax_id_normalized' => $taxNorm,
            'company_registration_number' => $cac,
            'company_registration_normalized' => $cacNorm,
            'company_website' => $website,
            'company_website_host' => $websiteHost,
            'invoice_footer' => $footer,
            'invoice_logo_url' => $logo,
        ];
    }

    private function trimOrNull(?string $value, int $maxLen): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim($value);
        if ($v === '') {
            return null;
        }
        if (function_exists('mb_strlen') && mb_strlen($v, 'UTF-8') > $maxLen) {
            return mb_substr($v, 0, $maxLen, 'UTF-8');
        }
        if (strlen($v) > $maxLen) {
            return substr($v, 0, $maxLen);
        }

        return $v;
    }
}
