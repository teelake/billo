-- Invoices and line items (per organization). Run once.

SET NAMES utf8mb4;

ALTER TABLE organizations
    ADD COLUMN invoice_number_prefix VARCHAR(20) NOT NULL DEFAULT 'INV' AFTER slug,
    ADD COLUMN invoice_next_number INT UNSIGNED NOT NULL DEFAULT 1 AFTER invoice_number_prefix;

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL,
    invoice_number VARCHAR(48) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'void') NOT NULL DEFAULT 'draft',
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    notes TEXT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY invoices_org_number (organization_id, invoice_number),
    KEY invoices_org_status (organization_id, status),
    KEY invoices_client (client_id),
    CONSTRAINT fk_invoices_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id)
        REFERENCES clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_line_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    line_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(12,4) NOT NULL DEFAULT 1.0000,
    unit_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_rate DECIMAL(6,3) NOT NULL DEFAULT 0.000,
    line_subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_tax DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    KEY invoice_line_invoice (invoice_id, line_order),
    CONSTRAINT fk_invoice_line_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
