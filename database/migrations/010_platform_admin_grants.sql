-- Platform operator access: grant rows keyed by user_id (password stays on users only).
-- Legacy users.is_system_admin is backfilled then may be ignored by the app once this runs.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_admin_grants (
    user_id INT UNSIGNED NOT NULL,
    granted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    granted_by_user_id INT UNSIGNED NULL DEFAULT NULL,
    revoked_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    notes VARCHAR(500) NULL DEFAULT NULL,
    PRIMARY KEY (user_id),
    KEY platform_admin_grants_active_lookup (user_id, revoked_at),
    CONSTRAINT fk_platform_admin_grants_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_platform_admin_grants_granted_by FOREIGN KEY (granted_by_user_id)
        REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO platform_admin_grants (user_id, granted_at, granted_by_user_id)
SELECT id, created_at, NULL
FROM users
WHERE is_system_admin = 1
ON DUPLICATE KEY UPDATE revoked_at = NULL;
