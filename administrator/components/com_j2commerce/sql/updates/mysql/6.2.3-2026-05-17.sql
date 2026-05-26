--
-- Restore the `params` column on `#__j2commerce_shippingmethods`.
--
-- The column was present on the legacy `#__j2store_shippingmethods` table
-- and on every other j2store->j2c clone, but was accidentally omitted when
-- the j2commerce_shippingmethods CREATE was authored. Core code (and 3rd
-- party plugins such as plg_j2commerce_app_restrictbyshipping) expects it
-- to be available, so this update brings existing 6.x installs back into
-- parity with the install.mysql.utf8.sql schema.
--

ALTER TABLE `#__j2commerce_shippingmethods` ADD COLUMN `params` LONGTEXT NULL AFTER `shipping_method_type` /** CAN FAIL **/;
