<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\ClientRepository;

final class ClientController extends Controller
{
    public function __construct(
        private Request $request,
        private ClientRepository $clients = new ClientRepository(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuth();
        $list = $this->clients->listForOrganization($ctx['organization_id']);
        $canManage = $this->canManageClients($ctx['role']);

        View::render('clients/index', [
            'clients' => $list,
            'can_manage' => $canManage,
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
        if (!$this->canManageClients($ctx['role'])) {
            Session::flash('error', 'You can view clients but not add or edit them.');
            $this->redirect('/clients');
        }

        View::render('clients/form', [
            'client' => null,
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
        if (!$this->canManageClients($ctx['role'])) {
            $this->redirect('/clients');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/clients/create');
        }

        $payload = $this->validatedPayload();
        if (is_string($payload)) {
            Session::flash('error', $payload);
            $this->redirect('/clients/create');
        }

        $this->clients->create($ctx['organization_id'], $payload);
        Session::flash('success', 'Client added.');
        $this->redirect('/clients');
    }

    public function edit(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageClients($ctx['role'])) {
            Session::flash('error', 'You can view clients but not edit them.');
            $this->redirect('/clients');
        }

        $idRaw = $this->request->input('id', '');
        if (!is_numeric($idRaw)) {
            Session::flash('error', 'Invalid client.');
            $this->redirect('/clients');
        }

        $client = $this->clients->findForOrganization((int) $idRaw, $ctx['organization_id']);
        if ($client === null) {
            Session::flash('error', 'Client not found.');
            $this->redirect('/clients');
        }

        View::render('clients/form', [
            'client' => $client,
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
        if (!$this->canManageClients($ctx['role'])) {
            $this->redirect('/clients');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/clients');
        }

        $idRaw = $this->request->input('id', '');
        if (!is_numeric($idRaw)) {
            Session::flash('error', 'Invalid client.');
            $this->redirect('/clients');
        }
        $id = (int) $idRaw;

        $payload = $this->validatedPayload();
        if (is_string($payload)) {
            Session::flash('error', $payload);
            $this->redirect('/clients/edit?id=' . $id);
        }

        if (!$this->clients->update($id, $ctx['organization_id'], $payload)) {
            Session::flash('error', 'Could not update client.');
            $this->redirect('/clients/edit?id=' . $id);
        }

        Session::flash('success', 'Client updated.');
        $this->redirect('/clients');
    }

    public function destroy(): void
    {
        $ctx = $this->requireAuth();
        if (!$this->canManageClients($ctx['role'])) {
            $this->redirect('/clients');
        }
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/clients');
        }

        $idRaw = $this->request->input('id', '');
        if (!is_numeric($idRaw)) {
            Session::flash('error', 'Invalid client.');
            $this->redirect('/clients');
        }

        if ($this->clients->delete((int) $idRaw, $ctx['organization_id'])) {
            Session::flash('success', 'Client removed.');
        } else {
            Session::flash('error', 'Could not remove client.');
        }
        $this->redirect('/clients');
    }

    private function canManageClients(string $role): bool
    {
        return in_array($role, ['owner', 'admin', 'member'], true);
    }

    /**
     * @return array<string, string|null>|string
     */
    private function validatedPayload(): array|string
    {
        $name = $this->request->input('name', '');
        $name = $name !== null ? trim($name) : '';
        if ($name === '' || strlen($name) > 200) {
            return 'Display name is required (max 200 characters).';
        }

        $company = $this->trimOrNull($this->request->input('company_name', ''), 200);
        $emailRaw = $this->trimOrNull($this->request->input('email', ''), 255);
        if ($emailRaw !== null && !filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
            return 'Please enter a valid email address or leave it blank.';
        }

        $phone = $this->trimOrNull($this->request->input('phone', ''), 40);
        $line1 = $this->trimOrNull($this->request->input('address_line1', ''), 255);
        $line2 = $this->trimOrNull($this->request->input('address_line2', ''), 255);
        $city = $this->trimOrNull($this->request->input('city', ''), 120);
        $state = $this->trimOrNull($this->request->input('state', ''), 120);
        $country = strtoupper(trim((string) ($this->request->input('country', '') ?? 'NG')));
        if (strlen($country) !== 2) {
            $country = 'NG';
        }
        $taxId = $this->trimOrNull($this->request->input('tax_id', ''), 64);
        $notes = $this->trimOrNull($this->request->input('notes', ''), 65535);

        return [
            'name' => $name,
            'company_name' => $company,
            'email' => $emailRaw !== null ? strtolower($emailRaw) : null,
            'phone' => $phone,
            'address_line1' => $line1,
            'address_line2' => $line2,
            'city' => $city,
            'state' => $state,
            'country' => $country,
            'tax_id' => $taxId,
            'notes' => $notes,
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

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }
}
