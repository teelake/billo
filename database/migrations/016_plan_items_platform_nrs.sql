-- Plan marketing items, plan-level NRS entitlement, platform NRS API (org columns for URL/token removed).

SET NAMES utf8mb4;

ALTER TABLE subscription_plans
    ADD COLUMN nrs_integration_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER is_active,
    ADD COLUMN nrs_requires_organization_tax_id TINYINT(1) NOT NULL DEFAULT 0 AFTER nrs_integration_allowed;

CREATE TABLE IF NOT EXISTS subscription_plan_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_id INT UNSIGNED NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    label VARCHAR(200) NOT NULL,
    detail TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY subscription_plan_items_plan_sort (plan_id, sort_order),
    CONSTRAINT fk_plan_items_plan FOREIGN KEY (plan_id)
        REFERENCES subscription_plans (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Paid plans typically include NRS eligibility; free tier often does not.
UPDATE subscription_plans SET nrs_integration_allowed = 1, nrs_requires_organization_tax_id = 1
WHERE slug IN ('starter', 'pro');

ALTER TABLE organizations
    DROP COLUMN nrs_api_base_url,
    DROP COLUMN nrs_bearer_token;
