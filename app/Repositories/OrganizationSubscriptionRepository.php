<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrganizationSubscriptionRepository
{
    public function ensureFreePlan(int $organizationId): void
    {
        try {
            $st = Database::pdo()->prepare(
                'INSERT IGNORE INTO organization_subscriptions (organization_id, plan_id, status)
                 SELECT :oid, p.id, \'active\' FROM subscription_plans p WHERE p.slug = \'free\' LIMIT 1'
            );
            $st->execute(['oid' => $organizationId]);
        } catch (\Throwable) {
            // Table missing until migration
        }
    }

    /** @return array<string, mixed>|null subscription row joined plan */
    public function findWithPlan(int $organizationId): ?array
    {
        try {
            $st = Database::pdo()->prepare(
                'SELECT s.*, p.slug AS plan_slug, p.name AS plan_name, p.description AS plan_description,
                        p.price_amount, p.currency AS plan_currency, p.billing_interval, p.max_invoices_per_month,
                        p.nrs_integration_allowed, p.nrs_requires_organization_tax_id
                 FROM organization_subscriptions s
                 INNER JOIN subscription_plans p ON p.id = s.plan_id
                 WHERE s.organization_id = :id LIMIT 1'
            );
            $st->execute(['id' => $organizationId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return $row ?: null;
    }

    public function setPlan(int $organizationId, int $planId, string $status = 'active'): void
    {
        $st = Database::pdo()->prepare(
            'INSERT INTO organization_subscriptions (organization_id, plan_id, status)
             VALUES (:oid, :pid, :st)
             ON DUPLICATE KEY UPDATE plan_id = VALUES(plan_id), status = VALUES(status), updated_at = CURRENT_TIMESTAMP'
        );
        $st->execute([
            'oid' => $organizationId,
            'pid' => $planId,
            'st' => $status === 'cancelled' ? 'cancelled' : 'active',
        ]);
    }
}
