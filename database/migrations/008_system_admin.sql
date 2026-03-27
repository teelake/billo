-- Legacy flag on users; the app uses platform_admin_grants (see 010_platform_admin_grants.sql).

SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN is_system_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at;

-- After 010 exists, prefer grants:
-- INSERT INTO platform_admin_grants (user_id) SELECT id FROM users WHERE email = 'you@example.com' LIMIT 1
--     ON DUPLICATE KEY UPDATE revoked_at = NULL;
