<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlanRepository
{
    /** @return list<array<string, mixed>> */
    public function listActiveForDisplay(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
            );
        } catch (\Throwable) {
            return [];
        }
        if ($st === false) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC'
            );
        } catch (\Throwable) {
            return [];
        }
        if ($st === false) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        try {
            $st = Database::pdo()->prepare('SELECT * FROM subscription_plans WHERE id = :id LIMIT 1');
            $st->execute(['id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return $row ?: null;
    }

    public function findActiveById(int $id): ?array
    {
        try {
            $st = Database::pdo()->prepare(
                'SELECT * FROM subscription_plans WHERE id = :id AND is_active = 1 LIMIT 1'
            );
            $st->execute(['id' => $id]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return null;
        }

        return $row ?: null;
    }

    public function create(string $slug, string $name, ?string $description, float $price, string $currency, string $interval, int $sort, bool $active): int
    {
        $st = Database::pdo()->prepare(
            'INSERT INTO subscription_plans (slug, name, description, price_amount, currency, billing_interval, sort_order, is_active)
             VALUES (:slug, :name, :desc, :price, :cur, :intv, :sort, :act)'
        );
        $st->execute([
            'slug' => $slug,
            'name' => $name,
            'desc' => $description,
            'price' => round($price, 2),
            'cur' => strtoupper(substr($currency, 0, 3)),
            'intv' => in_array($interval, ['monthly', 'yearly', 'lifetime'], true) ? $interval : 'monthly',
            'sort' => $sort,
            'act' => $active ? 1 : 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, string $slug, string $name, ?string $description, float $price, string $currency, string $interval, int $sort, bool $active): bool
    {
        $st = Database::pdo()->prepare(
            'UPDATE subscription_plans SET slug = :slug, name = :name, description = :desc,
             price_amount = :price, currency = :cur, billing_interval = :intv, sort_order = :sort, is_active = :act
             WHERE id = :id'
        );
        $st->execute([
            'id' => $id,
            'slug' => $slug,
            'name' => $name,
            'desc' => $description,
            'price' => round($price, 2),
            'cur' => strtoupper(substr($currency, 0, 3)),
            'intv' => in_array($interval, ['monthly', 'yearly', 'lifetime'], true) ? $interval : 'monthly',
            'sort' => $sort,
            'act' => $active ? 1 : 0,
        ]);

        return $st->rowCount() > 0;
    }
}
