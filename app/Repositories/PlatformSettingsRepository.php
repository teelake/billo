<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlatformSettingsRepository
{
    /**
     * @return list<string>
     */
    public function allKeys(): array
    {
        try {
            $stmt = Database::pdo()->query('SELECT setting_key FROM platform_settings');

            /** @var list<array{setting_key:string}> $rows */
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_values(array_filter(array_map(
                static fn (array $r): string => (string) ($r['setting_key'] ?? ''),
                $rows
            )));
        } catch (\PDOException) {
            return [];
        }
    }

    public function upsert(string $key, ?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $del = Database::pdo()->prepare('DELETE FROM platform_settings WHERE setting_key = :k');
            $del->execute(['k' => $key]);

            return;
        }

        $stmt = Database::pdo()->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute(['k' => $key, 'v' => $value]);
    }
}
