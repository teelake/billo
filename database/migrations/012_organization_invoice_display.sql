-- Invoice display: tax toggle, template style, brand colors (hex).

ALTER TABLE organizations
    ADD COLUMN invoice_tax_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER invoice_logo_url,
    ADD COLUMN invoice_style VARCHAR(20) NOT NULL DEFAULT 'modern' AFTER invoice_tax_enabled,
    ADD COLUMN invoice_brand_primary CHAR(7) NOT NULL DEFAULT '#1E3A8A' AFTER invoice_style,
    ADD COLUMN invoice_brand_accent CHAR(7) NOT NULL DEFAULT '#16A34A' AFTER invoice_brand_primary;
