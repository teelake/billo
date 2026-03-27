<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class EmailVerificationRepository
{
    public function deleteForUser(int $userId): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM email_verification_tokens WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function upsert(int $userId, string $tokenHash, \DateTimeInterface $expiresAt): void
    {
        $this->deleteForUser($userId);
        $stmt = Database::pdo()->prepare(
            'INSERT INTO email_verification_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{user_id:int}|null */
    public function findValidUserIdByTokenHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT user_id FROM email_verification_tokens
             WHERE token_hash = :h AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute(['h' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ['user_id' => (int) $row['user_id']] : null;
    }
}
