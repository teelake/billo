<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\InvitationRepository;
use App\Repositories\MemberRepository;
use App\Repositories\OrganizationRepository;
use App\Services\EmailNotifications;
use App\Support\SecureToken;
use DateInterval;
use DateTimeImmutable;

final class TeamController extends Controller
{
    public function __construct(
        private Request $request,
        private InvitationRepository $invitations = new InvitationRepository(),
        private MemberRepository $members = new MemberRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private EmailNotifications $emails = new EmailNotifications(),
    ) {
    }

    public function index(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        $orgId = $ctx['organization_id'];
        $org = $this->organizations->findById($orgId);
        $members = $this->members->listMembersForOrganization($orgId);
        $pending = $this->invitations->listPendingForOrg($orgId);

        View::render('team/index', [
            'organization' => $org,
            'members' => $members,
            'pending_invitations' => $pending,
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
            'user_name' => (string) Session::get('user_name', ''),
            'role' => $ctx['role'],
        ]);
    }

    public function invite(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/team');
        }

        $email = $this->request->input('email', '');
        $role = strtolower((string) $this->request->input('role', 'member'));
        if (!in_array($role, ['admin', 'member', 'viewer'], true)) {
            $role = 'member';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid email address.');
            $this->redirect('/team');
        }
        $email = strtolower(trim($email));

        if ($this->members->isEmailMemberOfOrganization($ctx['organization_id'], $email)) {
            Session::flash('error', 'That person is already in this organization.');
            $this->redirect('/team');
        }

        $org = $this->organizations->findById($ctx['organization_id']);
        if ($org === null) {
            Session::flash('error', 'Organization not found.');
            $this->redirect('/dashboard');
        }

        $plain = SecureToken::plain();
        $hash = SecureToken::hash($plain);
        $days = (int) \App\Core\Config::get('auth.invitation_ttl_days', 7);
        $days = max(1, $days);
        $exp = (new DateTimeImmutable())->add(new DateInterval('P' . $days . 'D'));

        $this->invitations->deletePendingForOrgEmail($ctx['organization_id'], $email);
        $this->invitations->create(
            $ctx['organization_id'],
            $email,
            $role,
            $hash,
            $ctx['user_id'],
            $exp
        );

        $inviterName = (string) Session::get('user_name', 'A teammate');
        $roleLabel = ucfirst($role);
        $sent = $this->emails->sendOrganizationInvite(
            $email,
            (string) $org['name'],
            $inviterName,
            $roleLabel,
            $plain
        );
        if (!$sent) {
            Session::flash('error', 'Invitation saved, but the email could not be sent. Check mail configuration.');
            $this->redirect('/team');
        }

        Session::flash('success', 'Invitation sent to ' . $email . '.');
        $this->redirect('/team');
    }

    public function revokeInvite(): void
    {
        $ctx = $this->requireAuthRole(['owner', 'admin']);
        if (!$this->validateCsrf()) {
            Session::flash('error', 'Invalid session. Please try again.');
            $this->redirect('/team');
        }

        $idRaw = $this->request->input('invitation_id', '');
        if (!is_numeric($idRaw)) {
            Session::flash('error', 'Invalid invitation.');
            $this->redirect('/team');
        }

        if ($this->invitations->revoke((int) $idRaw, $ctx['organization_id'])) {
            Session::flash('success', 'Invitation cancelled.');
        } else {
            Session::flash('error', 'Could not cancel that invitation.');
        }
        $this->redirect('/team');
    }

    private function validateCsrf(): bool
    {
        return Csrf::validate($this->request->input('_csrf'));
    }
}
