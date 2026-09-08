-- Sync Core identified a core template by its email_type/orderstatus_id/receiver_type triple,
-- which a duplicate carries verbatim -- so a merchant's copy was indistinguishable from the row
-- it was copied from and could be written over. Give the core rows a name of their own instead.
--
-- '' means "not claimed yet": the first sync after this update adopts a row by the identity
-- match it has always used and stamps the key, and every later run matches the key directly.
-- A duplicate or an import is stamped 'custom', which no tier accepts.

/** CAN FAIL **/
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `core_key` varchar(64) DEFAULT '';

/** CAN FAIL **/
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `core_key` varchar(64) DEFAULT '';
