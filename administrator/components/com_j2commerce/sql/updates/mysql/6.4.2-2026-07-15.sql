--
-- Voucher & Gift Card improvements (issue #1299).
-- Adds user_id ownership link + modified_on/modified_by audit columns to vouchers
-- (VoucherTable::store() already writes modified_on/modified_by; they were
-- silently dropped on upgraded sites because the columns did not exist),
-- a new append-only voucheradjustments ledger table, and a composite index
-- on orderdiscounts to speed up voucher balance aggregation.
-- Fresh installs already have these; ALTERs will no-op on those sites.
--

ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `user_id` int unsigned DEFAULT NULL AFTER `email_to` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `modified_on` datetime DEFAULT NULL /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `modified_by` int DEFAULT NULL /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
    ADD INDEX `idx_user_id` (`user_id`) /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
    ADD INDEX `idx_valid_to` (`valid_to`) /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__j2commerce_voucheradjustments` (
    `j2commerce_voucheradjustment_id` int unsigned NOT NULL AUTO_INCREMENT,
    `j2commerce_voucher_id` int NOT NULL,
    `adjustment_type` varchar(20) NOT NULL COMMENT 'credit|debit|correction',
    `amount` decimal(15,5) NOT NULL COMMENT 'always positive',
    `balance_before` decimal(15,5) NOT NULL,
    `balance_after` decimal(15,5) NOT NULL,
    `reason` varchar(255) NOT NULL,
    `note` text DEFAULT NULL,
    `order_id` varchar(255) DEFAULT NULL,
    `created_by` int NOT NULL DEFAULT 0,
    `created_on` datetime NOT NULL,
    `ip_address` varchar(45) NOT NULL DEFAULT '',
    PRIMARY KEY (`j2commerce_voucheradjustment_id`),
    KEY `idx_voucher_id` (`j2commerce_voucher_id`),
    KEY `idx_created_on` (`created_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `#__j2commerce_orderdiscounts`
    ADD INDEX `idx_type_entity` (`discount_type`, `discount_entity_id`) /** CAN FAIL **/;
