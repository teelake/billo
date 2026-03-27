-- Provider-neutral payment columns (Paystack, Stripe, or future gateways).

SET NAMES utf8mb4;

ALTER TABLE invoices
    ADD COLUMN payment_provider VARCHAR(32) NULL DEFAULT NULL AFTER paid_at,
    ADD COLUMN gateway_checkout_ref VARCHAR(255) NULL DEFAULT NULL AFTER payment_provider,
    ADD COLUMN gateway_transaction_ref VARCHAR(255) NULL DEFAULT NULL AFTER gateway_checkout_ref;

UPDATE invoices
SET
    gateway_checkout_ref = stripe_checkout_session_id,
    gateway_transaction_ref = stripe_payment_intent_id,
    payment_provider = CASE
        WHEN stripe_checkout_session_id IS NOT NULL OR stripe_payment_intent_id IS NOT NULL THEN 'stripe'
        ELSE NULL
    END
WHERE stripe_checkout_session_id IS NOT NULL OR stripe_payment_intent_id IS NOT NULL;

ALTER TABLE invoices
    DROP COLUMN stripe_checkout_session_id,
    DROP COLUMN stripe_payment_intent_id;
