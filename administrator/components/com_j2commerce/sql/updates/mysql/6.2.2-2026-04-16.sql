--
-- Extend paymentprofiles to support multiple stored payment tokens per user.
-- Adds payment_token (e.g. Square card_id), token_label (display string), and
-- is_renewal_default flag. The customer-anchor row keeps payment_token = ''.
-- A unique key on (user_id, provider, environment, payment_token) enforces one
-- customer row per user and one row per distinct card token.
--

ALTER TABLE `#__j2commerce_paymentprofiles`
    ADD COLUMN `payment_token`      VARCHAR(100) NOT NULL DEFAULT ''
        AFTER `customer_profile_id` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_paymentprofiles`
    ADD COLUMN `token_label`        VARCHAR(100) NOT NULL DEFAULT ''
        AFTER `payment_token` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_paymentprofiles`
    ADD COLUMN `is_renewal_default` TINYINT UNSIGNED NOT NULL DEFAULT 0
        AFTER `token_label` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_paymentprofiles`
    ADD UNIQUE KEY `uq_user_provider_env_token`
        (`user_id`, `provider`, `environment`, `payment_token`) /** CAN FAIL **/;
