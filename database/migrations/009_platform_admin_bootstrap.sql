-- Optional: one-shot platform operator with a known initial password (CHANGE AFTER LOGIN).
-- Default login: email below + password  admin123!
-- PHP PASSWORD_DEFAULT bcrypt hash of admin123! — replace email before running if needed.

INSERT IGNORE INTO organizations (name, slug) VALUES ('Billo (platform)', 'billo-platform');

SET @billo_platform_org := (SELECT id FROM organizations WHERE slug = 'billo-platform' LIMIT 1);

INSERT INTO users (email, password_hash, name, is_system_admin, email_verified_at, active_organization_id)
VALUES (
    'platform-admin@billo.local',
    '$2y$10$zmhchrZtMjhZZV5q/BwCoexTSM1Bxo2VhXOyQmiyV5XrzochefaxS',
    'Platform operator',
    1,
    NOW(),
    @billo_platform_org
)
ON DUPLICATE KEY UPDATE
    password_hash = VALUES(password_hash),
    is_system_admin = 1,
    email_verified_at = COALESCE(email_verified_at, NOW()),
    active_organization_id = VALUES(active_organization_id),
    updated_at = CURRENT_TIMESTAMP;

INSERT IGNORE INTO organization_members (organization_id, user_id, role)
SELECT @billo_platform_org, id, 'owner'
FROM users
WHERE email = 'platform-admin@billo.local'
LIMIT 1;
