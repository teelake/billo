-- Platform-wide SaaS settings (override config after load). Run once.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Set your public URL / brand (example — edit values, then run in MySQL):
-- INSERT INTO platform_settings (setting_key, setting_value) VALUES
--     ('app.public_url', 'https://www.webspace.ng'),
--     ('app.base_path', '/billo'),
--     ('brand.name', 'billo'),
--     ('brand.tagline', 'FIRS-ready invoicing for Nigeria')
-- ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
