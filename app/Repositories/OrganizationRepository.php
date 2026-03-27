<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class OrganizationRepository
{
    /**
     * @return int new organization id
     */
    public function create(string $name, string $slug): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO organizations (name, slug) VALUES (:name, :slug)'
        );
        $stmt->execute(['name' => $name, 'slug' => $slug]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function slugExists(string $slug): bool
    {
        $stmt = Database::pdo()->prepare('SELECT 1 FROM organizations WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);

        return (bool) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM organizations WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{
     *   legal_name:?string,
     *   billing_address_line1:?string,
     *   billing_address_line2:?string,
     *   billing_city:?string,
     *   billing_state:?string,
     *   billing_country:string,
     *   tax_id:?string,
     *   invoice_footer:?string,
     *   invoice_logo_url:?string
     * } $data
     */
    public function updateBranding(int $organizationId, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE organizations SET
                legal_name = :legal_name,
                billing_address_line1 = :billing_address_line1,
                billing_address_line2 = :billing_address_line2,
                billing_city = :billing_city,
                billing_state = :billing_state,
                billing_country = :billing_country,
                tax_id = :tax_id,
                invoice_footer = :invoice_footer,
                invoice_logo_url = :invoice_logo_url,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $organizationId,
            'legal_name' => $data['legal_name'],
            'billing_address_line1' => $data['billing_address_line1'],
            'billing_address_line2' => $data['billing_address_line2'],
            'billing_city' => $data['billing_city'],
            'billing_state' => $data['billing_state'],
            'billing_country' => $data['billing_country'],
            'tax_id' => $data['tax_id'],
            'invoice_footer' => $data['invoice_footer'],
            'invoice_logo_url' => $data['invoice_logo_url'],
        ]);

        return true;
    }
}
