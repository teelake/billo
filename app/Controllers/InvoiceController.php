<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OrganizationRepository;
use App\Services\EmailNotifications;

final class InvoiceController extends Controller
{
    public function __construct(
        private Request $request,
        private InvoiceRepository $invoices = new InvoiceRepository(),
        private ClientRepository $clients = new ClientRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private EmailNotifications $emailNotifications = new EmailNotifications(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuth();
        $list = $this->invoices->listForOrganization($ctx['organization_id']);
        $canManage = $this->canManageInvoices($ctx['role']);

        View::render('invoices/index', [
            'invoices' => $list,
            'can_manage' => $canManage,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function show(): void
    {
        $ctx = $this->requireAuth();
        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        $invoice = $this->invoices->findWithLines($id, $ctx['organization_id']);
        if ($invoice === null) {
            Session::flash('error', 'Invoice not found.');
            $this->redirect('/invoices');
        }

        View::render('invoices/show', [
            'invoice' => $invoice,
            'can_manage' => $this->canManageInvoices($ctx['role']),
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function create(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            Session::flash('error', 'You can view invoices but not create them.');
            $this->redirect('/invoices');
        }

        $clientList = $this->clients->listForOrganization($ctx['organization_id']);

        View::render('invoices/form', [
            'invoice' => null,
            'lines' => $this->defaultFormLines(),
            'clients' => $clientList,
            'is_edit' => false,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
        ]);
    }

    public function store(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices/create');
        }

        $parsed = $this->validatedInvoicePayload($ctx['organization_id']);
        if (is_string($parsed)) {
            Session::flash('error', $parsed);
            $this->redirect('/invoices/create');
        }

        try {
            $id = $this->invoices->create(
                $ctx['organization_id'],
                $parsed['client_id'],
                $parsed['issue_date'],
                $parsed['due_date'],
                $parsed['currency'],
                $parsed['notes'],
                $parsed['lines'],
            );
        } catch (\Throwable) {
            Session::flash('error', 'Could not create invoice. Please try again.');
            $this->redirect('/invoices/create');
        }

        Session::flash('success', 'Invoice saved as draft.');
        $this->redirect('/invoices/show?id=' . $id);
    }

    public function edit(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            Session::flash('error', 'You can view invoices but not edit them.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        $invoice = $this->invoices->findWithLines($id, $ctx['organization_id']);
        if ($invoice === null) {
            Session::flash('error', 'Invoice not found.');
            $this->redirect('/invoices');
        }
        if (($invoice['status'] ?? '') !== 'draft') {
            Session::flash('error', 'Only draft invoices can be edited.');
            $this->redirect('/invoices/show?id=' . $id);
        }

        $clientList = $this->clients->listForOrganization($ctx['organization_id']);

        View::render('invoices/form', [
            'invoice' => $invoice,
            'lines' => $invoice['lines'] ?? [],
            'clients' => $clientList,
            'is_edit' => true,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
            'show_team_nav' => in_array($ctx['role'], ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
        ]);
    }

    public function update(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $idRaw = $this->request->input('id', '');
        if (!is_numeric($idRaw)) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }
        $id = (int) $idRaw;

        $parsed = $this->validatedInvoicePayload($ctx['organization_id']);
        if (is_string($parsed)) {
            Session::flash('error', $parsed);
            $this->redirect('/invoices/edit?id=' . $id);
        }

        try {
            $ok = $this->invoices->updateDraft(
                $id,
                $ctx['organization_id'],
                $parsed['client_id'],
                $parsed['issue_date'],
                $parsed['due_date'],
                $parsed['currency'],
                $parsed['notes'],
                $parsed['lines'],
            );
        } catch (\Throwable) {
            Session::flash('error', 'Could not update invoice.');
            $this->redirect('/invoices/edit?id=' . $id);
        }

        if (!$ok) {
            Session::flash('error', 'Invoice not found or not a draft.');
            $this->redirect('/invoices');
        }

        Session::flash('success', 'Invoice updated.');
        $this->redirect('/invoices/show?id=' . $id);
    }

    public function destroy(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        if ($this->invoices->deleteDraft($id, $ctx['organization_id'])) {
            Session::flash('success', 'Draft discarded.');
        } else {
            Session::flash('error', 'Only draft invoices can be deleted.');
        }
        $this->redirect('/invoices');
    }

    public function send(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        $invoice = $this->invoices->findWithLines($id, $ctx['organization_id']);
        if ($invoice === null) {
            Session::flash('error', 'Invoice not found.');
            $this->redirect('/invoices');
        }
        if (empty($invoice['client_id'])) {
            Session::flash('error', 'Choose a client before marking this invoice as sent.');
            $this->redirect('/invoices/edit?id=' . $id);
        }

        if ($this->invoices->markSent($id, $ctx['organization_id'])) {
            Session::flash('success', 'Invoice marked as sent.');
        } else {
            Session::flash('error', 'Could not mark as sent. It must be a draft with a client.');
        }
        $this->redirect('/invoices/show?id=' . $id);
    }

    public function markPaid(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        if ($this->invoices->markPaid($id, $ctx['organization_id'])) {
            Session::flash('success', 'Invoice marked as paid.');
        } else {
            Session::flash('error', 'Could not mark as paid. It must be sent first.');
        }
        $this->redirect('/invoices/show?id=' . $id);
    }

    public function markVoid(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        if ($this->invoices->markVoid($id, $ctx['organization_id'])) {
            Session::flash('success', 'Invoice voided.');
        } else {
            Session::flash('error', 'Could not void this invoice.');
        }
        $this->redirect('/invoices/show?id=' . $id);
    }

    /** Print-ready HTML; use browser Print → Save as PDF. */
    public function printView(): void
    {
        $ctx = $this->requireAuth();
        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        $invoice = $this->invoices->findWithLines($id, $ctx['organization_id']);
        if ($invoice === null) {
            Session::flash('error', 'Invoice not found.');
            $this->redirect('/invoices');
        }

        $org = $this->organizations->findById($ctx['organization_id']);
        $orgName = is_array($org) ? (string) ($org['name'] ?? '') : '';

        View::render('invoices/print', [
            'invoice' => $invoice,
            'organization_name' => $orgName,
        ]);
    }

    public function emailClient(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageInvoices($ctx['role'])) {
            $this->redirect('/invoices');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/invoices');
        }

        $id = $this->intIdFromRequest();
        if ($id === null) {
            Session::flash('error', 'Invalid invoice.');
            $this->redirect('/invoices');
        }

        $invoice = $this->invoices->findWithLines($id, $ctx['organization_id']);
        if ($invoice === null) {
            Session::flash('error', 'Invoice not found.');
            $this->redirect('/invoices');
        }

        if (($invoice['status'] ?? '') === 'void') {
            Session::flash('error', 'Void invoices cannot be emailed.');
            $this->redirect('/invoices/show?id=' . $id);
        }

        $to = strtolower(trim((string) ($invoice['client_email'] ?? '')));
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'This client needs a valid email address on file.');
            $this->redirect('/invoices/show?id=' . $id);
        }

        $org = $this->organizations->findById($ctx['organization_id']);
        $orgName = is_array($org) && ($org['name'] ?? '') !== '' ? (string) $org['name'] : billo_brand_name();

        if ($this->emailNotifications->sendInvoiceToClient($to, $orgName, $invoice)) {
            Session::flash('success', 'Invoice emailed to ' . $to . '.');
        } else {
            Session::flash('error', 'Could not send email. Check mail configuration.');
        }
        $this->redirect('/invoices/show?id=' . $id);
    }

    private function canManageInvoices(string $role): bool
    {
        return in_array($role, ['owner', 'admin', 'member'], true);
    }

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }

    private function intIdFromRequest(): ?int
    {
        $idRaw = $this->request->input('id', '');
        if (!is_numeric($idRaw)) {
            return null;
        }

        return (int) $idRaw;
    }

    /**
     * @return list<array<string, string>>
     */
    private function defaultFormLines(): array
    {
        return [
            ['description' => '', 'quantity' => '1', 'unit_amount' => '', 'tax_rate' => '0'],
            ['description' => '', 'quantity' => '1', 'unit_amount' => '', 'tax_rate' => '0'],
            ['description' => '', 'quantity' => '1', 'unit_amount' => '', 'tax_rate' => '0'],
        ];
    }

    /**
     * @return array{client_id:?int,issue_date:string,due_date:?string,currency:string,notes:?string,lines:list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}>}|string
     */
    private function validatedInvoicePayload(int $organizationId): array|string
    {
        $clientRaw = $this->request->input('client_id', '');
        $clientId = null;
        if ($clientRaw !== null && $clientRaw !== '') {
            if (!is_numeric($clientRaw)) {
                return 'Invalid client.';
            }
            $cid = (int) $clientRaw;
            if ($this->clients->findForOrganization($cid, $organizationId) === null) {
                return 'Client not found in your organization.';
            }
            $clientId = $cid;
        }

        $issue = $this->request->input('issue_date', '');
        $issue = $issue !== null ? trim($issue) : '';
        if ($issue === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue)) {
            return 'Issue date is required (YYYY-MM-DD).';
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $issue);
        if ($dt === false || $dt->format('Y-m-d') !== $issue) {
            return 'Issue date is not valid.';
        }

        $dueRaw = $this->request->input('due_date', '');
        $dueDate = null;
        if ($dueRaw !== null && trim($dueRaw) !== '') {
            $d = trim($dueRaw);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                return 'Due date must be YYYY-MM-DD or empty.';
            }
            $dtd = \DateTimeImmutable::createFromFormat('Y-m-d', $d);
            if ($dtd === false || $dtd->format('Y-m-d') !== $d) {
                return 'Due date is not valid.';
            }
            $dueDate = $d;
        }

        $currency = strtoupper(trim((string) ($this->request->input('currency', '') ?? 'NGN')));
        if (strlen($currency) !== 3) {
            $currency = 'NGN';
        }

        $notes = $this->trimOrNull($this->request->input('notes', ''), 65535);

        $linesResult = $this->parsePostedLines();
        if (is_string($linesResult)) {
            return $linesResult;
        }

        return [
            'client_id' => $clientId,
            'issue_date' => $issue,
            'due_date' => $dueDate,
            'currency' => $currency,
            'notes' => $notes,
            'lines' => $linesResult,
        ];
    }

