-- Add audit/check-out metadata columns and indexes using ALTER TABLE statements.
-- created_on and modified_on use DEFAULT CURRENT_TIMESTAMP so that existing rows
-- receive the migration timestamp automatically, avoiding zero-datetime failures
-- in MySQL 8.0 strict mode (STRICT_TRANS_TABLES + NO_ZERO_DATE).

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD COLUMN `enabled` int NOT NULL DEFAULT 1/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_addresses`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_countries`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


-- Currencies already use lock-equivalent columns (`locked_by`, `locked_on`).
ALTER TABLE `#__j2commerce_currencies`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_currencies`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_currencies`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_customfields`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filtergroups`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_geozones`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_lengths`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_manufacturers`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_options`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orders`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_orderstatuses`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


-- Products are article-linked; only ensure access column, ordering and indexes.
-- Also migrate created_on/modified_on from varchar to datetime.
ALTER TABLE `#__j2commerce_products`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_products`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_products`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_products`
  ADD COLUMN `ordering` int NOT NULL DEFAULT 0/** CAN FAIL **/;

-- ALTER TABLE `#__j2commerce_products`
  -- CHANGE `created_on` `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

-- ALTER TABLE `#__j2commerce_products`
  -- CHANGE `modified_on` `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_products`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_products`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


-- Variants: migrate created_on/modified_on from varchar to datetime.

-- ALTER TABLE `#__j2commerce_variants`
  -- CHANGE `created_on` `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

-- ALTER TABLE `#__j2commerce_variants`
  -- CHANGE `modified_on` `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxprofiles`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_taxrates`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vendors`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_vouchers`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_weights`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;


ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0'/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD COLUMN `checked_out_time` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD INDEX `idx_access` (`access`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD INDEX `idx_checkout` (`checked_out`)/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_zones`
  ADD INDEX `idx_createdby` (`created_by`)/** CAN FAIL **/;
