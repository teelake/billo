<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Repositories\OrganizationRepository;
use App\Repositories\PlatformAdminGrantRepository;
use App\Repositories\PlatformAnalyticsRepository;
use App\Repositories\PlatformReportsRepository;
use App\Repositories\PlatformSettingsRepository;
use App\Repositories\UserRepository;
use App\Services\PlatformConfigurationStore;
use App\Services\PlatformSettings;
use DateTimeImmutable;
use PDOException;

final class SystemAdminController extends \App\Core\Controller
{
    public function __construct(
        private Request $request,
        private UserRepository $users = new UserRepository(),
        private OrganizationRepository $organizations = new OrganizationRepository(),
        private PlatformAnalyticsRepository $platformAnalytics = new PlatformAnalyticsRepository(),
        private PlatformReportsRepository $platformReports = new PlatformReportsRepository(),
        private PlatformConfigurationStore $platformConfig = new PlatformConfigurationStore(),
        private PlatformAdminGrantRepository $platformAdminGrants = new PlatformAdminGrantRepository(),
    ) {
    }

    public function index(): void
    {
        $this->requireSystemAdmin();

        try {
            $userCount = $this->users->countAll();
            $orgCount = $this->organizations->countAll();
            $invoiceCount = (int) Database::pdo()->query('SELECT COUNT(*) FROM invoices')->fetchColumn();
        } catch (PDOException) {
            Session::flash('error', 'Could not load statistics.');
            $userCount = $orgCount = $invoiceCount = 0;
        }

        View::render('system/index', [
            'user_count' => $userCount,
            'organization_count' => $orgCount,
            'invoice_count' => $invoiceCount,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function analytics(): void
    {
        $this->requireSystemAdmin();

        $summary = $this->platformAnalytics->summary();
        $status = $this->platformAnalytics->invoiceStatusBreakdown();
        $trends = $this->platformAnalytics->monthlyTrends(12);
        $topOrgs = $this->platformAnalytics->topOrganizationsByVolume(10);

        $monthShort = static function (string $ym): string {
            $t = date_create($ym . '-01');

            return $t ? $t->format('M Y') : $ym;
        };

        $labels = [];
        foreach ($trends['months'] as $ym) {
            $labels[] = $monthShort($ym);
        }

        View::render('system/analytics', [
            'summary' => $summary,
            'status' => $status,
            'trends' => $trends,
            'trend_labels' => $labels,
            'top_orgs' => $topOrgs,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function analyticsExport(): void
    {
        $this->requireSystemAdmin();

        $data = $this->platformAnalytics->organizationsReportCsvRows();
        $filename = 'billo-platform-orgs-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if ($out !== false) {
            fputcsv($out, $data['headers']);
            foreach ($data['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }
        exit;
    }

    public function reports(): void
    {
        $this->requireSystemAdmin();

        $type = $this->request->input('type', 'organizations') ?? 'organizations';
        if (!in_array($type, ['organizations', 'invoices', 'users'], true)) {
            $type = 'organizations';
        }
        $page = (int) ($this->request->input('page', '1') ?? '1');
        $perPage = (int) ($this->request->input('per_page', (string) PlatformReportsRepository::DEFAULT_PER_PAGE)
            ?? (string) PlatformReportsRepository::DEFAULT_PER_PAGE);
        $page = $this->platformReports->clampPage($page);
        $perPage = $this->platformReports->clampPerPage($perPage);

        $q = (string) ($this->request->input('q', '') ?? '');
        $status = $this->platformReports->normalizeInvoiceStatus($this->request->input('status', '') ?? '');
        $orgIdRaw = $this->request->input('organization_id', '') ?? '';
        $organizationId = max(0, (int) preg_replace('/\D/', '', (string) $orgIdRaw));
        [$dateFrom, $dateTo] = $this->platformReports->normalizeDateRange(
            $this->request->input('from', '') ?? '',
            $this->request->input('to', '') ?? '',
        );
        $fromStr = $dateFrom ?? '';
        $toStr = $dateTo ?? '';

        [$createdFrom, $createdTo] = $this->platformReports->normalizeDateRange(
            $this->request->input('created_from', '') ?? '',
            $this->request->input('created_to', '') ?? '',
        );
        $createdFromStr = $createdFrom ?? '';
        $createdToStr = $createdTo ?? '';

        $invQRaw = (string) ($this->request->input('inv_q', '') ?? '');
        $invQ = mb_strlen($invQRaw, 'UTF-8') > 80 ? mb_substr($invQRaw, 0, 80, 'UTF-8') : $invQRaw;

        $rows = [];
        $total = 0;
        if ($type === 'organizations') {
            $total = $this->platformReports->countOrganizations($q, $createdFrom, $createdTo);
        } elseif ($type === 'invoices') {
            $total = $this->platformReports->countInvoices($status, $organizationId, $dateFrom, $dateTo, $invQ);
        } else {
            $total = $this->platformReports->countUsers($q, $createdFrom, $createdTo);
        }

        $totalPages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $totalPages);

        if ($type === 'organizations') {
            $rows = $this->platformReports->listOrganizations($page, $perPage, $q);
        } elseif ($type === 'invoices') {
            $rows = $this->platformReports->listInvoices($page, $perPage, $status, $organizationId, $dateFrom, $dateTo);
        } else {
            $rows = $this->platformReports->listUsers($page, $perPage, $q);
        }

        $queryForLinks = array_filter([
            'type' => $type,
            'q' => $q,
            'status' => $status,
            'organization_id' => $organizationId > 0 ? (string) $organizationId : '',
            'from' => $fromStr,
            'to' => $toStr,
            'per_page' => (string) $perPage,
        ], static fn ($v) => $v !== null && $v !== '');

        View::render('system/reports', [
            'report_type' => $type,
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'q' => $q,
            'status' => $status,
            'organization_id' => $organizationId,
            'from' => $fromStr,
            'to' => $toStr,
            'created_from' => $createdFromStr,
            'created_to' => $createdToStr,
            'inv_q' => $invQ,
            'query_for_links' => $queryForLinks,
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function reportsExport(): void
    {
        $this->requireSystemAdmin();

        $type = $this->request->input('type', 'organizations') ?? 'organizations';
        if (!in_array($type, ['organizations', 'invoices', 'users'], true)) {
            $type = 'organizations';
        }
        $q = (string) ($this->request->input('q', '') ?? '');
        $status = $this->platformReports->normalizeInvoiceStatus($this->request->input('status', '') ?? '');
        $orgIdRaw = $this->request->input('organization_id', '') ?? '';
        $organizationId = max(0, (int) preg_replace('/\D/', '', (string) $orgIdRaw));
        [$dateFrom, $dateTo] = $this->platformReports->normalizeDateRange(
            $this->request->input('from', '') ?? '',
            $this->request->input('to', '') ?? '',
        );

        $filename = 'billo-report-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if ($out === false) {
            exit;
        }

        if ($type === 'organizations') {
            fputcsv($out, ['row', 'organization_id', 'name', 'slug', 'created_at']);
            $rn = 0;
            foreach ($this->platformReports->exportOrganizationsCsv($q, $createdFrom, $createdTo) as $row) {
                ++$rn;
                fputcsv($out, array_merge([$rn], $row));
            }
        } elseif ($type === 'invoices') {
            fputcsv($out, [
                'row', 'invoice_id', 'organization_id', 'organization', 'invoice_number', 'status',
                'total', 'currency', 'issue_date', 'created_at',
            ]);
            $rn = 0;
            foreach ($this->platformReports->exportInvoicesCsv($status, $organizationId, $dateFrom, $dateTo, $invQ) as $row) {
                ++$rn;
                fputcsv($out, array_merge([$rn], $row));
            }
        } else {
            fputcsv($out, ['row', 'user_id', 'email', 'name', 'platform_operator', 'created_at']);
            $rn = 0;
            foreach ($this->platformReports->exportUsersCsv($q, $createdFrom, $createdTo) as $row) {
                ++$rn;
                fputcsv($out, array_merge([$rn], $row));
            }
        }
        fclose($out);
        exit;
    }

    public function configuration(): void
    {
        $this->requireSystemAdmin();

        if (strtoupper($this->request->method) === 'POST') {
            if (!Csrf::validate($this->request->input('_csrf'))) {
                Session::flash('error', 'Invalid session. Try again.');
                $this->redirect('/system/configuration');
            }
            $errors = $this->platformConfig->save($this->request);
            if ($errors !== []) {
                Session::flash('error', implode(' ', $errors));
                $this->redirect('/system/configuration');
            }
            PlatformSettings::applyFromDatabase();
            Session::flash('success', 'Configuration saved. Database overrides are merged into runtime settings.');
            $this->redirect('/system/configuration');
        }

        View::render('system/configuration', [
            'db_setting_keys' => (new PlatformSettingsRepository())->allKeys(),
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function operators(): void
    {
        $this->requireSystemAdmin();

        View::render('system/operators', [
            'operators' => $this->platformAdminGrants->listActiveWithUsers(),
            'active_count' => $this->platformAdminGrants->countActiveGrants(),
            'current_user_id' => (int) Session::get('user_id', 0),
            'user_name' => (string) Session::get('user_name', ''),
            'role' => (string) Session::get('role', 'owner'),
            'show_team_nav' => in_array(Session::get('role', ''), ['owner', 'admin'], true),
            'error' => Session::flash('error') ?? '',
            'success' => Session::flash('success') ?? '',
        ]);
    }

    public function operatorsGrant(): void
    {
        $this->requireSystemAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/system/operators');
        }

        $emailRaw = $this->request->input('email', '') ?? '';
        $email = strtolower(trim((string) $emailRaw));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Enter a valid email address.');
            $this->redirect('/system/operators');
        }

        $user = $this->users->findByEmail($email);
        if ($user === null) {
            Session::flash('error', 'No account with that email. The person must sign up first.');
            $this->redirect('/system/operators');
        }

        $notesRaw = $this->request->input('notes', '') ?? '';
        $notes = trim((string) $notesRaw);
        $notesVal = $notes === '' ? null : (function_exists('mb_substr')
            ? mb_substr($notes, 0, 500, 'UTF-8')
            : substr($notes, 0, 500));

        $expSql = null;
        $expRaw = $this->request->input('expires_at', '') ?? '';
        $expTrim = trim((string) $expRaw);
        if ($expTrim !== '') {
            $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $expTrim);
            if ($dt === false) {
                Session::flash('error', 'Use a valid expiry date and time.');
                $this->redirect('/system/operators');
            }
            if ($dt <= new DateTimeImmutable('now')) {
                Session::flash('error', 'Expiry must be in the future.');
                $this->redirect('/system/operators');
            }
            $expSql = $dt->format('Y-m-d H:i:s');
        }

        $me = (int) Session::get('user_id', 0);
        $this->platformAdminGrants->ensureGrant((int) $user['id'], $me > 0 ? $me : null, $notesVal, $expSql);

        Session::flash('success', 'Platform operator access granted for ' . $email . '.');
        $this->redirect('/system/operators');
    }

    public function operatorsRevoke(): void
    {
        $this->requireSystemAdmin();
        if (!Csrf::validate($this->request->input('_csrf'))) {
            Session::flash('error', 'Invalid session. Try again.');
            $this->redirect('/system/operators');
        }

        $target = (int) ($this->request->input('user_id', '0') ?? '0');
        if ($target <= 0) {
            Session::flash('error', 'Invalid user.');
            $this->redirect('/system/operators');
        }

        if (!$this->platformAdminGrants->userHasActiveGrant($target)) {
            Session::flash('error', 'That user does not have active platform access.');
            $this->redirect('/system/operators');
        }

        if ($this->platformAdminGrants->countActiveGrants() <= 1) {
            Session::flash('error', 'You cannot revoke the last platform operator. Grant another operator first.');
            $this->redirect('/system/operators');
        }

        $this->platformAdminGrants->revoke($target);

        Session::flash('success', 'Platform operator access revoked.');
        $this->redirect('/system/operators');
    }

    private function requireSystemAdmin(): void
    {
        $ctx = $this->authContext();
        if ($ctx === null) {
            Session::flash('error', 'Sign in to continue.');
            $this->redirect('/login');
        }
        if (!billo_is_system_admin()) {
            Session::flash('error', 'You do not have system operator access.');
            $this->redirect('/dashboard');
        }
        Session::set('app_nav_mode', 'platform');
    }
}