    /**
     * @return list<array{description:string,quantity:float,unit_amount:float,tax_rate:float}>|string
     */
    private function parsePostedLines(): array|string
    {
        $raw = $_POST['lines'] ?? null;
        if (!is_array($raw)) {
            return 'Add at least one line item.';
        }

        $lines = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $desc = isset($row['description']) ? trim((string) $row['description']) : '';
            $qtyStr = isset($row['quantity']) ? trim((string) $row['quantity']) : '0';
            $unitStr = isset($row['unit_amount']) ? trim((string) $row['unit_amount']) : '0';
            $taxStr = isset($row['tax_rate']) ? trim((string) $row['tax_rate']) : '0';

            if ($desc === '' && $qtyStr === '' && $unitStr === '' && ($taxStr === '' || $taxStr === '0')) {
                continue;
            }

            if ($desc === '') {
                return 'Each line item needs a description.';
            }

            if (!is_numeric($qtyStr) || !is_numeric($unitStr) || !is_numeric($taxStr)) {
                return 'Quantity, unit price, and tax % must be numbers.';
            }

            $qty = (float) $qtyStr;
            $unit = (float) $unitStr;
            $tax = (float) $taxStr;

            if ($qty <= 0) {
                return 'Quantity must be greater than zero.';
            }
            if ($unit < 0) {
                return 'Unit price cannot be negative.';
            }
            if ($tax < 0 || $tax > 100) {
                return 'Tax rate must be between 0 and 100.';
            }

            if (strlen($desc) > 500) {
                $desc = substr($desc, 0, 500);
            }

            $lines[] = [
                'description' => $desc,
                'quantity' => $qty,
                'unit_amount' => $unit,
                'tax_rate' => $tax,
            ];
        }

        if ($lines === []) {
            return 'Add at least one line item.';
        }

        return $lines;
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
