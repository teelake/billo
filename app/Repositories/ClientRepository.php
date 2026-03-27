<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class ClientRepository
{
    /** @return list<array<string, mixed>> */
    public function listForOrganization(int $organizationId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, name, company_name, email, phone, city, state, country, created_at
             FROM clients
             WHERE organization_id = :organization_id
             ORDER BY name ASC, id ASC'
        );
        $stmt->execute(['organization_id' => $organizationId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function findForOrganization(int $clientId, int $organizationId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, organization_id, name, company_name, email, phone,
                    address_line1, address_line2, city, state, country, tax_id, notes,
                    created_at, updated_at
             FROM clients
             WHERE id = :id AND organization_id = :organization_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $clientId, 'organization_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{name:string,company_name:?string,email:?string,phone:?string,address_line1:?string,address_line2:?string,city:?string,state:?string,country:string,tax_id:?string,notes:?string} $data
     */
    public function create(int $organizationId, array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO clients (
                organization_id, name, company_name, email, phone,
                address_line1, address_line2, city, state, country, tax_id, notes
            ) VALUES (
                :organization_id, :name, :company_name, :email, :phone,
                :address_line1, :address_line2, :city, :state, :country, :tax_id, :notes
            )'
        );
        $stmt->execute([
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'],
            'tax_id' => $data['tax_id'],
            'notes' => $data['notes'],
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * @param array{name:string,company_name:?string,email:?string,phone:?string,address_line1:?string,address_line2:?string,city:?string,state:?string,country:string,tax_id:?string,notes:?string} $data
     */
    public function update(int $clientId, int $organizationId, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE clients SET
                name = :name,
                company_name = :company_name,
                email = :email,
                phone = :phone,
                address_line1 = :address_line1,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                country = :country,
                tax_id = :tax_id,
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND organization_id = :organization_id'
        );
        $stmt->execute([
            'id' => $clientId,
            'organization_id' => $organizationId,
            'name' => $data['name'],
            'company_name' => $data['company_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'],
            'city' => $data['city'],
            'state' => $data['state'],
            'country' => $data['country'],
            'tax_id' => $data['tax_id'],
            'notes' => $data['notes'],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $clientId, int $organizationId): bool
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM clients WHERE id = :id AND organization_id = :organization_id'
        );
        $stmt->execute(['id' => $clientId, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }
}
