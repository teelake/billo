<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Paginated cross-tenant listings for system operators only. */
final class PlatformReportsRepository
{
    public const MAX_PER_PAGE = 100;

    public const DEFAULT_PER_PAGE = 25;

    public const MAX_EXPORT_ROWS = 5000;

    public function clampPage(int $page): int
    {
        return max(1, $page);
    }

    public function clampPerPage(int $perPage): int
    {
        if ($perPage <= 0) {
            return self::DEFAULT_PER_PAGE;
        }

        return max(10, min(self::MAX_PER_PAGE, $perPage));
    }

    public function normalizeInvoiceStatus(?string $status): string
    {
        $s = (string) ($status ?? '');

        return in_array($s, ['draft', 'sent', 'paid', 'void'], true) ? $s : '';
    }

    /**
     * @return array{0:?string,1:?string} [from, to] as Y-m-d or [null,null] if invalid
     */
    public function normalizeDateRange(?string $from, ?string $to): array
    {
        $f = trim((string) ($from ?? ''));
        $t = trim((string) ($to ?? ''));
        $df = $f !== '' ? \DateTimeImmutable::createFromFormat('Y-m-d', $f) : false;
        $dt = $t !== '' ? \DateTimeImmutable::createFromFormat('Y-m-d', $t) : false;
        $outF = ($df instanceof \DateTimeImmutable) ? $df->format('Y-m-d') : null;
        $outT = ($dt instanceof \DateTimeImmutable) ? $dt->format('Y-m-d') : null;
        if ($outF !== null && $outT !== null && $outF > $outT) {
            return [$outT, $outF];
        }

        return [$outF, $outT];
    }

