-- Add is_active (and matching indexes) when landing tables were created from an older 018
-- without these columns. Safe to run once; skip or ignore "Duplicate column" if already applied.

SET NAMES utf8mb4;

ALTER TABLE landing_faqs
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order,
    ADD KEY landing_faqs_active_sort (is_active, sort_order);

ALTER TABLE landing_trusted_logos
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order,
    ADD KEY landing_trusted_logos_active_sort (is_active, sort_order);

ALTER TABLE landing_testimonials
    ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER sort_order,
    ADD KEY landing_testimonials_active_sort (is_active, sort_order);
