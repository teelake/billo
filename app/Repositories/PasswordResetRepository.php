<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PasswordResetRepository
{
    public function deleteForEmail(string $email): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM password_resets WHERE email = :email');
        $stmt->execute(['email' => strtolower(trim($email))]);
    }

    public function create(string $email, string $tokenHash, \DateTimeInterface $expiresAt): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO password_resets (email, token_hash, expires_at) VALUES (:email, :token_hash, :expires_at)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    /** @return array{email:string}|null */
    public function findValidEmailByTokenHash(string $tokenHash): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT email FROM password_resets
             WHERE token_hash = :h AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['h' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? ['email' => (string) $row['email']] : null;
    }
}
