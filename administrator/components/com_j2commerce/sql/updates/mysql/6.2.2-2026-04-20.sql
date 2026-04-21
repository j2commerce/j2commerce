--
-- Add valid_from and valid_to columns to vouchers table.
-- These were missing from early install SQL so upgraders never got them.
-- Fresh installs already have them; ALTER will no-op on those sites.
--

ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `valid_from` datetime DEFAULT NULL AFTER `email_body` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `valid_to` datetime DEFAULT NULL AFTER `valid_from` /** CAN FAIL **/;
