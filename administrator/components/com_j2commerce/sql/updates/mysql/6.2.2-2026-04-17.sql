--
-- Multi-token paymentprofiles cleanup.
-- 1. Backfill any NULL payment_token values to '' so customer-anchor rows match
--    the `payment_token = ''` filter used by payment plugins.
-- 2. Force payment_token to NOT NULL DEFAULT '' (matches 6.2.2-2026-04-16.sql intent;
--    the earlier migration left it nullable on installs that pre-dated the change).
-- 3. Drop the legacy 3-column unique index `idx_user_provider_env`; the 4-column
--    `uq_user_provider_env_token` replaces it and allows one customer-anchor row
--    plus N card-token rows per (user, provider, environment).
--

UPDATE `#__j2commerce_paymentprofiles`
    SET `payment_token` = ''
    WHERE `payment_token` IS NULL;

ALTER TABLE `#__j2commerce_paymentprofiles`
    MODIFY `payment_token` VARCHAR(100) NOT NULL DEFAULT '';

ALTER TABLE `#__j2commerce_paymentprofiles`
    DROP INDEX `idx_user_provider_env`;
