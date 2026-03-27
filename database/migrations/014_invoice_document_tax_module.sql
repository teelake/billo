-- Document-level VAT (additive) + WHT (deductive)

CREATE TABLE IF NOT EXISTS tax_configs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    type ENUM('additive', 'deductive') NOT NULL,
    rate DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY tax_configs_type_active (type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tax_configs (name, type, rate, is_active, sort_order)
SELECT v.name, v.type, v.rate, v.is_active, v.sort_order
FROM (
    SELECT 'VAT (standard)' AS name, 'additive' AS type, 7.5000 AS rate, 1 AS is_active, 0 AS sort_order
    UNION ALL SELECT 'WHT — Services', 'deductive', 5.0000, 1, 10
    UNION ALL SELECT 'WHT — Consultancy', 'deductive', 10.0000, 1, 20
    UNION ALL SELECT 'WHT — Contracts', 'deductive', 5.0000, 1, 30
) AS v
WHERE NOT EXISTS (SELECT 1 FROM tax_configs LIMIT 1);

CREATE TABLE IF NOT EXISTS organization_tax_settings (
    organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
    enable_vat TINYINT(1) NOT NULL DEFAULT 0,
    vat_rate DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    enable_wht TINYINT(1) NOT NULL DEFAULT 0,
    default_wht_id INT UNSIGNED NULL DEFAULT NULL,
    CONSTRAINT fk_org_tax_settings_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_org_tax_settings_wht FOREIGN KEY (default_wht_id)
        REFERENCES tax_configs (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO organization_tax_settings (organization_id, enable_vat, vat_rate, enable_wht, default_wht_id)
SELECT o.id, COALESCE(o.invoice_tax_enabled, 1), 0.0000, 0, NULL
FROM organizations o;

ALTER TABLE invoices
    ADD COLUMN tax_computation ENUM('line', 'document') NOT NULL DEFAULT 'line',
    ADD COLUMN apply_vat TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN vat_rate DECIMAL(10, 4) NOT NULL DEFAULT 0.0000,
    ADD COLUMN apply_wht TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN wht_id INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN vat_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    ADD COLUMN wht_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    ADD COLUMN net_payable DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    ADD KEY invoices_wht_id (wht_id),
    ADD CONSTRAINT fk_invoices_wht_config FOREIGN KEY (wht_id) REFERENCES tax_configs (id) ON DELETE SET NULL;

UPDATE invoices SET net_payable = total;
