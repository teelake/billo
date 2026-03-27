<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TaxConfigRepository
{
    /** @return list<array<string, mixed>> */
    public function listAll(): array
    {
        $st = Database::pdo()->query(
            'SELECT id, name, type, rate, is_active, sort_order, created_at, updated_at
             FROM tax_configs ORDER BY type ASC, sort_order ASC, id ASC'
        );
        if ($st === false) {
            return [];
        }
        /** @var list<array<string, mixed>> */
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return list<array<string, mixed>> */
    public function listActiveByType(string $type): array
    {
        if (!in_array($type, ['additive', 'deductive'], true)) {
            return [];
        }
        $st = Database::pdo()->prepare(
            'SELECT id, name, type, rate, is_active, sort_order
             FROM tax_configs WHERE type = :t AND is_active = 1
             ORDER BY sort_order ASC, id ASC'
        );
        $st->execute(['t' => $type]);
        /** @var list<array<string, mixed>> */
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id): ?array
    {
        $st = Database::pdo()->prepare('SELECT * FROM tax_configs WHERE id = :id LIMIT 1');
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findActiveDeductiveById(int $id): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT * FROM tax_configs WHERE id = :id AND type = \'deductive\' AND is_active = 1 LIMIT 1'
        );
        $st->execute(['id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Rate % for first active additive config, or 7.5 fallback. */
    public function defaultPlatformVatRatePercent(): float
    {
        $st = Database::pdo()->query(
            'SELECT rate FROM tax_configs WHERE type = \'additive\' AND is_active = 1 ORDER BY sort_order ASC, id ASC LIMIT 1'
        );
        if ($st === false) {
            return 7.5;
        }
        $r = $st->fetchColumn();
        if ($r === false) {
            return 7.5;
        }

        return max(0.0, (float) $r);
    }

    public function create(string $name, string $type, float $rate, bool $active = true): int
    {
        $st = Database::pdo()->prepare(
            'INSERT INTO tax_configs (name, type, rate, is_active, sort_order)
             VALUES (:name, :type, :rate, :active, 100)'
        );
        $st->execute([
            'name' => $name,
            'type' => $type === 'deductive' ? 'deductive' : 'additive',
            'rate' => round($rate, 4),
            'active' => $active ? 1 : 0,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, string $name, string $type, float $rate, bool $active): bool
    {
        $st = Database::pdo()->prepare(
            'UPDATE tax_configs SET name = :name, type = :type, rate = :rate, is_active = :active
             WHERE id = :id'
        );
        $st->execute([
            'id' => $id,
            'name' => $name,
            'type' => $type === 'deductive' ? 'deductive' : 'additive',
            'rate' => round($rate, 4),
            'active' => $active ? 1 : 0,
        ]);

        return $st->rowCount() > 0;
    }
}
