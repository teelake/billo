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
            'SELECT id, name, slug, created_at FROM organizations WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
