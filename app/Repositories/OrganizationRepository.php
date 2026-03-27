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

    public function findDuplicateTaxIdentity(string $billingCountry, string $taxNormalized, int $excludeOrganizationId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM organizations
             WHERE billing_country = :country AND tax_id_normalized = :tin AND id <> :exclude
             LIMIT 1'
        );
        $stmt->execute([
            'country' => $billingCountry,
            'tin' => $taxNormalized,
            'exclude' => $excludeOrganizationId,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function findDuplicateRegistration(string $billingCountry, string $registrationNormalized, int $excludeOrganizationId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM organizations
             WHERE billing_country = :country AND company_registration_normalized = :reg AND id <> :exclude
             LIMIT 1'
        );
        $stmt->execute([
            'country' => $billingCountry,
            'reg' => $registrationNormalized,
            'exclude' => $excludeOrganizationId,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function findDuplicateWebsiteHost(string $websiteHost, int $excludeOrganizationId): ?int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM organizations
             WHERE company_website_host = :host AND id <> :exclude
             LIMIT 1'
        );
        $stmt->execute([
            'host' => $websiteHost,
            'exclude' => $excludeOrganizationId,
        ]);
        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
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
     *   tax_id_normalized:?string,
     *   company_registration_number:?string,
     *   company_registration_normalized:?string,
     *   company_website:?string,
     *   company_website_host:?string,
     *   invoice_footer:?string,
     *   invoice_logo_url:?string,
     *   invoice_tax_enabled:int,
     *   invoice_style:string,
     *   invoice_brand_primary:string,
     *   invoice_brand_accent:string
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
                tax_id_normalized = :tax_id_normalized,
                company_registration_number = :company_registration_number,
                company_registration_normalized = :company_registration_normalized,
                company_website = :company_website,
                company_website_host = :company_website_host,
                invoice_footer = :invoice_footer,
                invoice_logo_url = :invoice_logo_url,
                invoice_tax_enabled = :invoice_tax_enabled,
                invoice_style = :invoice_style,
                invoice_brand_primary = :invoice_brand_primary,
                invoice_brand_accent = :invoice_brand_accent,
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
            'tax_id_normalized' => $data['tax_id_normalized'],
            'company_registration_number' => $data['company_registration_number'],
            'company_registration_normalized' => $data['company_registration_normalized'],
            'company_website' => $data['company_website'],
            'company_website_host' => $data['company_website_host'],
            'invoice_footer' => $data['invoice_footer'],
            'invoice_logo_url' => $data['invoice_logo_url'],
            'invoice_tax_enabled' => (int) $data['invoice_tax_enabled'],
            'invoice_style' => $data['invoice_style'],
            'invoice_brand_primary' => $data['invoice_brand_primary'],
            'invoice_brand_accent' => $data['invoice_brand_accent'],
        ]);

        return true;
    }

    public function countAll(): int
    {
        $n = Database::pdo()->query('SELECT COUNT(*) FROM organizations')->fetchColumn();
        if ($n === false) {
            return 0;
        }

        return (int) $n;
    }
}
