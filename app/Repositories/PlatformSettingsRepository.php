<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class PlatformSettingsRepository
{
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
