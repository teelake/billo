<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrganizationTaxRepository
{
    public function ensureDefaults(int $organizationId): void
    {
        $st = Database::pdo()->prepare(
            'INSERT IGNORE INTO organization_tax_settings (organization_id, enable_vat, vat_rate, enable_wht, default_wht_id)
             VALUES (:oid, 1, 0, 0, NULL)'
        );
        $st->execute(['oid' => $organizationId]);
    }

    public function findByOrganization(int $organizationId): ?array
    {
        $st = Database::pdo()->prepare(
            'SELECT organization_id, enable_vat, vat_rate, enable_wht, default_wht_id
             FROM organization_tax_settings WHERE organization_id = :id LIMIT 1'
        );
        $st->execute(['id' => $organizationId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{
     *   enable_vat:int|bool,
     *   vat_rate:float|string,
     *   enable_wht:int|bool,
     *   default_wht_id:?int
     * } $data
     */
    public function upsert(int $organizationId, array $data): bool
    {
        $st = Database::pdo()->prepare(
            'INSERT INTO organization_tax_settings (organization_id, enable_vat, vat_rate, enable_wht, default_wht_id)
             VALUES (:oid, :ev, :vr, :ew, :dw)
             ON DUPLICATE KEY UPDATE
                enable_vat = VALUES(enable_vat),
                vat_rate = VALUES(vat_rate),
                enable_wht = VALUES(enable_wht),
                default_wht_id = VALUES(default_wht_id)'
        );
        $st->execute([
            'oid' => $organizationId,
            'ev' => !empty($data['enable_vat']) ? 1 : 0,
            'vr' => round((float) ($data['vat_rate'] ?? 0), 4),
            'ew' => !empty($data['enable_wht']) ? 1 : 0,
            'dw' => isset($data['default_wht_id']) && $data['default_wht_id'] > 0 ? (int) $data['default_wht_id'] : null,
        ]);

        return true;
    }
}
