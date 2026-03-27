-- Billo — core schema (auth, tenants, verification, invites)
-- MySQL 5.7+ / 8.0+

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    legal_name VARCHAR(200) NULL DEFAULT NULL,
    billing_address_line1 VARCHAR(255) NULL DEFAULT NULL,
    billing_address_line2 VARCHAR(255) NULL DEFAULT NULL,
    billing_city VARCHAR(120) NULL DEFAULT NULL,
    billing_state VARCHAR(120) NULL DEFAULT NULL,
    billing_country CHAR(2) NOT NULL DEFAULT 'NG',
    tax_id VARCHAR(64) NULL DEFAULT NULL,
    tax_id_normalized VARCHAR(64) NULL DEFAULT NULL,
    company_registration_number VARCHAR(40) NULL DEFAULT NULL,
    company_registration_normalized VARCHAR(40) NULL DEFAULT NULL,
    company_website VARCHAR(255) NULL DEFAULT NULL,
    company_website_host VARCHAR(190) NULL DEFAULT NULL,
    invoice_footer TEXT NULL,
    invoice_logo_url VARCHAR(500) NULL DEFAULT NULL,
    invoice_tax_enabled TINYINT(1) NOT NULL DEFAULT 1,
    invoice_style VARCHAR(20) NOT NULL DEFAULT 'modern',
    invoice_brand_primary CHAR(7) NOT NULL DEFAULT '#1E3A8A',
    invoice_brand_accent CHAR(7) NOT NULL DEFAULT '#16A34A',
    slug VARCHAR(96) NOT NULL,
    invoice_number_prefix VARCHAR(20) NOT NULL DEFAULT 'INV',
    invoice_next_number INT UNSIGNED NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY organizations_slug_unique (slug),
    UNIQUE KEY organizations_country_tin_norm_unique (billing_country, tax_id_normalized),
    UNIQUE KEY organizations_country_reg_norm_unique (billing_country, company_registration_normalized),
    UNIQUE KEY organizations_website_host_unique (company_website_host)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(120) NOT NULL,
    email_verified_at TIMESTAMP NULL DEFAULT NULL,
    is_system_admin TINYINT(1) NOT NULL DEFAULT 0,
    active_organization_id INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY users_email_unique (email),
    KEY users_active_org (active_organization_id),
    CONSTRAINT fk_users_active_org FOREIGN KEY (active_organization_id)
        REFERENCES organizations (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS organization_members (
    organization_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('owner', 'admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (organization_id, user_id),
    KEY organization_members_user_id (user_id),
    CONSTRAINT fk_members_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_members_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY password_resets_email (email),
    KEY password_resets_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY email_verification_user (user_id),
    KEY email_verification_expires (expires_at),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invitations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    role ENUM('admin', 'member', 'viewer') NOT NULL DEFAULT 'member',
    token_hash CHAR(64) NOT NULL,
    invited_by_user_id INT UNSIGNED NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY invitations_org (organization_id),
    KEY invitations_email (email),
    KEY invitations_expires (expires_at),
    CONSTRAINT fk_invitations_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_invitations_inviter FOREIGN KEY (invited_by_user_id)
        REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NULL,
    invoice_number VARCHAR(48) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'void') NOT NULL DEFAULT 'draft',
    invoice_kind ENUM('invoice', 'credit_note') NOT NULL DEFAULT 'invoice',
    credited_invoice_id INT UNSIGNED NULL DEFAULT NULL,
    issue_date DATE NOT NULL,
    due_date DATE NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    notes TEXT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    tax_total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(14,2) NOT NULL DEFAULT 0.00,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    payment_provider VARCHAR(32) NULL DEFAULT NULL,
    gateway_checkout_ref VARCHAR(255) NULL DEFAULT NULL,
    gateway_transaction_ref VARCHAR(255) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY invoices_org_number (organization_id, invoice_number),
    KEY invoices_org_status (organization_id, status),
    KEY invoices_client (client_id),
    KEY invoices_credited (credited_invoice_id),
    CONSTRAINT fk_invoices_organization FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoices_client FOREIGN KEY (client_id)
        REFERENCES clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_invoices_credited_invoice FOREIGN KEY (credited_invoice_id)
        REFERENCES invoices (id) ON DELETE SET NULL
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

CREATE TABLE IF NOT EXISTS platform_settings (
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
