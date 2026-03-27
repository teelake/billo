-- Legal entity deduplication: TIN + CAC (per country) and company website (global host).
-- If migration fails on UNIQUE, resolve duplicate rows in `organizations` first (same TIN/CAC/site).

ALTER TABLE organizations
    ADD COLUMN tax_id_normalized VARCHAR(64) NULL DEFAULT NULL COMMENT 'Dedup key for TIN' AFTER tax_id,
    ADD COLUMN company_registration_number VARCHAR(40) NULL DEFAULT NULL COMMENT 'CAC/RC/BN raw' AFTER tax_id_normalized,
    ADD COLUMN company_registration_normalized VARCHAR(40) NULL DEFAULT NULL COMMENT 'Dedup key for CAC' AFTER company_registration_number,
    ADD COLUMN company_website VARCHAR(255) NULL DEFAULT NULL COMMENT 'Public site URL raw' AFTER company_registration_normalized,
    ADD COLUMN company_website_host VARCHAR(190) NULL DEFAULT NULL COMMENT 'Dedup key: hostname' AFTER company_website;

-- One workspace per (country + TIN) when TIN is set
CREATE UNIQUE INDEX organizations_country_tin_norm_unique
    ON organizations (billing_country, tax_id_normalized);

-- One workspace per (country + company reg no.) when reg is set
CREATE UNIQUE INDEX organizations_country_reg_norm_unique
    ON organizations (billing_country, company_registration_normalized);

-- One workspace per canonical website host when set
CREATE UNIQUE INDEX organizations_website_host_unique
    ON organizations (company_website_host);
