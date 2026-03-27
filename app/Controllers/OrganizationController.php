<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;

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

        $payload = $this->validatedPayload();
        if (is_string($payload)) {
            Session::flash('error', $payload);
            $this->redirect('/organization');
        }

        $this->organizations->updateBranding($ctx['organization_id'], $payload);
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
     *   invoice_footer:?string,
     *   invoice_logo_url:?string
     * }|string
     */
    private function validatedPayload(): array|string
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

        return [
            'legal_name' => $legal,
            'billing_address_line1' => $l1,
            'billing_address_line2' => $l2,
            'billing_city' => $city,
            'billing_state' => $state,
            'billing_country' => $country,
            'tax_id' => $tax,
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
