-- Business bank details for invoices (Nigeria-focused bank list in app)

ALTER TABLE organizations
    ADD COLUMN invoice_bank_name VARCHAR(160) NULL DEFAULT NULL AFTER invoice_footer,
    ADD COLUMN invoice_bank_code VARCHAR(12) NULL DEFAULT NULL AFTER invoice_bank_name,
    ADD COLUMN invoice_bank_account_name VARCHAR(160) NULL DEFAULT NULL AFTER invoice_bank_code,
    ADD COLUMN invoice_bank_account_number VARCHAR(32) NULL DEFAULT NULL AFTER invoice_bank_account_name;
