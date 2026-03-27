<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class UserRepository
{
    public function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, password_hash, name, email_verified_at, active_organization_id, created_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, password_hash, name, email_verified_at, active_organization_id, created_at
             FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @return int new user id
     */
    public function create(string $email, string $passwordHash, string $name): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (email, password_hash, name) VALUES (:email, :password_hash, :name)'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
            'name' => $name,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'password_hash' => $passwordHash]);
    }

    public function setEmailVerified(int $userId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET email_verified_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
    }

    public function setActiveOrganization(int $userId, ?int $organizationId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET active_organization_id = :organization_id, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'id' => $userId,
            'organization_id' => $organizationId,
        ]);
    }
}
