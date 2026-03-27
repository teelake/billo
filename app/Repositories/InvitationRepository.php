<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class InvitationRepository
{
    public function deletePendingForOrgEmail(int $organizationId, string $email): void
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM invitations
             WHERE organization_id = :organization_id AND email = :email AND accepted_at IS NULL'
        );
        $stmt->execute([
            'organization_id' => $organizationId,
            'email' => strtolower(trim($email)),
        ]);
    }

    /**
     * @return int invitation id
     */
    public function create(
        int $organizationId,
        string $email,
        string $role,
        string $tokenHash,
        int $invitedByUserId,
        \DateTimeInterface $expiresAt,
    ): int {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO invitations (
                organization_id, email, role, token_hash, invited_by_user_id, expires_at
            ) VALUES (
                :organization_id, :email, :role, :token_hash, :invited_by_user_id, :expires_at
            )'
        );
        $stmt->execute([
            'organization_id' => $organizationId,
            'email' => strtolower(trim($email)),
            'role' => $role,
            'token_hash' => $tokenHash,
            'invited_by_user_id' => $invitedByUserId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findValidByTokenHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.organization_id, i.email, i.role, i.invited_by_user_id,
                    o.name AS organization_name
             FROM invitations i
             INNER JOIN organizations o ON o.id = i.organization_id
             WHERE i.token_hash = :h AND i.accepted_at IS NULL AND i.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['h' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findPendingById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.organization_id, i.email, i.role, i.invited_by_user_id,
                    o.name AS organization_name
             FROM invitations i
             INNER JOIN organizations o ON o.id = i.organization_id
             WHERE i.id = :id AND i.accepted_at IS NULL AND i.expires_at > NOW()
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findByIdForOrg(int $id, int $organizationId): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, organization_id, email, role, accepted_at, expires_at
             FROM invitations WHERE id = :id AND organization_id = :organization_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'organization_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function markAccepted(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE invitations SET accepted_at = NOW() WHERE id = :id AND accepted_at IS NULL'
        );
        $stmt->execute(['id' => $id]);
    }

    /** @return list<array<string, mixed>> */
    public function listPendingForOrg(int $organizationId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT id, email, role, expires_at, created_at,
                    (SELECT name FROM users WHERE id = invitations.invited_by_user_id) AS invited_by_name
             FROM invitations
             WHERE organization_id = :organization_id AND accepted_at IS NULL AND expires_at > NOW()
             ORDER BY created_at DESC"
        );
        $stmt->execute(['organization_id' => $organizationId]);
        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function revoke(int $id, int $organizationId): bool
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM invitations
             WHERE id = :id AND organization_id = :organization_id AND accepted_at IS NULL'
        );
        $stmt->execute(['id' => $id, 'organization_id' => $organizationId]);

        return $stmt->rowCount() > 0;
    }
}
