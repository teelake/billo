<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlanItemRepository
{
    /** @return list<array<string, mixed>> */
    public function listForPlan(int $planId): array
    {
        if ($planId <= 0) {
            return [];
        }
        try {
            $st = Database::pdo()->prepare(
                'SELECT * FROM subscription_plan_items WHERE plan_id = :pid ORDER BY sort_order ASC, id ASC'
            );
            $st->execute(['pid' => $planId]);
        } catch (\Throwable) {
            return [];
        }

        /** @var list<array<string, mixed>> */
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param list<int> $planIds
     * @return array<int, list<array<string, mixed>>>
     */
    public function listGroupedForPlans(array $planIds): array
    {
        $planIds = array_values(array_filter(array_map('intval', $planIds), static fn (int $i) => $i > 0));
        if ($planIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        try {
            $st = Database::pdo()->prepare(
                "SELECT * FROM subscription_plan_items WHERE plan_id IN ({$placeholders}) ORDER BY plan_id ASC, sort_order ASC, id ASC"
            );
            $st->execute($planIds);
            /** @var list<array<string, mixed>> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        foreach ($planIds as $pid) {
            $out[$pid] = [];
        }
        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $pid = (int) ($r['plan_id'] ?? 0);
            if ($pid > 0) {
                $out[$pid][] = $r;
            }
        }

        return $out;
    }

    public function create(int $planId, string $label, ?string $detail, int $sortOrder): int
    {
        $label = trim($label);
        if ($label === '' || $planId <= 0) {
            return 0;
        }
        $st = Database::pdo()->prepare(
            'INSERT INTO subscription_plan_items (plan_id, sort_order, label, detail)
             VALUES (:pid, :sort, :lab, :det)'
        );
        $st->execute([
            'pid' => $planId,
            'sort' => max(0, $sortOrder),
            'lab' => function_exists('mb_substr') ? mb_substr($label, 0, 200, 'UTF-8') : substr($label, 0, 200),
            'det' => $detail === null || trim($detail) === ''
                ? null
                : (function_exists('mb_substr') ? mb_substr(trim($detail), 0, 2000, 'UTF-8') : substr(trim($detail), 0, 2000)),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, string $label, ?string $detail, int $sortOrder): bool
    {
        if ($id <= 0) {
            return false;
        }
        $label = trim($label);
        if ($label === '') {
            return false;
        }
        $st = Database::pdo()->prepare(
            'UPDATE subscription_plan_items SET label = :lab, detail = :det, sort_order = :sort WHERE id = :id'
        );
        $st->execute([
            'id' => $id,
            'lab' => function_exists('mb_substr') ? mb_substr($label, 0, 200, 'UTF-8') : substr($label, 0, 200),
            'det' => $detail === null || trim($detail) === ''
                ? null
                : (function_exists('mb_substr') ? mb_substr(trim($detail), 0, 2000, 'UTF-8') : substr(trim($detail), 0, 2000)),
            'sort' => max(0, $sortOrder),
        ]);

        return $st->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }
        $st = Database::pdo()->prepare('DELETE FROM subscription_plan_items WHERE id = :id');

        return $st->execute(['id' => $id]) && $st->rowCount() > 0;
    }

    public function findById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $st = Database::pdo()->prepare('SELECT * FROM subscription_plan_items WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