    private function likeFragment(string $q): string
    {
        $q = trim($q);

        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q);
    }

    public function countOrganizations(string $q): int
    {
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                return (int) $pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
            }
            $like = '%' . $this->likeFragment($q) . '%';
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM organizations WHERE name LIKE :l ESCAPE \'\\\\\' OR slug LIKE :l2 ESCAPE \'\\\\\''
            );
            $st->execute(['l' => $like, 'l2' => $like]);

            return (int) $st->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * @return list<array{id:int,name:string,slug:string,created_at:string}>
     */
    public function listOrganizations(int $page, int $perPage, string $q): array
    {
        $offset = ($this->clampPage($page) - 1) * $this->clampPerPage($perPage);
        $perPage = $this->clampPerPage($perPage);
        $out = [];
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                $st = $pdo->prepare(
                    'SELECT id, name, slug, created_at FROM organizations ORDER BY id DESC LIMIT :lim OFFSET :off'
                );
                $st->bindValue('lim', $perPage, PDO::PARAM_INT);
                $st->bindValue('off', $offset, PDO::PARAM_INT);
                $st->execute();
            } else {
                $like = '%' . $this->likeFragment($q) . '%';
                $st = $pdo->prepare(
                    'SELECT id, name, slug, created_at FROM organizations
                     WHERE name LIKE :l ESCAPE \'\\\\\' OR slug LIKE :l2 ESCAPE \'\\\\\'
                     ORDER BY id DESC LIMIT :lim OFFSET :off'
                );
                $st->bindValue('l', $like);
                $st->bindValue('l2', $like);
                $st->bindValue('lim', $perPage, PDO::PARAM_INT);
                $st->bindValue('off', $offset, PDO::PARAM_INT);
                $st->execute();
            }
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'slug' => (string) $row['slug'],
                    'created_at' => (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $out;
    }

    public function countInvoices(
        string $status,
        int $organizationId,
        ?string $dateFrom,
        ?string $dateTo,
    ): int {
        try {
            $pdo = Database::pdo();
            $sql = "SELECT COUNT(*) FROM invoices i WHERE i.invoice_kind = 'invoice'";
            $bind = [];
            if ($status !== '') {
                $sql .= ' AND i.status = :st';
                $bind['st'] = $status;
            }
            if ($organizationId > 0) {
                $sql .= ' AND i.organization_id = :oid';
                $bind['oid'] = $organizationId;
            }
            if ($dateFrom !== null) {
                $sql .= ' AND i.issue_date >= :df';
                $bind['df'] = $dateFrom;
            }
            if ($dateTo !== null) {
                $sql .= ' AND i.issue_date <= :dt';
                $bind['dt'] = $dateTo;
            }
            $st = $pdo->prepare($sql);
            $st->execute($bind);

            return (int) $st->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * @return list<array{id:int,organization_id:int,org_name:string,invoice_number:string,status:string,total:string,currency:string,issue_date:string,created_at:string}>
     */
    public function listInvoices(
        int $page,
        int $perPage,
        string $status,
        int $organizationId,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $page = $this->clampPage($page);
        $perPage = $this->clampPerPage($perPage);
        $offset = ($page - 1) * $perPage;
        $out = [];
        try {
            $pdo = Database::pdo();
            $sql = "SELECT i.id, i.organization_id, o.name AS org_name, i.invoice_number, i.status,
                           i.total, i.currency, i.issue_date, i.created_at
                    FROM invoices i
                    INNER JOIN organizations o ON o.id = i.organization_id
                    WHERE i.invoice_kind = 'invoice'";
            $bind = [];
            if ($status !== '') {
                $sql .= ' AND i.status = :st';
                $bind['st'] = $status;
            }
            if ($organizationId > 0) {
                $sql .= ' AND i.organization_id = :oid';
                $bind['oid'] = $organizationId;
            }
            if ($dateFrom !== null) {
                $sql .= ' AND i.issue_date >= :df';
                $bind['df'] = $dateFrom;
            }
            if ($dateTo !== null) {
                $sql .= ' AND i.issue_date <= :dt';
                $bind['dt'] = $dateTo;
            }
            $sql .= ' ORDER BY i.id DESC LIMIT :lim OFFSET :off';
            $st = $pdo->prepare($sql);
            foreach ($bind as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue('lim', $perPage, PDO::PARAM_INT);
            $st->bindValue('off', $offset, PDO::PARAM_INT);
            $st->execute();
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'organization_id' => (int) $row['organization_id'],
                    'org_name' => (string) $row['org_name'],
                    'invoice_number' => (string) $row['invoice_number'],
                    'status' => (string) $row['status'],
                    'total' => number_format((float) $row['total'], 2, '.', ''),
                    'currency' => (string) $row['currency'],
                    'issue_date' => (string) $row['issue_date'],
                    'created_at' => (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $out;
    }

    public function countUsers(string $q): int
    {
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                return (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            }
            $like = '%' . $this->likeFragment($q) . '%';
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM users WHERE email LIKE :l ESCAPE \'\\\\\' OR name LIKE :l2 ESCAPE \'\\\\\''
            );
            $st->execute(['l' => $like, 'l2' => $like]);

            return (int) $st->fetchColumn();
        } catch (\PDOException) {
            return 0;
        }
    }

    /**
     * @return list<array{id:int,email:string,name:string,platform_operator:int,created_at:string}>
     */
    public function listUsers(int $page, int $perPage, string $q): array
    {
        $page = $this->clampPage($page);
        $perPage = $this->clampPerPage($perPage);
        $offset = ($page - 1) * $perPage;
        $out = [];
        $baseSql = 'SELECT u.id, u.email, u.name, u.created_at,
            CASE WHEN g.user_id IS NOT NULL THEN 1 ELSE 0 END AS platform_operator
            FROM users u
            LEFT JOIN platform_admin_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
                AND (g.expires_at IS NULL OR g.expires_at > CURRENT_TIMESTAMP)';
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                $st = $pdo->prepare($baseSql . ' ORDER BY u.id DESC LIMIT :lim OFFSET :off');
                $st->bindValue('lim', $perPage, PDO::PARAM_INT);
                $st->bindValue('off', $offset, PDO::PARAM_INT);
                $st->execute();
            } else {
                $like = '%' . $this->likeFragment($q) . '%';
                $st = $pdo->prepare(
                    $baseSql . ' WHERE u.email LIKE :l ESCAPE \'\\\\\' OR u.name LIKE :l2 ESCAPE \'\\\\\'
                     ORDER BY u.id DESC LIMIT :lim OFFSET :off'
                );
                $st->bindValue('l', $like);
                $st->bindValue('l2', $like);
                $st->bindValue('lim', $perPage, PDO::PARAM_INT);
                $st->bindValue('off', $offset, PDO::PARAM_INT);
                $st->execute();
            }
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'email' => (string) $row['email'],
                    'name' => (string) $row['name'],
                    'platform_operator' => (int) $row['platform_operator'],
                    'created_at' => (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $out;
    }

    /**
     * @return list<list<string|int>>
     */
    public function exportOrganizationsCsv(string $q): array
    {
        $rows = [];
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                $st = $pdo->query(
                    'SELECT id, name, slug, created_at FROM organizations ORDER BY id DESC LIMIT ' . self::MAX_EXPORT_ROWS
                );
            } else {
                $like = '%' . $this->likeFragment($q) . '%';
                $st = $pdo->prepare(
                    'SELECT id, name, slug, created_at FROM organizations
                     WHERE name LIKE :l ESCAPE \'\\\\\' OR slug LIKE :l2 ESCAPE \'\\\\\'
                     ORDER BY id DESC LIMIT ' . self::MAX_EXPORT_ROWS
                );
                $st->execute(['l' => $like, 'l2' => $like]);
            }
            if (!$st) {
                return [];
            }
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    (int) $row['id'],
                    (string) $row['name'],
                    (string) $row['slug'],
                    (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $rows;
    }

    /**
     * @return list<list<string|int|float>>
     */
    public function exportInvoicesCsv(
        string $status,
        int $organizationId,
        ?string $dateFrom,
        ?string $dateTo,
    ): array {
        $rows = [];
        try {
            $pdo = Database::pdo();
            $sql = "SELECT i.id, i.organization_id, o.name AS org_name, i.invoice_number, i.status,
                           i.total, i.currency, i.issue_date, i.created_at
                    FROM invoices i
                    INNER JOIN organizations o ON o.id = i.organization_id
                    WHERE i.invoice_kind = 'invoice'";
            $bind = [];
            if ($status !== '') {
                $sql .= ' AND i.status = :st';
                $bind['st'] = $status;
            }
            if ($organizationId > 0) {
                $sql .= ' AND i.organization_id = :oid';
                $bind['oid'] = $organizationId;
            }
            if ($dateFrom !== null) {
                $sql .= ' AND i.issue_date >= :df';
                $bind['df'] = $dateFrom;
            }
            if ($dateTo !== null) {
                $sql .= ' AND i.issue_date <= :dt';
                $bind['dt'] = $dateTo;
            }
            $sql .= ' ORDER BY i.id DESC LIMIT ' . self::MAX_EXPORT_ROWS;
            $st = $pdo->prepare($sql);
            $st->execute($bind);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    (int) $row['id'],
                    (int) $row['organization_id'],
                    (string) $row['org_name'],
                    (string) $row['invoice_number'],
                    (string) $row['status'],
                    round((float) $row['total'], 2),
                    (string) $row['currency'],
                    (string) $row['issue_date'],
                    (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $rows;
    }

    /**
     * @return list<list<string|int>>
     */
    public function exportUsersCsv(string $q): array
    {
        $rows = [];
        $baseSql = 'SELECT u.id, u.email, u.name, u.created_at,
            CASE WHEN g.user_id IS NOT NULL THEN 1 ELSE 0 END AS platform_operator
            FROM users u
            LEFT JOIN platform_admin_grants g ON g.user_id = u.id AND g.revoked_at IS NULL
                AND (g.expires_at IS NULL OR g.expires_at > CURRENT_TIMESTAMP)';
        try {
            $pdo = Database::pdo();
            if (trim($q) === '') {
                $st = $pdo->query($baseSql . ' ORDER BY u.id DESC LIMIT ' . self::MAX_EXPORT_ROWS);
            } else {
                $like = '%' . $this->likeFragment($q) . '%';
                $st = $pdo->prepare(
                    $baseSql . ' WHERE u.email LIKE :l ESCAPE \'\\\\\' OR u.name LIKE :l2 ESCAPE \'\\\\\'
                     ORDER BY u.id DESC LIMIT ' . self::MAX_EXPORT_ROWS
                );
                $st->execute(['l' => $like, 'l2' => $like]);
            }
            if (!$st) {
                return [];
            }
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    (int) $row['id'],
                    (string) $row['email'],
                    (string) $row['name'],
                    (int) $row['platform_operator'],
                    (string) $row['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return $rows;
    }
}
