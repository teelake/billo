<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class MemberRepository
{
    public function attach(int $organizationId, int $userId, string $role): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO organization_members (organization_id, user_id, role)
             VALUES (:organization_id, :user_id, :role)'
        );
        $stmt->execute([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'role' => $role,
        ]);
    }

    public function attachIfNotMember(int $organizationId, int $userId, string $role): void
    {
        if ($this->isMember($userId, $organizationId)) {
            return;
        }
        $this->attach($organizationId, $userId, $role);
    }

    public function findMembership(int $userId, int $organizationId): ?string
    {
        $stmt = Database::pdo()->prepare(
            'SELECT role FROM organization_members
             WHERE user_id = :user_id AND organization_id = :organization_id LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId, 'organization_id' => $organizationId]);
        $role = $stmt->fetchColumn();

        return $role !== false ? (string) $role : null;
    }

    public function isMember(int $userId, int $organizationId): bool
    {
        return $this->findMembership($userId, $organizationId) !== null;
    }

    /** @return array{organization_id:int,role:string}|null */
    public function firstMembershipForUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT organization_id, role FROM organization_members
             WHERE user_id = :user_id ORDER BY created_at ASC LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return [
            'organization_id' => (int) $row['organization_id'],
            'role' => (string) $row['role'],
        ];
    }

    /**
     * Prefer stored active organization when still valid; otherwise oldest membership.
     *
     * @return array{organization_id:int,role:string}|null
     */
    public function membershipForUserPreferringOrg(int $userId, ?int $preferredOrganizationId): ?array
    {
        if ($preferredOrganizationId !== null && $preferredOrganizationId > 0) {
            $role = $this->findMembership($userId, $preferredOrganizationId);
            if ($role !== null) {
                return [
                    'organization_id' => $preferredOrganizationId,
                    'role' => $role,
                ];
            }
        }

        return $this->firstMembershipForUser($userId);
    }

    /** @return list<array<string, mixed>> */
    public function listMembersForOrganization(int $organizationId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT u.id AS user_id, u.name, u.email, u.email_verified_at, m.role, m.created_at
             FROM organization_members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.organization_id = :organization_id
             ORDER BY FIELD(m.role, \'owner\', \'admin\', \'member\', \'viewer\'), u.name ASC'
        );
        $stmt->execute(['organization_id' => $organizationId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function isEmailMemberOfOrganization(int $organizationId, string $email): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM organization_members m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.organization_id = :organization_id AND u.email = :email LIMIT 1'
        );
        $stmt->execute([
            'organization_id' => $organizationId,
            'email' => strtolower(trim($email)),
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
