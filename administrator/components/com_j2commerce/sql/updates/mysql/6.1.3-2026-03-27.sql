-- Add audit/check-out metadata columns and indexes using ALTER TABLE statements.
-- created_on and modified_on use DEFAULT CURRENT_TIMESTAMP so that existing rows
-- receive the migration timestamp automatically, avoiding zero-datetime failures
-- in MySQL 8.0 strict mode (STRICT_TRANS_TABLES + NO_ZERO_DATE).

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0,
  ADD COLUMN `enabled` int NOT NULL DEFAULT 1,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

-- Currencies already use lock-equivalent columns (`locked_by`, `locked_on`).
ALTER TABLE `#__j2commerce_currencies`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_createdby` (`created_by`),
  MODIFY COLUMN `modified_on` DATETIME DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

-- Products are article-linked; only ensure access column, ordering and indexes.
-- Also migrate created_on/modified_on from varchar to datetime.
ALTER TABLE `#__j2commerce_products`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0,
  MODIFY COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

-- Variants: migrate created_on/modified_on from varchar to datetime.
ALTER TABLE `#__j2commerce_variants`
  MODIFY COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  MODIFY COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL,
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL,
  ADD INDEX `idx_access` (`access`),
  ADD INDEX `idx_checkout` (`checked_out`),
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;
