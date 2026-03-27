<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Cross-tenant metrics for system operators only. */
final class PlatformAnalyticsRepository
{
    /**
     * @return array{
     *   users:int, organizations:int, clients:int,
     *   invoices:int, invoices_paid:int,
     *   revenue_paid:string, invoice_line_items:int
     * }
     */
    public function summary(): array
    {
        $pdo = Database::pdo();
        $defaults = [
            'users' => 0,
            'organizations' => 0,
            'clients' => 0,
            'invoices' => 0,
            'invoices_paid' => 0,
            'revenue_paid' => '0.00',
            'invoice_line_items' => 0,
        ];

        try {
            $defaults['users'] = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
            $defaults['organizations'] = (int) $pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
            $defaults['clients'] = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();
            $defaults['invoices'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM invoices WHERE invoice_kind = 'invoice'"
            )->fetchColumn();
            $defaults['invoices_paid'] = (int) $pdo->query(
                "SELECT COUNT(*) FROM invoices WHERE invoice_kind = 'invoice' AND status = 'paid'"
            )->fetchColumn();
            $defaults['invoice_line_items'] = (int) $pdo->query('SELECT COUNT(*) FROM invoice_line_items')->fetchColumn();

            $rev = $pdo->query(
                "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE invoice_kind = 'invoice' AND status = 'paid'"
            )->fetchColumn();
            $defaults['revenue_paid'] = $rev !== false ? number_format((float) $rev, 2, '.', '') : '0.00';
        } catch (\PDOException) {
            // return defaults
        }

        return $defaults;
    }

