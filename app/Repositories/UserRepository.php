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
            'SELECT id, email, password_hash, google_sub, name, email_verified_at, is_system_admin, active_organization_id, created_at
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, password_hash, google_sub, name, email_verified_at, is_system_admin, active_organization_id, created_at
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

    public function findByGoogleSub(string $googleSub): ?array
    {
        $sub = trim($googleSub);
        if ($sub === '') {
            return null;
        }
        $stmt = Database::pdo()->prepare(
            'SELECT id, email, password_hash, google_sub, name, email_verified_at, is_system_admin, active_organization_id, created_at
             FROM users WHERE google_sub = :sub LIMIT 1'
        );
        $stmt->execute(['sub' => $sub]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * OAuth-only account (password_hash NULL). Email pre-verified via Google.
     *
     * @return int new user id
     */
    public function createOAuthGoogle(string $email, string $name, string $googleSub): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (email, password_hash, google_sub, name, email_verified_at)
             VALUES (:email, NULL, :google_sub, :name, NOW())'
        );
        $stmt->execute([
            'email' => strtolower(trim($email)),
            'google_sub' => trim($googleSub),
            'name' => $name,
        ]);

        return (int) Database::pdo()->lastInsertId();
    }

    public function linkGoogleSub(int $userId, string $googleSub): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET google_sub = :sub, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'sub' => trim($googleSub)]);
    }

    public function hasPasswordHash(int $userId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT password_hash FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $userId]);
        $h = $stmt->fetchColumn();
        if ($h === false || $h === null) {
            return false;
        }
        $s = trim((string) $h);

        return $s !== '';
    }

    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'password_hash' => $passwordHash]);
    }

    public function updateName(int $userId, string $name): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET name = :name, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'name' => $name]);
    }

    /** Lowercase email; clears verification when email changes. */
    public function updateEmail(int $userId, string $email): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET email = :email, email_verified_at = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute(['id' => $userId, 'email' => strtolower(trim($email))]);
    }

    public function emailExistsForOtherUser(string $email, int $excludeUserId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM users WHERE email = :email AND id <> :exclude LIMIT 1'
        );
        $stmt->execute(['email' => strtolower(trim($email)), 'exclude' => $excludeUserId]);

        return (bool) $stmt->fetchColumn();
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

    public function countAll(): int
    {
        $n = Database::pdo()->query('SELECT COUNT(*) FROM users')->fetchColumn();
        if ($n === false) {
            return 0;
        }

        return (int) $n;
    }
}
