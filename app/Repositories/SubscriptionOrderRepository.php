<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class SubscriptionOrderRepository
{
    public function createPending(int $organizationId, int $planId, float $amount, string $currency): int
    {
        $st = Database::pdo()->prepare(
            'INSERT INTO subscription_orders (organization_id, plan_id, amount, currency, status)
             VALUES (:oid, :pid, :amt, :cur, \'pending\')'
        );
        $st->execute([
            'oid' => $organizationId,
            'pid' => $planId,
            'amt' => round($amount, 2),
            'cur' => strtoupper(substr($currency, 0, 3)),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function setCheckoutRef(int $orderId, int $organizationId, string $ref): bool
    {
        $st = Database::pdo()->prepare(
            'UPDATE subscription_orders SET checkout_ref = :ref, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :oid AND status = \'pending\''
        );
        $st->execute(['ref' => $ref, 'id' => $orderId, 'oid' => $organizationId]);

        return $st->rowCount() > 0;
    }

    public function findPendingByCheckoutRef(string $ref): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM subscription_orders WHERE checkout_ref = :ref AND status = \'pending\' LIMIT 1'
        );
        $st->execute(['ref' => $ref]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markPaid(int $orderId, int $organizationId, string $txRef): bool
    {
        $st = Database::pdo()->prepare(
            'UPDATE subscription_orders SET status = \'paid\', gateway_transaction_ref = :tx, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :oid'
        );
        $st->execute(['id' => $orderId, 'oid' => $organizationId, 'tx' => $txRef]);

        return $st->rowCount() > 0;
    }
}
