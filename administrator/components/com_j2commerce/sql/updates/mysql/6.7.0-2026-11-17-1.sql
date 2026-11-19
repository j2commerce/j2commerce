ALTER TABLE `#__j2commerce_ordertransactions` ADD COLUMN `method` varchar(100) NOT NULL DEFAULT '' AFTER `plugin`;
ALTER TABLE `#__j2commerce_ordertransactions` ADD COLUMN `reference` varchar(255) NOT NULL DEFAULT '' AFTER `parent_txn_id`;
ALTER TABLE `#__j2commerce_ordertransactions` ADD COLUMN `note` varchar(1000) NOT NULL DEFAULT '' AFTER `reference`;
ALTER TABLE `#__j2commerce_ordertransactions` ADD COLUMN `received_at` datetime NULL DEFAULT NULL AFTER `note`;