    /**
     * @return array<string, int>
     */
    public function invoiceStatusBreakdown(): array
    {
        try {
            $stmt = Database::pdo()->query(
                "SELECT status, COUNT(*) AS c FROM invoices WHERE invoice_kind = 'invoice' GROUP BY status"
            );
            $out = ['draft' => 0, 'sent' => 0, 'paid' => 0, 'void' => 0];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $s = (string) ($row['status'] ?? '');
                if (isset($out[$s])) {
                    $out[$s] = (int) $row['c'];
                }
            }

            return $out;
        } catch (\PDOException) {
            return ['draft' => 0, 'sent' => 0, 'paid' => 0, 'void' => 0];
        }
    }

    /**
     * Last N calendar months buckets (YYYY-MM) with counts / sums.
     *
     * @return array{
     *   months:list<string>,
     *   new_users:list<int>, new_orgs:list<int>,
     *   new_invoices:list<int>, paid_totals:list<float>
     * }
     */
    public function monthlyTrends(int $monthCount = 12): array
    {
        $months = [];
        $t = new \DateTimeImmutable('first day of this month');
        for ($i = $monthCount - 1; $i >= 0; $i--) {
            $months[] = $t->modify("-{$i} months")->format('Y-m');
        }

        $empty = static fn () => array_fill(0, count($months), 0);
        $result = [
            'months' => $months,
            'new_users' => $empty(),
            'new_orgs' => $empty(),
            'new_invoices' => $empty(),
            'paid_totals' => array_map(static fn () => 0.0, $months),
        ];

        try {
            $pdo = Database::pdo();

            $stmt = $pdo->query(
                'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS ym, COUNT(*) AS c FROM users
                 WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), \'%Y-%m-01\'), INTERVAL ' . (int) ($monthCount - 1) . ' MONTH)
                 GROUP BY ym ORDER BY ym'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            $idxMap = array_flip($months);
            foreach ($rows as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['new_users'][$idxMap[$ym]] = (int) ($r['c'] ?? 0);
                }
            }

            $stmt = $pdo->query(
                'SELECT DATE_FORMAT(created_at, \'%Y-%m\') AS ym, COUNT(*) AS c FROM organizations
                 WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), \'%Y-%m-01\'), INTERVAL ' . (int) ($monthCount - 1) . ' MONTH)
                 GROUP BY ym ORDER BY ym'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['new_orgs'][$idxMap[$ym]] = (int) ($r['c'] ?? 0);
                }
            }

            $stmt = $pdo->query(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM invoices
                 WHERE invoice_kind = 'invoice'
                 AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL " . (int) ($monthCount - 1) . ' MONTH)
                 GROUP BY ym ORDER BY ym'
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['new_invoices'][$idxMap[$ym]] = (int) ($r['c'] ?? 0);
                }
            }

            $stmt = $pdo->query(
                "SELECT DATE_FORMAT(COALESCE(paid_at, updated_at), '%Y-%m') AS ym, SUM(total) AS s FROM invoices
                 WHERE invoice_kind = 'invoice' AND status = 'paid'
                 AND COALESCE(paid_at, updated_at) >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL " . (int) ($monthCount - 1) . " MONTH)
                 GROUP BY ym ORDER BY ym"
            );
            $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            foreach ($rows as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['paid_totals'][$idxMap[$ym]] = (float) ($r['s'] ?? 0);
                }
            }
        } catch (\PDOException) {
            // keep zeros
        }

        return $result;
    }

    /**
     * @return list<array{id:int, name:string, invoice_count:int, member_count:int, paid_total:float}>
     */
    public function topOrganizationsByVolume(int $limit = 10): array
    {
        try {
            $lim = max(1, min(50, $limit));
            $sql = <<<SQL
SELECT o.id, o.name,
       (SELECT COUNT(*) FROM invoices i WHERE i.organization_id = o.id AND i.invoice_kind = 'invoice') AS invoice_count,
       (SELECT COUNT(*) FROM organization_members m WHERE m.organization_id = o.id) AS member_count,
       (SELECT COALESCE(SUM(i2.total), 0) FROM invoices i2
        WHERE i2.organization_id = o.id AND i2.invoice_kind = 'invoice' AND i2.status = 'paid') AS paid_total
FROM organizations o
ORDER BY invoice_count DESC, paid_total DESC
LIMIT {$lim}
SQL;
            $stmt = Database::pdo()->query($sql);
            if (!$stmt) {
                return [];
            }
            $out = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $out[] = [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['name'],
                    'invoice_count' => (int) $row['invoice_count'],
                    'member_count' => (int) $row['member_count'],
                    'paid_total' => (float) $row['paid_total'],
                ];
            }

            return $out;
        } catch (\PDOException) {
            return [];
        }
    }

    /**
     * @return array{headers:list<string>, rows:list<list<string|int|float>>}
     */
    public function organizationsReportCsvRows(): array
    {
        $headers = ['organization_id', 'name', 'slug', 'members', 'clients', 'invoices', 'paid_total', 'created_at'];
        $rows = [];
        try {
            $sql = <<<SQL
SELECT o.id, o.name, o.slug, o.created_at,
       (SELECT COUNT(*) FROM organization_members m WHERE m.organization_id = o.id) AS members,
       (SELECT COUNT(*) FROM clients c WHERE c.organization_id = o.id) AS clients,
       (SELECT COUNT(*) FROM invoices i WHERE i.organization_id = o.id AND i.invoice_kind = 'invoice') AS invoices,
       (SELECT COALESCE(SUM(i2.total), 0) FROM invoices i2
        WHERE i2.organization_id = o.id AND i2.invoice_kind = 'invoice' AND i2.status = 'paid') AS paid_total
FROM organizations o
ORDER BY o.id
SQL;
            $stmt = Database::pdo()->query($sql);
            if (!$stmt) {
                return ['headers' => $headers, 'rows' => []];
            }
            while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $rows[] = [
                    (int) $r['id'],
                    (string) $r['name'],
                    (string) $r['slug'],
                    (int) $r['members'],
                    (int) $r['clients'],
                    (int) $r['invoices'],
                    round((float) $r['paid_total'], 2),
                    (string) $r['created_at'],
                ];
            }
        } catch (\PDOException) {
        }

        return ['headers' => $headers, 'rows' => $rows];
    }
}
