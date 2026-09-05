-- Order statuses had no semantic classification, so core code that needed one had to fall back
-- to the row id. Ids are install-dependent: J2Store shipped six core statuses where J2Commerce
-- ships eight, the migrator preserves the source ids verbatim, and merchants add and reorder
-- their own rows, so id 7 or 8 on a real store is frequently a merchant row. Give the row its
-- own lifecycle classification instead. NULL means merchant-defined with no core semantics and
-- is a first-class state.
ALTER TABLE `#__j2commerce_orderstatuses` ADD COLUMN `orderstatus_type` varchar(16) DEFAULT NULL COMMENT 'Lifecycle classification. NULL means merchant-defined, with no core semantics.';

-- Seed the eight core rows by NAME, never by id, and only where the row is still unclassified.
-- A renamed row matches nothing and is simply left unmapped, so the failure mode is "unmapped"
-- rather than "wrong". Migrated stores are covered because the migrator rewrites J2STORE_ names
-- to J2COMMERCE_, so the names line up even where the ids do not. The IS NULL guard makes a
-- second run a no-op and never overwrites a mapping the merchant has chosen.
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'new' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_NEW' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'open' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_PENDING' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'open' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_CONFIRMED' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'open' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_PROCESSED' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'shipped' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_SHIPPED' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'delivered' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_DELIVERED' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'cancelled' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_CANCELLED' AND `orderstatus_type` IS NULL;
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'failed' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_FAILED' AND `orderstatus_type` IS NULL;
