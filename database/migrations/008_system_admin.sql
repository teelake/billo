-- Cross-tenant operators: users.is_system_admin (access /system after normal login)

SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN is_system_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER email_verified_at;

-- Promote your account (replace email):
-- UPDATE users SET is_system_admin = 1 WHERE email = 'you@example.com' LIMIT 1;
