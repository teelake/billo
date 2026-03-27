-- Google Sign-In: stable subject + optional password (OAuth-only users have NULL password_hash).

SET NAMES utf8mb4;

ALTER TABLE users
    MODIFY password_hash VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN google_sub VARCHAR(255) NULL DEFAULT NULL AFTER password_hash,
    ADD UNIQUE KEY users_google_sub_unique (google_sub);
