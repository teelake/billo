<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Services\NigerianBankListService;
use App\Services\OrganizationLogoService;
use App\Support\InvoiceTheme;
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
            'nigerian_banks' => NigerianBankListService::banks(),
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

        $orgId = (int) $ctx['organization_id'];
        $removeLogo = isset($_POST['remove_logo']) && $_POST['remove_logo'] === '1';
        $logoUploadPath = null;
        if (!$removeLogo && !empty($_FILES['logo_upload']) && is_array($_FILES['logo_upload'])) {
            $fe = (int) ($_FILES['logo_upload']['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($fe !== UPLOAD_ERR_NO_FILE) {
                if ($fe !== UPLOAD_ERR_OK) {
                    Session::flash('error', match ($fe) {
                        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Logo file must be 1 MB or smaller.',
                        UPLOAD_ERR_PARTIAL => 'Logo upload was interrupted.',
                        default => 'Logo upload failed.',
                    });
                    $this->redirect('/organization');
                }
                $uploadResult = OrganizationLogoService::processAndStore($_FILES['logo_upload'], $orgId);
                if (!($uploadResult['ok'] ?? false)) {
                    Session::flash('error', $uploadResult['error'] ?? 'Logo upload failed.');
                    $this->redirect('/organization');
                }
                /** @var array{ok: true, path: string} $uploadResult */
                $logoUploadPath = $uploadResult['path'];
            }
        }
        if ($removeLogo) {
            OrganizationLogoService::removeBrandingFiles($orgId);
        }

        $payload = $this->validatedPayload($orgId, $logoUploadPath, $removeLogo);
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

    /** Serve uploaded invoice logo for current organization (authenticated). */
    public function logo(): void
    {
        $ctx = $this->requireAuth();
        $org = $this->organizations->findById($ctx['organization_id']);
        if ($org === null) {
            http_response_code(404);

            return;
        }
        $ref = trim(str_replace('\\', '/', (string) ($org['invoice_logo_url'] ?? '')));
        if ($ref === '' || str_starts_with($ref, 'http://') || str_starts_with($ref, 'https://')) {
            http_response_code(404);

            return;
        }
        $expected = 'storage/branding/' . $ctx['organization_id'] . '/';
        if (!str_starts_with(ltrim($ref, '/'), $expected)) {
            http_response_code(403);

            return;
        }
        $full = BILLO_ROOT . '/' . str_replace('/', DIRECTORY_SEPARATOR, ltrim($ref, '/'));
        $real = realpath($full);
        $base = realpath(OrganizationLogoService::brandDir($ctx['organization_id']));
        if ($real === false || $base === false || !str_starts_with($real, $base) || !is_file($real)) {
            http_response_code(404);

            return;
        }
        $mime = @mime_content_type($real);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            http_response_code(404);

            return;
        }
        header('Content-Type: ' . $mime);
        header('Cache-Control: private, max-age=3600');
        readfile($real);
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
     *   invoice_logo_url:?string,
     *   invoice_tax_enabled:int,
     *   invoice_style:string,
     *   invoice_brand_primary:string,
     *   invoice_brand_accent:string
     * }|string
     */
    private function validatedPayload(int $organizationId, ?string $logoUploadPath, bool $removeLogo): array|string
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

        if ($removeLogo) {
            $logo = null;
        } elseif ($logoUploadPath !== null) {
            $logo = $logoUploadPath;
        } else {
            $logo = $this->trimOrNull($this->request->input('invoice_logo_url', ''), 500);
        }

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

        $invoiceTaxEnabled = isset($_POST['invoice_tax_enabled']) && (string) $_POST['invoice_tax_enabled'] === '1' ? 1 : 0;
        $invoiceStyle = InvoiceTheme::normalizeStyle((string) ($this->request->input('invoice_style', 'modern') ?? 'modern'));
        $rawPrimary = trim((string) ($this->request->input('invoice_brand_primary', '') ?? ''));
        $rawAccent = trim((string) ($this->request->input('invoice_brand_accent', '') ?? ''));
        if ($rawPrimary !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $rawPrimary) !== 1) {
            return 'Primary brand color must be a hex code like #1E3A8A.';
        }
        if ($rawAccent !== '' && preg_match('/^#[0-9A-Fa-f]{6}$/', $rawAccent) !== 1) {
            return 'Accent brand color must be a hex code like #16A34A.';
        }
        $brandPrimary = strtoupper($rawPrimary !== '' ? $rawPrimary : '#1E3A8A');
        $brandAccent = strtoupper($rawAccent !== '' ? $rawAccent : '#16A34A');

        $bankName = $this->trimOrNull($this->request->input('invoice_bank_name', ''), 160);
        $bankCodeRaw = trim((string) ($this->request->input('invoice_bank_code', '') ?? ''));
        if (strlen($bankCodeRaw) > 12) {
            return 'Bank code is too long.';
        }
        if ($bankCodeRaw !== '' && preg_match('/^[A-Za-z0-9]+$/', $bankCodeRaw) !== 1) {
            return 'Bank code must be letters and numbers only.';
        }
        $bankCode = $bankCodeRaw !== '' ? $bankCodeRaw : null;
        $acctHolder = $this->trimOrNull($this->request->input('invoice_bank_account_name', ''), 160);
        $rawAcct = preg_replace('/\s+/', '', (string) ($this->request->input('invoice_bank_account_number', '') ?? ''));
        $acctNum = $rawAcct !== '' ? $rawAcct : null;
        if ($acctNum !== null && preg_match('/^\d{8,20}$/', $acctNum) !== 1) {
            return 'Account number must be digits only (8–20 characters).';
        }
        $hasAcct = $acctHolder !== null || $acctNum !== null;
        if ($hasAcct && $bankName === null) {
            return 'Enter the bank name when account details are provided.';
        }
        if ($bankName === null) {
            $bankCode = null;
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
            'invoice_bank_name' => $bankName,
            'invoice_bank_code' => $bankCode,
            'invoice_bank_account_name' => $acctHolder,
            'invoice_bank_account_number' => $acctNum,
            'invoice_logo_url' => $logo,
            'invoice_tax_enabled' => $invoiceTaxEnabled,
            'invoice_style' => $invoiceStyle,
            'invoice_brand_primary' => strtoupper($brandPrimary),
            'invoice_brand_accent' => strtoupper($brandAccent),
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
