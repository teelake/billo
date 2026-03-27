<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;
use PDOException;

/** Active rows in platform_admin_grants grant /system and related platform operator UI. */
final class PlatformAdminGrantRepository
{
    public function userHasActiveGrant(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $st = Database::pdo()->prepare(
                'SELECT 1 FROM platform_admin_grants
                 WHERE user_id = :uid AND revoked_at IS NULL
                 AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
                 LIMIT 1'
            );
            $st->execute(['uid' => $userId]);

            return (bool) $st->fetchColumn();
        } catch (PDOException) {
            return false;
        }
    }

    public function countActiveGrants(): int
    {
        try {
            $st = Database::pdo()->query(
                'SELECT COUNT(*) FROM platform_admin_grants
                 WHERE revoked_at IS NULL
                 AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)'
            );

            return (int) $st->fetchColumn();
        } catch (PDOException) {
            return 0;
        }
    }

    /**
     * @return list<array{user_id:int,email:string,name:string,granted_at:string,expires_at:?string,granted_by_user_id:?int}>
     */
    public function listActiveWithUsers(): array
    {
        try {
            $st = Database::pdo()->query(
                'SELECT g.user_id, u.email, u.name, g.granted_at, g.expires_at, g.granted_by_user_id
                 FROM platform_admin_grants g
                 INNER JOIN users u ON u.id = g.user_id
                 WHERE g.revoked_at IS NULL
                 AND (g.expires_at IS NULL OR g.expires_at > CURRENT_TIMESTAMP)
                 ORDER BY g.granted_at ASC'
            );
            $out = [];
            while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
                $exp = $row['expires_at'] ?? null;
                $out[] = [
                    'user_id' => (int) $row['user_id'],
                    'email' => (string) $row['email'],
                    'name' => (string) $row['name'],
                    'granted_at' => (string) $row['granted_at'],
                    'expires_at' => $exp !== null && $exp !== '' ? (string) $exp : null,
                    'granted_by_user_id' => isset($row['granted_by_user_id']) && $row['granted_by_user_id'] !== null
                        ? (int) $row['granted_by_user_id'] : null,
                ];
            }

            return $out;
        } catch (PDOException) {
            return [];
        }
    }

    /**
     * Create or reactivate a platform operator grant (idempotent).
     *
     * @param ?string $expiresAt MySQL datetime 'Y-m-d H:i:s' or null for no expiry
     */
    public function ensureGrant(
        int $userId,
        ?int $grantedByUserId = null,
        ?string $notes = null,
        ?string $expiresAt = null,
    ): void {
        if ($userId <= 0) {
            return;
        }

        $pdo = Database::pdo();
        $st = $pdo->prepare(
            'INSERT INTO platform_admin_grants (user_id, granted_by_user_id, notes, revoked_at, expires_at)
             VALUES (:uid, :by, :notes, NULL, :exp)
             ON DUPLICATE KEY UPDATE
                revoked_at = NULL,
                granted_at = CURRENT_TIMESTAMP,
                granted_by_user_id = VALUES(granted_by_user_id),
                notes = COALESCE(VALUES(notes), notes),
                expires_at = VALUES(expires_at)'
        );
        $st->execute([
            'uid' => $userId,
            'by' => $grantedByUserId,
            'notes' => $notes,
            'exp' => $expiresAt,
        ]);
    }

    public function revoke(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $st = Database::pdo()->prepare(
            'UPDATE platform_admin_grants SET revoked_at = CURRENT_TIMESTAMP
             WHERE user_id = :uid AND revoked_at IS NULL'
        );
        $st->execute(['uid' => $userId]);
    }
}
