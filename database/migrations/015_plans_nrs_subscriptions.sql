-- Configurable plans, org subscriptions, NRS integration fields, subscription checkout orders

ALTER TABLE organizations
    ADD COLUMN nrs_enabled TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN nrs_api_base_url VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN nrs_bearer_token VARCHAR(500) NULL DEFAULT NULL,
    ADD COLUMN nrs_tenant_external_id VARCHAR(120) NULL DEFAULT NULL;

CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    price_amount DECIMAL(14, 2) NOT NULL DEFAULT 0.00,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    billing_interval ENUM('monthly', 'yearly', 'lifetime') NOT NULL DEFAULT 'monthly',
    max_invoices_per_month INT UNSIGNED NULL DEFAULT NULL,
    features_json JSON NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY subscription_plans_slug (slug),
    KEY subscription_plans_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO subscription_plans (slug, name, description, price_amount, currency, billing_interval, sort_order, is_active)
SELECT v.slug, v.name, v.description, v.price_amount, v.currency, v.billing_interval, v.sort_order, v.is_active
FROM (
    SELECT 'free' AS slug, 'Free' AS name, 'Core invoicing for small teams.' AS description,
           0.00 AS price_amount, 'NGN' AS currency, 'lifetime' AS billing_interval, 0 AS sort_order, 1 AS is_active
    UNION ALL SELECT 'starter', 'Starter', 'Growing businesses — higher limits.', 5000.00, 'NGN', 'monthly', 10, 1
    UNION ALL SELECT 'pro', 'Pro', 'Scale with priority support and exports.', 15000.00, 'NGN', 'monthly', 20, 1
) AS v
WHERE NOT EXISTS (SELECT 1 FROM subscription_plans LIMIT 1);

CREATE TABLE IF NOT EXISTS organization_subscriptions (
    organization_id INT UNSIGNED NOT NULL PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_org_sub_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_org_sub_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscription_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    amount DECIMAL(14, 2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'NGN',
    checkout_ref VARCHAR(190) NULL DEFAULT NULL,
    gateway_transaction_ref VARCHAR(255) NULL DEFAULT NULL,
    status ENUM('pending', 'paid', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY subscription_orders_org (organization_id, status),
    CONSTRAINT fk_sub_orders_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_orders_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_nrs_sync (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    invoice_id INT UNSIGNED NOT NULL,
    status ENUM('queued', 'sent', 'failed') NOT NULL DEFAULT 'queued',
    last_error VARCHAR(500) NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY invoice_nrs_sync_invoice (invoice_id),
    KEY invoice_nrs_sync_org (organization_id),
    CONSTRAINT fk_invoice_nrs_invoice FOREIGN KEY (invoice_id)
        REFERENCES invoices (id) ON DELETE CASCADE,
    CONSTRAINT fk_invoice_nrs_org FOREIGN KEY (organization_id)
        REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO organization_subscriptions (organization_id,plan_id,status)
SELECT o.id, p.id, 'active'
FROM organizations o
INNER JOIN subscription_plans p ON p.slug = 'free'
WHERE NOT EXISTS (SELECT 1 FROM organization_subscriptions s WHERE s.organization_id = o.id);
