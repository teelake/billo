-- Invoice letterhead / billing identity (per organization). Run once.

SET NAMES utf8mb4;

ALTER TABLE organizations
    ADD COLUMN legal_name VARCHAR(200) NULL DEFAULT NULL AFTER name,
    ADD COLUMN billing_address_line1 VARCHAR(255) NULL DEFAULT NULL AFTER legal_name,
    ADD COLUMN billing_address_line2 VARCHAR(255) NULL DEFAULT NULL AFTER billing_address_line1,
    ADD COLUMN billing_city VARCHAR(120) NULL DEFAULT NULL AFTER billing_address_line2,
    ADD COLUMN billing_state VARCHAR(120) NULL DEFAULT NULL AFTER billing_city,
    ADD COLUMN billing_country CHAR(2) NOT NULL DEFAULT 'NG' AFTER billing_state,
    ADD COLUMN tax_id VARCHAR(64) NULL DEFAULT NULL AFTER billing_country,
    ADD COLUMN invoice_footer TEXT NULL DEFAULT NULL AFTER tax_id,
    ADD COLUMN invoice_logo_url VARCHAR(500) NULL DEFAULT NULL AFTER invoice_footer;
