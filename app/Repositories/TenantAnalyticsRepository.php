<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/** Organization-scoped analytics for owners and admins. */
final class TenantAnalyticsRepository
{
    /**
     * @return array{
     *   clients:int, invoices:int, invoices_paid:int,
     *   revenue_paid:string, draft_value:string, sent_value:string
     * }
     */
    public function summary(int $organizationId): array
    {
        $defaults = [
            'clients' => 0,
            'invoices' => 0,
            'invoices_paid' => 0,
            'revenue_paid' => '0.00',
            'draft_value' => '0.00',
            'sent_value' => '0.00',
        ];
        if ($organizationId <= 0) {
            return $defaults;
        }

        try {
            $pdo = Database::pdo();
            $st = $pdo->prepare('SELECT COUNT(*) FROM clients WHERE organization_id = :oid');
            $st->execute(['oid' => $organizationId]);
            $defaults['clients'] = (int) $st->fetchColumn();

            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM invoices WHERE organization_id = :oid AND invoice_kind = 'invoice'"
            );
            $st->execute(['oid' => $organizationId]);
            $defaults['invoices'] = (int) $st->fetchColumn();

            $st = $pdo->prepare(
                "SELECT COUNT(*) FROM invoices WHERE organization_id = :oid AND invoice_kind = 'invoice' AND status = 'paid'"
            );
            $st->execute(['oid' => $organizationId]);
            $defaults['invoices_paid'] = (int) $st->fetchColumn();

            $st = $pdo->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE organization_id = :oid AND invoice_kind = 'invoice' AND status = 'paid'"
            );
            $st->execute(['oid' => $organizationId]);
            $defaults['revenue_paid'] = number_format((float) $st->fetchColumn(), 2, '.', '');

            $st = $pdo->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE organization_id = :oid AND invoice_kind = 'invoice' AND status = 'draft'"
            );
            $st->execute(['oid' => $organizationId]);
            $defaults['draft_value'] = number_format((float) $st->fetchColumn(), 2, '.', '');

            $st = $pdo->prepare(
                "SELECT COALESCE(SUM(total), 0) FROM invoices WHERE organization_id = :oid AND invoice_kind = 'invoice' AND status = 'sent'"
            );
            $st->execute(['oid' => $organizationId]);
            $defaults['sent_value'] = number_format((float) $st->fetchColumn(), 2, '.', '');
        } catch (\PDOException) {
        }

        return $defaults;
    }

    /**
     * @return array<string, int>
     */
    public function invoiceStatusBreakdown(int $organizationId): array
    {
        $out = ['draft' => 0, 'sent' => 0, 'paid' => 0, 'void' => 0];
        if ($organizationId <= 0) {
            return $out;
        }
        try {
            $st = Database::pdo()->prepare(
                "SELECT status, COUNT(*) AS c FROM invoices
                 WHERE organization_id = :oid AND invoice_kind = 'invoice' GROUP BY status"
            );
            $st->execute(['oid' => $organizationId]);
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $s = (string) ($row['status'] ?? '');
                if (isset($out[$s])) {
                    $out[$s] = (int) $row['c'];
                }
            }
        } catch (\PDOException) {
        }

        return $out;
    }

    /**
     * @return array{months:list<string>, new_invoices:list<int>, paid_totals:list<float>}
     */
    public function monthlyTrends(int $organizationId, int $monthCount = 12): array
    {
        $months = [];
        $t = new \DateTimeImmutable('first day of this month');
        for ($i = $monthCount - 1; $i >= 0; $i--) {
            $months[] = $t->modify("-{$i} months")->format('Y-m');
        }
        $idxMap = array_flip($months);
        $result = [
            'months' => $months,
            'new_invoices' => array_fill(0, count($months), 0),
            'paid_totals' => array_map(static fn () => 0.0, $months),
        ];
        if ($organizationId <= 0) {
            return $result;
        }

        try {
            $pdo = Database::pdo();
            $mc = (int) ($monthCount - 1);
            $st = $pdo->prepare(
                "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS c FROM invoices
                 WHERE organization_id = :oid AND invoice_kind = 'invoice'
                 AND created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL {$mc} MONTH)
                 GROUP BY ym ORDER BY ym"
            );
            $st->execute(['oid' => $organizationId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['new_invoices'][$idxMap[$ym]] = (int) ($r['c'] ?? 0);
                }
            }

            $st = $pdo->prepare(
                "SELECT DATE_FORMAT(COALESCE(paid_at, updated_at), '%Y-%m') AS ym, SUM(total) AS s FROM invoices
                 WHERE organization_id = :oid AND invoice_kind = 'invoice' AND status = 'paid'
                 AND COALESCE(paid_at, updated_at) >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL {$mc} MONTH)
                 GROUP BY ym ORDER BY ym"
            );
            $st->execute(['oid' => $organizationId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $ym = (string) ($r['ym'] ?? '');
                if (isset($idxMap[$ym])) {
                    $result['paid_totals'][$idxMap[$ym]] = (float) ($r['s'] ?? 0);
                }
            }
        } catch (\PDOException) {
        }

        return $result;
    }
}
