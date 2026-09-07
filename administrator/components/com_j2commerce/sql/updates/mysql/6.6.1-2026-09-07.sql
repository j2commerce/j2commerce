-- Email and invoice templates are resolved by email type, order status, payment
-- method and language, never by who is looking at them, so a view level has no
-- meaning on either table. Retire the column rather than leave it carrying a
-- value that resolves against nothing in #__viewlevels.
ALTER TABLE `#__j2commerce_emailtemplates` DROP COLUMN `access` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` DROP COLUMN `access` /** CAN FAIL **/;
