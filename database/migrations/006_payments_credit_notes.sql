-- Stripe payment tracking + credit notes + self-reference for credited invoice.

SET NAMES utf8mb4;

ALTER TABLE invoices
    ADD COLUMN invoice_kind ENUM('invoice', 'credit_note') NOT NULL DEFAULT 'invoice' AFTER status,
    ADD COLUMN credited_invoice_id INT UNSIGNED NULL DEFAULT NULL AFTER invoice_kind,
    ADD COLUMN stripe_checkout_session_id VARCHAR(255) NULL DEFAULT NULL AFTER paid_at,
    ADD COLUMN stripe_payment_intent_id VARCHAR(255) NULL DEFAULT NULL AFTER stripe_checkout_session_id,
    ADD KEY invoices_credited (credited_invoice_id),
    CONSTRAINT fk_invoices_credited_invoice FOREIGN KEY (credited_invoice_id)
        REFERENCES invoices (id) ON DELETE SET NULL;
