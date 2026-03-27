-- Add clients (customers) table, scoped by organization.
-- Safe to run once on existing Billo databases.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    company_name VARCHAR(200) NULL DEFAULT NULL,
    email VARCHAR(255) NULL DEFAULT NULL,
    phone VARCHAR(40) NULL DEFAULT NULL,
    address_line1 VARCHAR(255) NULL DEFAULT NULL,
    address_line2 VARCHAR(255) NULL DEFAULT NULL,
    city VARCHAR(120) NULL DEFAULT NULL,
    state VARCHAR(120) NULL DEFAULT NULL,
    country CHAR(2) NOT NULL DEFAULT 'NG',
    tax_id VARCHAR(64) NULL DEFAULT NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY clients_organization_id (organization_id),
    CONSTRAINT fk_clients_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
