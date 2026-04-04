--
-- J2Commerce Database Install Script
-- Migrated from J2Store with Joomla 6 / MySQL 8.0+ best practices
--

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_addresses`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_addresses` (
  `j2commerce_address_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL DEFAULT 0,
  `first_name` varchar(255) NOT NULL DEFAULT '',
  `last_name` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `address_1` varchar(255) NOT NULL DEFAULT '',
  `address_2` varchar(255) NOT NULL DEFAULT '',
  `city` varchar(255) NOT NULL DEFAULT '',
  `zip` varchar(255) NOT NULL DEFAULT '',
  `zone_id` varchar(255) NOT NULL DEFAULT '',
  `country_id` varchar(255) NOT NULL DEFAULT '',
  `phone_1` varchar(255) NOT NULL DEFAULT '',
  `phone_2` varchar(255) NOT NULL DEFAULT '',
  `fax` varchar(255) NOT NULL DEFAULT '',
  `type` varchar(255) NOT NULL DEFAULT '',
  `company` varchar(255) NOT NULL DEFAULT '',
  `tax_number` varchar(255) NOT NULL DEFAULT '',
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 1,
  `params` text NULL,
  PRIMARY KEY (`j2commerce_address_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_carts`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_carts` (
  `j2commerce_cart_id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `cart_type` varchar(255) NOT NULL DEFAULT 'cart',
  `created_on` datetime NOT NULL,
  `modified_on` datetime NOT NULL,
  `customer_ip` varchar(255) NOT NULL,
  `cart_params` text NOT NULL,
  `cart_browser` text NOT NULL,
  `cart_voucher` varchar(255) NOT NULL DEFAULT '',
  `cart_coupon` varchar(255) NOT NULL DEFAULT '',
  `cart_analytics` text NOT NULL,
  PRIMARY KEY (`j2commerce_cart_id`),
  KEY `session_id` (`session_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_cartitems`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_cartitems` (
  `j2commerce_cartitem_id` int unsigned NOT NULL AUTO_INCREMENT,
  `cart_id` int unsigned NOT NULL,
  `product_id` int unsigned NOT NULL,
  `variant_id` int unsigned NOT NULL,
  `vendor_id` int unsigned NOT NULL,
  `product_type` varchar(255) NOT NULL,
  `cartitem_params` text NOT NULL,
  `product_qty` decimal(12,4) NOT NULL,
  `product_options` text NOT NULL,
  PRIMARY KEY (`j2commerce_cartitem_id`),
  KEY `cart_id` (`cart_id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_configurations`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_configurations` (
  `config_meta_key` varchar(255) NOT NULL,
  `config_meta_value` text,
  `config_meta_default` text DEFAULT NULL,
  PRIMARY KEY (`config_meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_countries`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_countries` (
  `j2commerce_country_id` int NOT NULL AUTO_INCREMENT,
  `country_name` varchar(255) NOT NULL,
  `country_isocode_2` varchar(5) NOT NULL,
  `country_isocode_3` varchar(5) NOT NULL,
  `country_isocode_num` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_country_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_coupons`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_coupons` (
  `j2commerce_coupon_id` int NOT NULL AUTO_INCREMENT,
  `coupon_name` varchar(255) NOT NULL,
  `coupon_code` varchar(255) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `value` decimal(15,5) NOT NULL,
  `value_type` varchar(255) NOT NULL,
  `max_value` varchar(255) NOT NULL,
  `free_shipping` int NOT NULL,
  `max_uses` int NOT NULL,
  `logged` int NOT NULL,
  `max_customer_uses` int NOT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_to` datetime DEFAULT NULL,
  `product_category` varchar(255) NOT NULL,
  `products` varchar(255) NOT NULL,
  `min_subtotal` varchar(255) NOT NULL,
  `users` text NOT NULL,
  `mycategory` text NOT NULL,
  `brand_ids` text NOT NULL,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_coupon_id`),
  UNIQUE KEY `coupon_code` (`coupon_code`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_currencies`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_currencies` (
  `j2commerce_currency_id` int NOT NULL AUTO_INCREMENT,
  `currency_title` varchar(32) NOT NULL,
  `currency_code` varchar(3) NOT NULL,
  `currency_position` varchar(12) NOT NULL,
  `currency_symbol` varchar(255) NOT NULL,
  `currency_num_decimals` int NOT NULL,
  `currency_decimal` varchar(12) NOT NULL,
  `currency_thousands` char(1) NOT NULL,
  `currency_value` decimal(15,8) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint unsigned NOT NULL DEFAULT 0,
  `modified_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `modified_by` bigint unsigned NOT NULL DEFAULT 0,
  `locked_on` datetime DEFAULT NULL,
  `locked_by` bigint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_currency_id`),
  UNIQUE KEY `currency_code` (`currency_code`),
  KEY `idx_access` (`access`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default currencies for new installs
INSERT IGNORE INTO `#__j2commerce_currencies`
  (`currency_title`, `currency_code`, `currency_position`, `currency_symbol`, `currency_num_decimals`, `currency_decimal`, `currency_thousands`, `currency_value`, `enabled`, `ordering`)
VALUES
  ('US Dollar', 'USD', 'pre', '$', 2, '.', ',', 1.00000000, 1, 1),
  ('Euro', 'EUR', 'pre', '€', 2, '.', ',', 1.00000000, 1, 2),
  ('British Pound', 'GBP', 'pre', '£', 2, '.', ',', 1.00000000, 1, 3),
  ('Canadian Dollar', 'CAD', 'pre', '$', 2, '.', ',', 1.00000000, 1, 4),
  ('Australian Dollar', 'AUD', 'pre', '$', 2, '.', ',', 1.00000000, 1, 5),
  ('Japanese Yen', 'JPY', 'pre', '¥', 0, '.', ',', 1.00000000, 1, 6),
  ('Swiss Franc', 'CHF', 'pre', 'CHF', 2, '.', ',', 1.00000000, 1, 7),
  ('Indian Rupee', 'INR', 'pre', '₹', 2, '.', ',', 1.00000000, 1, 8),
  ('Chinese Yuan', 'CNY', 'pre', '¥', 2, '.', ',', 1.00000000, 1, 9),
  ('Brazilian Real', 'BRL', 'pre', 'R$', 2, ',', '.', 1.00000000, 1, 10),
  ('Mexican Peso', 'MXN', 'pre', '$', 2, '.', ',', 1.00000000, 1, 11),
  ('South Korean Won', 'KRW', 'pre', '₩', 0, '.', ',', 1.00000000, 1, 12),
  ('Swedish Krona', 'SEK', 'post', 'kr', 2, ',', '.', 1.00000000, 1, 13),
  ('Norwegian Krone', 'NOK', 'post', 'kr', 2, ',', '.', 1.00000000, 1, 14),
  ('Danish Krone', 'DKK', 'post', 'kr', 2, ',', '.', 1.00000000, 1, 15),
  ('New Zealand Dollar', 'NZD', 'pre', '$', 2, '.', ',', 1.00000000, 1, 16),
  ('Singapore Dollar', 'SGD', 'pre', '$', 2, '.', ',', 1.00000000, 1, 17),
  ('Hong Kong Dollar', 'HKD', 'pre', 'HK$', 2, '.', ',', 1.00000000, 1, 18),
  ('South African Rand', 'ZAR', 'pre', 'R', 2, '.', ',', 1.00000000, 1, 19),
  ('Turkish Lira', 'TRY', 'pre', '₺', 2, ',', '.', 1.00000000, 1, 20),
  ('Polish Zloty', 'PLN', 'post', 'zł', 2, ',', '.', 1.00000000, 1, 21),
  ('Thai Baht', 'THB', 'pre', '฿', 2, '.', ',', 1.00000000, 1, 22),
  ('Israeli New Shekel', 'ILS', 'pre', '₪', 2, '.', ',', 1.00000000, 1, 23),
  ('Philippine Peso', 'PHP', 'pre', '₱', 2, '.', ',', 1.00000000, 1, 24),
  ('Malaysian Ringgit', 'MYR', 'pre', 'RM', 2, '.', ',', 1.00000000, 1, 25),
  ('Indonesian Rupiah', 'IDR', 'pre', 'Rp', 0, ',', '.', 1.00000000, 1, 26),
  ('Czech Koruna', 'CZK', 'post', 'Kč', 2, ',', '.', 1.00000000, 1, 27),
  ('Hungarian Forint', 'HUF', 'post', 'Ft', 0, ',', '.', 1.00000000, 1, 28),
  ('UAE Dirham', 'AED', 'pre', 'د.إ', 2, '.', ',', 1.00000000, 1, 29),
  ('Saudi Riyal', 'SAR', 'pre', '﷼', 2, '.', ',', 1.00000000, 1, 30),
  ('Colombian Peso', 'COP', 'pre', '$', 0, ',', '.', 1.00000000, 1, 31),
  ('Argentine Peso', 'ARS', 'pre', '$', 2, ',', '.', 1.00000000, 1, 32),
  ('Nigerian Naira', 'NGN', 'pre', '₦', 2, '.', ',', 1.00000000, 1, 33),
  ('Egyptian Pound', 'EGP', 'pre', 'E£', 2, '.', ',', 1.00000000, 1, 34),
  ('Pakistani Rupee', 'PKR', 'pre', '₨', 0, '.', ',', 1.00000000, 1, 35),
  ('Bangladeshi Taka', 'BDT', 'pre', '৳', 2, '.', ',', 1.00000000, 1, 36),
  ('Vietnamese Dong', 'VND', 'post', '₫', 0, ',', '.', 1.00000000, 1, 37),
  ('Romanian Leu', 'RON', 'post', 'lei', 2, ',', '.', 1.00000000, 1, 38),
  ('Ukrainian Hryvnia', 'UAH', 'post', '₴', 2, ',', '.', 1.00000000, 1, 39),
  ('Peruvian Sol', 'PEN', 'pre', 'S/', 2, '.', ',', 1.00000000, 1, 40),
  ('Chilean Peso', 'CLP', 'pre', '$', 0, ',', '.', 1.00000000, 1, 41),
  ('Kuwaiti Dinar', 'KWD', 'pre', 'د.ك', 3, '.', ',', 1.00000000, 1, 42),
  ('Qatari Riyal', 'QAR', 'pre', 'ر.ق', 2, '.', ',', 1.00000000, 1, 43),
  ('Omani Rial', 'OMR', 'pre', 'ر.ع.', 3, '.', ',', 1.00000000, 1, 44),
  ('Bahraini Dinar', 'BHD', 'pre', '.د.ب', 3, '.', ',', 1.00000000, 1, 45),
  ('Jordanian Dinar', 'JOD', 'pre', 'د.ا', 3, '.', ',', 1.00000000, 1, 46),
  ('Russian Ruble', 'RUB', 'post', '₽', 2, ',', '.', 1.00000000, 1, 47),
  ('Taiwanese Dollar', 'TWD', 'pre', 'NT$', 0, '.', ',', 1.00000000, 1, 48);

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_customfields`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_customfields` (
  `j2commerce_customfield_id` smallint unsigned NOT NULL AUTO_INCREMENT,
  `field_table` varchar(50) DEFAULT NULL,
  `field_name` varchar(250) NOT NULL,
  `field_namekey` varchar(50) NOT NULL,
  `field_type` varchar(50) DEFAULT NULL,
  `field_value` longtext NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `field_options` text,
  `field_core` tinyint unsigned NOT NULL DEFAULT 0,
  `field_required` tinyint unsigned NOT NULL DEFAULT 0,
  `field_default` varchar(250) DEFAULT NULL,
  `field_placeholder` varchar(250) DEFAULT NULL,
  `field_autocomplete` varchar(100) DEFAULT NULL,
  `field_width` varchar(20) DEFAULT '',
  `field_access` varchar(255) NOT NULL DEFAULT 'all',
  `field_categories` varchar(255) NOT NULL DEFAULT 'all',
  `field_with_sub_categories` tinyint NOT NULL DEFAULT 0,
  `field_frontend` tinyint unsigned NOT NULL DEFAULT 0,
  `field_backend` tinyint unsigned NOT NULL DEFAULT 1,
  `field_display` text NOT NULL,
  `field_display_billing` smallint NOT NULL DEFAULT 0,
  `field_display_register` smallint NOT NULL DEFAULT 0,
  `field_display_shipping` smallint NOT NULL DEFAULT 0,
  `field_display_guest` smallint NOT NULL DEFAULT 0,
  `field_display_guest_shipping` smallint NOT NULL DEFAULT 0,
  `field_display_payment` smallint NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_customfield_id`),
  UNIQUE KEY `field_namekey` (`field_namekey`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default custom fields data
INSERT IGNORE INTO `#__j2commerce_customfields` (`j2commerce_customfield_id`, `field_table`, `field_name`, `field_namekey`, `field_type`, `field_value`, `enabled`, `ordering`, `field_options`, `field_core`, `field_required`, `field_default`, `field_placeholder`, `field_autocomplete`, `field_access`, `field_categories`, `field_with_sub_categories`, `field_frontend`, `field_backend`, `field_display`, `field_display_billing`, `field_display_register`, `field_display_shipping`, `field_display_guest`, `field_display_guest_shipping`, `field_display_payment`) VALUES
(1, 'address', 'J2COMMERCE_ADDRESS_FIRSTNAME', 'first_name', 'text', '', 1, 2, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_FIRSTNAME', 'given-name', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(2, 'address', 'J2COMMERCE_ADDRESS_LASTNAME', 'last_name', 'text', '', 1, 3, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_LASTNAME', 'family-name', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(3, 'address', 'J2COMMERCE_EMAIL', 'email', 'email', '', 1, 1, 'a:8:{s:12:"errormessage";s:38:"J2COMMERCE_VALIDATION_ENTER_VALID_EMAIL";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_EMAIL', 'email', 'all', 'all', 0, 0, 1, '', 1, 1, 0, 1, 0, 0),
(4, 'address', 'J2COMMERCE_ADDRESS_LINE1', 'address_1', 'text', '', 1, 4, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_ADDRESS_1', 'address-line1', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(5, 'address', 'J2COMMERCE_ADDRESS_LINE2', 'address_2', 'text', '', 1, 5, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 0, '', 'J2COMMERCE_PLACEHOLDER_ADDRESS_2', 'address-line2', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(6, 'address', 'J2COMMERCE_ADDRESS_CITY', 'city', 'text', '', 1, 6, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_CITY', 'address-level2', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(7, 'address', 'J2COMMERCE_ADDRESS_ZIP', 'zip', 'text', '', 1, 7, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_ZIP', 'postal-code', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(8, 'address', 'J2COMMERCE_ADDRESS_PHONE', 'phone_1', 'telephone', '', 1, 10, 'a:8:{s:12:"errormessage";s:0:"";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 0, '', 'J2COMMERCE_PLACEHOLDER_PHONE', 'tel', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(9, 'address', 'J2COMMERCE_ADDRESS_MOBILE', 'phone_2', 'telephone', '', 1, 11, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', 'J2COMMERCE_PLACEHOLDER_MOBILE', 'tel', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(10, 'address', 'J2COMMERCE_ADDRESS_COMPANY_NAME', 'company', 'text', '', 1, 12, 'a:8:{s:12:"errormessage";s:0:"";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 0, '', 'J2COMMERCE_PLACEHOLDER_COMPANY', 'organization', 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(11, 'address', 'J2COMMERCE_ADDRESS_TAX_NUMBER', 'tax_number', 'text', '', 1, 13, 'a:8:{s:12:"errormessage";s:0:"";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 0, '', 'J2COMMERCE_PLACEHOLDER_TAX_NUMBER', 'off', 'all', 'all', 0, 0, 1, '', 1, 1, 0, 1, 0, 0),
(12, 'address', 'J2COMMERCE_ADDRESS_COUNTRY', 'country_id', 'zone', '', 1, 8, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:7:"country";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', NULL, NULL, 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0),
(13, 'address', 'J2COMMERCE_ADDRESS_ZONE', 'zone_id', 'zone', '', 1, 9, 'a:8:{s:12:"errormessage";s:24:"J2COMMERCE_FIELD_REQUIRED";s:9:"filtering";s:1:"0";s:9:"maxlength";s:1:"0";s:4:"size";s:0:"";s:4:"cols";s:0:"";s:9:"zone_type";s:4:"zone";s:6:"format";s:0:"";s:8:"readonly";s:1:"0";}', 1, 1, '', NULL, NULL, 'all', 'all', 0, 0, 1, '', 1, 1, 1, 1, 1, 0);

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_emailtemplates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_emailtemplates` (
  `j2commerce_emailtemplate_id` int NOT NULL AUTO_INCREMENT,
  `email_type` varchar(255) NOT NULL,
  `context` varchar(100) NOT NULL DEFAULT '',
  `receiver_type` varchar(255) NOT NULL DEFAULT '*',
  `orderstatus_id` varchar(255) NOT NULL,
  `group_id` varchar(255) NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `body_json` mediumtext NULL DEFAULT NULL,
  `body_source` varchar(255) NOT NULL DEFAULT 'editor',
  `body_source_file` varchar(255) NOT NULL DEFAULT '',
  `custom_css` text DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT '*',
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_emailtemplate_id`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_emailtype_tags`
-- Stores available tags for each email type
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_emailtype_tags` (
  `j2commerce_emailtype_tag_id` int NOT NULL AUTO_INCREMENT,
  `email_type` varchar(255) NOT NULL,
  `tag_name` varchar(100) NOT NULL,
  `tag_label` varchar(255) NOT NULL,
  `tag_description` text,
  `tag_group` varchar(100) DEFAULT 'general',
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_emailtype_tag_id`),
  KEY `idx_email_type` (`email_type`),
  KEY `idx_tag_name` (`tag_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default tags for transactional email type
INSERT IGNORE INTO `#__j2commerce_emailtype_tags`
  (`email_type`, `tag_name`, `tag_label`, `tag_description`, `tag_group`, `ordering`)
VALUES
  ('transactional', 'ORDER_ID', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERID', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERID_DESC', 'order', 1),
  ('transactional', 'ORDER_DATE', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERDATE', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERDATE_DESC', 'order', 2),
  ('transactional', 'ORDER_STATUS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERSTATUS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERSTATUS_DESC', 'order', 3),
  ('transactional', 'ORDER_TOTAL', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERAMOUNT', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ORDERAMOUNT_DESC', 'order', 4),
  ('transactional', 'ORDER_SUBTOTAL', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SUBTOTAL', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SUBTOTAL_DESC', 'order', 5),
  ('transactional', 'ORDER_TAX', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_AMOUNT', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_TAX_AMOUNT_DESC', 'order', 6),
  ('transactional', 'ORDER_SHIPPING', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_AMOUNT', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_AMOUNT_DESC', 'order', 7),
  ('transactional', 'ORDER_DISCOUNT', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_DISCOUNT_AMOUNT', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_DISCOUNT_AMOUNT_DESC', 'order', 8),
  ('transactional', 'ORDER_ITEMS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ITEMS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_ITEMS_DESC', 'order', 9),
  ('transactional', 'CUSTOMER_NAME', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_NAME', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_NAME_DESC', 'customer', 10),
  ('transactional', 'CUSTOMER_EMAIL', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_EMAIL', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_CUSTOMER_EMAIL_DESC', 'customer', 11),
  ('transactional', 'BILLING_ADDRESS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_BILLING_ADDRESS_DESC', 'customer', 12),
  ('transactional', 'SHIPPING_ADDRESS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS', 'COM_J2COMMERCE_EMAILTEMPLATE_TAG_SHIPPING_ADDRESS_DESC', 'customer', 13),
  ('transactional', 'PAYMENT_METHOD', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_PAYMENT_METHOD', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_PAYMENT_METHOD_DESC', 'payment', 14),
  ('transactional', 'SHIPPING_METHOD', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_METHOD', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SHIPPING_METHOD_DESC', 'shipping', 15),
  ('transactional', 'SITE_NAME', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SITE_NAME', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_SITE_NAME_DESC', 'store', 16),
  ('transactional', 'SITE_URL', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_URL', 'COM_J2COMMERCE_EMAILTEMPLATE_SHORTCODE_STORE_URL_DESC', 'store', 17);

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_emailtype_contexts`
-- Defines contexts for each email type (sent, expired, etc.)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_emailtype_contexts` (
  `j2commerce_emailtype_context_id` int NOT NULL AUTO_INCREMENT,
  `email_type` varchar(255) NOT NULL,
  `context` varchar(100) NOT NULL,
  `label` varchar(255) NOT NULL,
  `description` text,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_emailtype_context_id`),
  UNIQUE KEY `idx_type_context` (`email_type`, `context`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default contexts for transactional email type
INSERT IGNORE INTO `#__j2commerce_emailtype_contexts`
  (`email_type`, `context`, `label`, `description`, `ordering`)
VALUES
  ('transactional', 'order_confirmed', 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_CONFIRMED', '', 1),
  ('transactional', 'order_cancelled', 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_CANCELLED', '', 2),
  ('transactional', 'order_shipped', 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_SHIPPED', '', 3),
  ('transactional', 'order_refunded', 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_ORDER_REFUNDED', '', 4),
  ('transactional', 'payment_received', 'COM_J2COMMERCE_EMAILTYPE_CONTEXT_PAYMENT_RECEIVED', '', 5);

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_filtergroups`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_filtergroups` (
  `j2commerce_filtergroup_id` int NOT NULL AUTO_INCREMENT,
  `group_name` varchar(255) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_filtergroup_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_filters`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_filters` (
  `j2commerce_filter_id` int NOT NULL AUTO_INCREMENT,
  `group_id` int NOT NULL,
  `filter_name` varchar(255) DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_filter_id`),
  KEY `group_id` (`group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_geocode_cache`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_geocode_cache` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `address_hash` char(32) NOT NULL,
  `address_text` varchar(500) NOT NULL DEFAULT '',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `created` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`id`),
 UNIQUE KEY `idx_address_hash` (`address_hash`),
 KEY `idx_created` (`created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_geozones`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_geozones` (
  `j2commerce_geozone_id` int NOT NULL AUTO_INCREMENT,
  `geozone_name` varchar(255) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_geozone_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_geozonerules`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_geozonerules` (
  `j2commerce_geozonerule_id` int NOT NULL AUTO_INCREMENT,
  `geozone_id` int NOT NULL,
  `country_id` int NOT NULL,
  `zone_id` int NOT NULL,
  PRIMARY KEY (`j2commerce_geozonerule_id`),
  UNIQUE KEY `georule` (`geozone_id`,`country_id`,`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_invoicetemplates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_invoicetemplates` (
  `j2commerce_invoicetemplate_id` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `invoice_type` varchar(255) NOT NULL,
  `orderstatus_id` varchar(255) NOT NULL,
  `group_id` varchar(255) NOT NULL,
  `paymentmethod` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `body_json` mediumtext NULL DEFAULT NULL,
  `body_source` varchar(255) NOT NULL DEFAULT 'editor',
  `body_source_file` varchar(255) NOT NULL DEFAULT '',
  `custom_css` text NULL DEFAULT NULL,
  `language` varchar(10) NOT NULL DEFAULT '*',
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_invoicetemplate_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_lengths`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_lengths` (
  `j2commerce_length_id` int NOT NULL AUTO_INCREMENT,
  `length_title` varchar(255) NOT NULL,
  `length_unit` varchar(4) NOT NULL,
  `length_value` decimal(15,8) NOT NULL,
  `num_decimals` int NOT NULL DEFAULT 2,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_length_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_manufacturers`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_manufacturers` (
  `j2commerce_manufacturer_id` int NOT NULL AUTO_INCREMENT,
  `address_id` int NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `brand_desc_id` int DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_manufacturer_id`),
  KEY `address_id` (`address_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_metafields`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_metafields` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `metakey` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `scope` varchar(255) NOT NULL,
  `metavalue` text NOT NULL,
  `valuetype` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `owner_id` int unsigned NOT NULL,
  `owner_resource` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `metafields_owner_id_index` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_options`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_options` (
  `j2commerce_option_id` int NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `option_unique_name` varchar(255) NOT NULL,
  `option_name` varchar(255) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `option_params` text,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_option_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_optionvalues`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_optionvalues` (
  `j2commerce_optionvalue_id` int NOT NULL AUTO_INCREMENT,
  `option_id` int NOT NULL,
  `optionvalue_name` varchar(255) NOT NULL,
  `optionvalue_image` longtext NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_optionvalue_id`),
  KEY `option_id` (`option_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderdiscounts`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderdiscounts` (
  `j2commerce_orderdiscount_id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `discount_type` varchar(255) NOT NULL,
  `discount_entity_id` int unsigned NOT NULL,
  `discount_title` varchar(255) NOT NULL,
  `discount_code` varchar(255) NOT NULL,
  `discount_value` varchar(255) NOT NULL,
  `discount_value_type` varchar(255) NOT NULL,
  `discount_customer_email` varchar(255) NOT NULL,
  `user_id` int unsigned NOT NULL,
  `discount_amount` decimal(15,5) NOT NULL,
  `discount_tax` decimal(15,5) NOT NULL,
  `discount_params` text NOT NULL,
  PRIMARY KEY (`j2commerce_orderdiscount_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderfees`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderfees` (
  `j2commerce_orderfee_id` int unsigned NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL DEFAULT '0',
  `name` varchar(255) NOT NULL DEFAULT '',
  `amount` decimal(15,5) NOT NULL DEFAULT 0.00000,
  `tax_class_id` int NOT NULL DEFAULT 0,
  `taxable` int NOT NULL DEFAULT 0,
  `tax` decimal(15,5) NOT NULL DEFAULT 0.00000,
  `tax_data` text,
  `fee_type` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`j2commerce_orderfee_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderdownloads`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderdownloads` (
  `j2commerce_orderdownload_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `product_id` int NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `limit_count` bigint NOT NULL,
  `access_granted` datetime NOT NULL,
  `access_expires` datetime NOT NULL,
  PRIMARY KEY (`j2commerce_orderdownload_id`),
  KEY `download_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderhistories`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderhistories` (
  `j2commerce_orderhistory_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `order_state_id` int NOT NULL,
  `notify_customer` int NOT NULL,
  `comment` text NOT NULL,
  `created_on` datetime NOT NULL,
  `created_by` int NOT NULL,
  `params` text NOT NULL,
  PRIMARY KEY (`j2commerce_orderhistory_id`),
  KEY `history_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderinfos`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderinfos` (
  `j2commerce_orderinfo_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `billing_company` varchar(255) DEFAULT NULL,
  `billing_last_name` varchar(255) DEFAULT NULL,
  `billing_first_name` varchar(255) DEFAULT NULL,
  `billing_middle_name` varchar(255) DEFAULT NULL,
  `billing_phone_1` varchar(255) DEFAULT NULL,
  `billing_phone_2` varchar(255) DEFAULT NULL,
  `billing_fax` varchar(255) DEFAULT NULL,
  `billing_address_1` varchar(255) NOT NULL DEFAULT '',
  `billing_address_2` varchar(255) DEFAULT NULL,
  `billing_city` varchar(255) NOT NULL DEFAULT '',
  `billing_zone_name` varchar(255) NOT NULL DEFAULT '',
  `billing_country_name` varchar(255) NOT NULL DEFAULT '',
  `billing_zone_id` int NOT NULL DEFAULT 0,
  `billing_country_id` int NOT NULL DEFAULT 0,
  `billing_zip` varchar(255) NOT NULL DEFAULT '',
  `billing_tax_number` varchar(255) DEFAULT NULL,
  `shipping_company` varchar(255) DEFAULT NULL,
  `shipping_last_name` varchar(255) DEFAULT NULL,
  `shipping_first_name` varchar(255) DEFAULT NULL,
  `shipping_middle_name` varchar(255) DEFAULT NULL,
  `shipping_phone_1` varchar(255) DEFAULT NULL,
  `shipping_phone_2` varchar(255) DEFAULT NULL,
  `shipping_fax` varchar(255) DEFAULT NULL,
  `shipping_address_1` varchar(255) NOT NULL DEFAULT '',
  `shipping_address_2` varchar(255) DEFAULT NULL,
  `shipping_city` varchar(255) NOT NULL DEFAULT '',
  `shipping_zip` varchar(255) NOT NULL,
  `shipping_zone_name` varchar(255) NOT NULL DEFAULT '',
  `shipping_country_name` varchar(255) NOT NULL DEFAULT '',
  `shipping_zone_id` int NOT NULL DEFAULT 0,
  `shipping_country_id` int NOT NULL DEFAULT 0,
  `shipping_id` varchar(255) NOT NULL DEFAULT '',
  `shipping_tax_number` varchar(255) DEFAULT NULL,
  `all_billing` longtext NOT NULL,
  `all_shipping` longtext NOT NULL,
  `all_payment` longtext NOT NULL,
  PRIMARY KEY (`j2commerce_orderinfo_id`),
  KEY `idx_orderinfo_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderitemattributes`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderitemattributes` (
  `j2commerce_orderitemattribute_id` int NOT NULL AUTO_INCREMENT,
  `orderitem_id` int NOT NULL,
  `productattributeoption_id` int NOT NULL,
  `productattributeoptionvalue_id` int NOT NULL,
  `orderitemattribute_name` varchar(255) NOT NULL,
  `orderitemattribute_value` varchar(255) NOT NULL,
  `orderitemattribute_prefix` varchar(1) NOT NULL,
  `orderitemattribute_price` decimal(15,5) NOT NULL,
  `orderitemattribute_code` varchar(255) NOT NULL,
  `orderitemattribute_type` varchar(255) NOT NULL,
  PRIMARY KEY (`j2commerce_orderitemattribute_id`),
  KEY `attribute_orderitem_id` (`orderitem_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderitems`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderitems` (
  `j2commerce_orderitem_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `orderitem_type` varchar(255) NOT NULL DEFAULT 'normal',
  `cart_id` int NOT NULL,
  `cartitem_id` int unsigned NOT NULL,
  `product_id` int NOT NULL,
  `product_type` varchar(255) NOT NULL,
  `variant_id` int NOT NULL,
  `vendor_id` int NOT NULL,
  `orderitem_sku` varchar(255) NOT NULL,
  `orderitem_name` varchar(255) NOT NULL,
  `orderitem_attributes` text NOT NULL,
  `orderitem_quantity` varchar(255) NOT NULL,
  `orderitem_taxprofile_id` int NOT NULL,
  `orderitem_per_item_tax` decimal(15,5) NOT NULL,
  `orderitem_tax` decimal(15,5) NOT NULL,
  `orderitem_discount` decimal(15,5) NOT NULL,
  `orderitem_discount_tax` decimal(15,5) NOT NULL,
  `orderitem_price` decimal(15,5) NOT NULL,
  `orderitem_option_price` decimal(15,5) NOT NULL,
  `orderitem_finalprice` decimal(15,5) NOT NULL,
  `orderitem_finalprice_with_tax` decimal(15,5) NOT NULL,
  `orderitem_finalprice_without_tax` decimal(15,5) NOT NULL,
  `orderitem_params` text NOT NULL,
  `created_on` datetime NOT NULL,
  `created_by` int NOT NULL,
  `orderitem_weight` varchar(255) NOT NULL,
  `orderitem_weight_total` varchar(255) NOT NULL,
  PRIMARY KEY (`j2commerce_orderitem_id`),
  KEY `orderitem_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orders`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orders` (
  `j2commerce_order_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `order_type` varchar(255) NOT NULL DEFAULT 'normal',
  `parent_id` int DEFAULT NULL,
  `subscription_id` int DEFAULT NULL,
  `cart_id` int unsigned NOT NULL,
  `invoice_prefix` varchar(255) NOT NULL,
  `invoice_number` int NOT NULL,
  `token` varchar(255) NOT NULL,
  `user_id` int NOT NULL,
  `user_email` varchar(255) NOT NULL,
  `order_total` decimal(15,5) NOT NULL,
  `order_subtotal` decimal(15,5) NOT NULL,
  `order_subtotal_ex_tax` decimal(15,5) DEFAULT NULL,
  `order_tax` decimal(15,5) NOT NULL,
  `order_shipping` decimal(15,5) NOT NULL,
  `order_shipping_tax` decimal(15,5) NOT NULL,
  `order_discount` decimal(15,5) NOT NULL,
  `order_discount_tax` decimal(15,5) DEFAULT NULL,
  `order_credit` decimal(15,5) NOT NULL,
  `order_refund` decimal(15,5) DEFAULT NULL,
  `order_surcharge` decimal(15,5) NOT NULL,
  `order_fees` decimal(15,5) DEFAULT NULL,
  `orderpayment_type` varchar(255) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `transaction_status` varchar(255) NOT NULL,
  `transaction_details` text NOT NULL,
  `currency_id` int NOT NULL,
  `currency_code` varchar(255) NOT NULL,
  `currency_value` decimal(15,5) NOT NULL,
  `ip_address` varchar(255) NOT NULL,
  `is_shippable` int NOT NULL,
  `is_including_tax` int NOT NULL,
  `customer_note` text NOT NULL,
  `customer_language` varchar(255) NOT NULL,
  `customer_group` varchar(255) NOT NULL,
  `order_state_id` int NOT NULL,
  `order_state` varchar(255) NOT NULL COMMENT 'Legacy compatibility',
  `order_params` text DEFAULT NULL,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `campaign_double_opt_in` int DEFAULT NULL,
  `campaign_order_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`j2commerce_order_id`),
  KEY `idx_order_id` (`order_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_ordershippings`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_ordershippings` (
  `j2commerce_ordershipping_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL DEFAULT '0',
  `ordershipping_type` varchar(255) NOT NULL DEFAULT '' COMMENT 'Element name of shipping plugin',
  `ordershipping_price` decimal(15,5) DEFAULT 0.00000,
  `ordershipping_name` varchar(255) NOT NULL DEFAULT '',
  `ordershipping_code` varchar(255) NOT NULL DEFAULT '',
  `ordershipping_tax` decimal(15,5) DEFAULT 0.00000,
  `ordershipping_extra` decimal(15,5) DEFAULT 0.00000,
  `ordershipping_tracking_id` mediumtext NOT NULL,
  PRIMARY KEY (`j2commerce_ordershipping_id`),
  KEY `idx_order_shipping_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_orderstatuses`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_orderstatuses` (
  `j2commerce_orderstatus_id` int NOT NULL AUTO_INCREMENT,
  `orderstatus_name` varchar(32) NOT NULL,
  `orderstatus_cssclass` text NOT NULL,
  `orderstatus_core` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_orderstatus_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default order statuses
INSERT IGNORE INTO `#__j2commerce_orderstatuses` (`j2commerce_orderstatus_id`, `orderstatus_name`, `orderstatus_cssclass`, `orderstatus_core`, `enabled`, `ordering`) VALUES
(1, 'J2COMMERCE_CONFIRMED', 'badge text-bg-success', 1, 1, 1),
(2, 'J2COMMERCE_PROCESSED', 'badge text-bg-info', 1, 1, 2),
(3, 'J2COMMERCE_FAILED', 'badge text-bg-danger', 1, 1, 3),
(4, 'J2COMMERCE_PENDING', 'badge text-bg-warning', 1, 1, 4),
(5, 'J2COMMERCE_NEW', 'badge text-bg-warning', 1, 1, 5),
(6, 'J2COMMERCE_CANCELLED', 'badge text-bg-secondary', 1, 1, 6),
(7, 'J2COMMERCE_SHIPPED', 'badge text-bg-success', 1, 1, 7),
(8, 'J2COMMERCE_DELIVERED', 'badge text-bg-primary', 1, 1, 8);

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_ordertaxes`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_ordertaxes` (
  `j2commerce_ordertax_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `ordertax_title` varchar(255) NOT NULL,
  `ordertax_percent` decimal(15,5) NOT NULL,
  `ordertax_amount` decimal(15,5) NOT NULL,
  PRIMARY KEY (`j2commerce_ordertax_id`),
  KEY `ordertax_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_paymentprofiles`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_paymentprofiles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'authorizenet',
  `customer_profile_id` varchar(50) NOT NULL,
  `environment` varchar(10) NOT NULL DEFAULT 'production',
  `is_default` tinyint unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_customer_profile_id` (`customer_profile_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_productfiles`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_productfiles` (
  `j2commerce_productfile_id` int NOT NULL AUTO_INCREMENT,
  `product_file_display_name` varchar(255) NOT NULL,
  `product_file_save_name` varchar(255) NOT NULL,
  `product_id` int NOT NULL,
  `download_total` int NOT NULL,
  PRIMARY KEY (`j2commerce_productfile_id`),
  KEY `productfile_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_product_filters`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_product_filters` (
  `product_id` int NOT NULL,
  `filter_id` int NOT NULL,
  PRIMARY KEY (`product_id`,`filter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_productimages`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_productimages` (
  `j2commerce_productimage_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `main_image` text,
  `main_image_alt` varchar(255) NOT NULL,
  `thumb_image` text,
  `thumb_image_alt` varchar(255) NOT NULL,
  `tiny_image` text DEFAULT NULL,
  `tiny_image_alt` varchar(255) NOT NULL DEFAULT '',
  `additional_images` longtext,
  `additional_images_alt` longtext,
  `additional_thumb_images` longtext DEFAULT NULL,
  `additional_thumb_images_alt` longtext DEFAULT NULL,
  `additional_tiny_images` longtext DEFAULT NULL,
  `additional_tiny_images_alt` longtext DEFAULT NULL,
  PRIMARY KEY (`j2commerce_productimage_id`),
  KEY `productimage_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_productquantities`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_productquantities` (
  `j2commerce_productquantity_id` int NOT NULL AUTO_INCREMENT,
  `product_attributes` text NOT NULL COMMENT 'A CSV of productattributeoption_id values, always in numerical order',
  `variant_id` int NOT NULL,
  `quantity` int NOT NULL,
  `on_hold` int NOT NULL,
  `sold` int NOT NULL,
  PRIMARY KEY (`j2commerce_productquantity_id`),
  UNIQUE KEY `variantidx` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_products`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_products` (
  `j2commerce_product_id` int NOT NULL AUTO_INCREMENT,
  `visibility` int NOT NULL,
  `product_source` varchar(255) DEFAULT NULL,
  `product_source_id` int DEFAULT NULL,
  `product_type` varchar(255) DEFAULT NULL,
  `main_tag` varchar(255) DEFAULT NULL,
  `taxprofile_id` int DEFAULT NULL,
  `manufacturer_id` int DEFAULT NULL,
  `vendor_id` int DEFAULT NULL,
  `has_options` int DEFAULT NULL,
  `addtocart_text` varchar(255) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `plugins` text,
  `params` text,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int DEFAULT NULL,
  `up_sells` varchar(255) NOT NULL,
  `cross_sells` varchar(255) NOT NULL,
  `productfilter_ids` varchar(255) DEFAULT NULL,
  `hits` int unsigned NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_product_id`),
  UNIQUE KEY `catalogsource` (`product_source`,`product_source_id`),
  KEY `idx_hits` (`hits`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_product_options`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_product_options` (
  `j2commerce_productoption_id` int NOT NULL AUTO_INCREMENT,
  `option_id` int NOT NULL,
  `parent_id` int NOT NULL DEFAULT 0,
  `product_id` int NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `required` int NOT NULL DEFAULT 0,
  `is_variant` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_productoption_id`),
  KEY `productoption_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_product_optionvalues`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_product_optionvalues` (
  `j2commerce_product_optionvalue_id` int NOT NULL AUTO_INCREMENT,
  `productoption_id` int NOT NULL,
  `optionvalue_id` int DEFAULT NULL,
  `parent_optionvalue` text NOT NULL,
  `product_optionvalue_price` decimal(15,8) NOT NULL,
  `product_optionvalue_prefix` varchar(255) NOT NULL,
  `product_optionvalue_weight` decimal(15,8) NOT NULL,
  `product_optionvalue_weight_prefix` varchar(255) NOT NULL,
  `product_optionvalue_sku` varchar(255) NOT NULL,
  `product_optionvalue_default` int NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `product_optionvalue_attribs` text NOT NULL,
  PRIMARY KEY (`j2commerce_product_optionvalue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_product_prices`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_product_prices` (
  `j2commerce_productprice_id` int NOT NULL AUTO_INCREMENT,
  `variant_id` int DEFAULT NULL,
  `quantity_from` decimal(15,5) DEFAULT NULL,
  `quantity_to` decimal(15,5) DEFAULT NULL,
  `date_from` datetime DEFAULT NULL,
  `date_to` datetime DEFAULT NULL,
  `customer_group_id` int DEFAULT NULL,
  `price` decimal(15,5) DEFAULT NULL,
  `params` text,
  PRIMARY KEY (`j2commerce_productprice_id`),
  KEY `price_variant_id` (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_productprice_index`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_productprice_index` (
  `product_id` int NOT NULL,
  `min_price` decimal(15,5) NOT NULL DEFAULT 0.00000,
  `max_price` decimal(15,5) NOT NULL DEFAULT 0.00000,
  PRIMARY KEY (`product_id`),
  KEY `min_price` (`min_price`),
  KEY `max_price` (`max_price`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_product_variant_optionvalues`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_product_variant_optionvalues` (
  `variant_id` int NOT NULL,
  `product_optionvalue_ids` varchar(255) NOT NULL,
  PRIMARY KEY (`variant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_queues`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_queues` (
  `j2commerce_queue_id` int NOT NULL AUTO_INCREMENT,
  `relation_id` varchar(255) NOT NULL,
  `queue_type` varchar(100) NOT NULL COMMENT 'Plugin identifier: shipstation, quickbooks, avalara',
  `item_type` varchar(50) NOT NULL DEFAULT 'order' COMMENT 'order, user, product, etc.',
  `queue_data` mediumtext NOT NULL COMMENT 'JSON payload for the processor',
  `params` mediumtext DEFAULT NULL COMMENT 'Additional parameters',
  `priority` tinyint NOT NULL DEFAULT 0 COMMENT 'Higher = processed first',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, processing, completed, failed, dead',
  `error_message` text DEFAULT NULL COMMENT 'Last error message',
  `attempt_count` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Number of processing attempts',
  `max_attempts` smallint unsigned NOT NULL DEFAULT 10 COMMENT 'Max retries before dead-letter',
  `next_attempt_at` datetime DEFAULT NULL COMMENT 'Earliest time for next processing attempt',
  `locked_at` datetime DEFAULT NULL COMMENT 'Claim lock timestamp for concurrent protection',
  `locked_by` varchar(64) DEFAULT NULL COMMENT 'Lock owner identifier (task ID or process ID)',
  `processed_at` datetime DEFAULT NULL COMMENT 'When processing completed or failed',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`j2commerce_queue_id`),
  KEY `idx_status_next` (`status`, `next_attempt_at`),
  KEY `idx_queue_type` (`queue_type`),
  KEY `idx_item_type` (`item_type`),
  KEY `idx_relation` (`relation_id`(50)),
  KEY `idx_locked` (`locked_at`),
  KEY `idx_priority` (`priority` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_queue_logs`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_queue_logs` (
  `j2commerce_queue_log_id` int unsigned NOT NULL AUTO_INCREMENT,
  `queue_type` varchar(100) NOT NULL COMMENT 'Which queue was processed',
  `task_id` int unsigned DEFAULT NULL COMMENT 'Joomla scheduler task ID, if applicable',
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL COMMENT 'Execution duration in milliseconds',
  `items_total` smallint unsigned NOT NULL DEFAULT 0,
  `items_success` smallint unsigned NOT NULL DEFAULT 0,
  `items_failed` smallint unsigned NOT NULL DEFAULT 0,
  `items_skipped` smallint unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'running' COMMENT 'running, completed, error',
  `error_message` text DEFAULT NULL,
  `details` mediumtext DEFAULT NULL COMMENT 'JSON array of per-item results',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`j2commerce_queue_log_id`),
  KEY `idx_queue_type` (`queue_type`),
  KEY `idx_status` (`status`),
  KEY `idx_started_at` (`started_at`),
  KEY `idx_created_on` (`created_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_shippingmethods`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_shippingmethods` (
  `j2commerce_shippingmethod_id` int NOT NULL AUTO_INCREMENT,
  `shipping_method_name` varchar(255) NOT NULL,
  `published` tinyint NOT NULL,
  `shipping_method_type` tinyint NOT NULL,
  `tax_class_id` int NOT NULL,
  `address_override` varchar(255) NOT NULL,
  `subtotal_minimum` decimal(15,3) NOT NULL,
  `subtotal_maximum` decimal(15,3) NOT NULL,
  PRIMARY KEY (`j2commerce_shippingmethod_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_shippingrates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_shippingrates` (
  `j2commerce_shippingrate_id` int NOT NULL AUTO_INCREMENT,
  `shipping_method_id` int NOT NULL,
  `geozone_id` int NOT NULL,
  `shipping_rate_price` decimal(15,5) NOT NULL,
  `shipping_rate_weight_start` decimal(11,3) NOT NULL,
  `shipping_rate_weight_end` decimal(11,3) NOT NULL,
  `shipping_rate_handling` decimal(15,5) NOT NULL,
  `created_date` datetime NOT NULL COMMENT 'GMT Only',
  `modified_date` datetime NOT NULL COMMENT 'GMT Only',
  PRIMARY KEY (`j2commerce_shippingrate_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_taxprofiles`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_taxprofiles` (
  `j2commerce_taxprofile_id` int NOT NULL AUTO_INCREMENT,
  `taxprofile_name` varchar(255) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_taxprofile_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_taxrates`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_taxrates` (
  `j2commerce_taxrate_id` int NOT NULL AUTO_INCREMENT,
  `geozone_id` int NOT NULL,
  `taxrate_name` varchar(255) NOT NULL,
  `tax_percent` decimal(11,3) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_taxrate_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_taxrules`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_taxrules` (
  `j2commerce_taxrule_id` int NOT NULL AUTO_INCREMENT,
  `taxprofile_id` int NOT NULL,
  `taxrate_id` int NOT NULL,
  `address` varchar(255) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_taxrule_id`),
  UNIQUE KEY `taxrule_unique` (`taxprofile_id`, `taxrate_id`, `address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_uploads`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_uploads` (
  `j2commerce_upload_id` int NOT NULL AUTO_INCREMENT,
  `original_name` varchar(255) NOT NULL,
  `mangled_name` varchar(255) NOT NULL,
  `saved_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) NOT NULL,
  `created_by` int NOT NULL,
  `created_on` datetime NOT NULL,
  PRIMARY KEY (`j2commerce_upload_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_variants`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_variants` (
  `j2commerce_variant_id` int NOT NULL AUTO_INCREMENT,
  `product_id` int DEFAULT NULL,
  `is_master` int DEFAULT NULL,
  `sku` varchar(255) DEFAULT NULL,
  `upc` varchar(255) DEFAULT NULL,
  `price` decimal(15,5) DEFAULT NULL COMMENT 'Regular price of the product',
  `pricing_calculator` varchar(255) NOT NULL,
  `shipping` int NOT NULL,
  `params` text,
  `length` decimal(15,5) DEFAULT NULL,
  `width` decimal(15,5) DEFAULT NULL,
  `height` decimal(15,5) DEFAULT NULL,
  `length_class_id` int DEFAULT NULL,
  `weight` decimal(15,5) DEFAULT NULL,
  `weight_class_id` int DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int DEFAULT NULL,
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int DEFAULT NULL,
  `manage_stock` int DEFAULT NULL,
  `quantity_restriction` int NOT NULL,
  `min_out_qty` decimal(15,5) DEFAULT NULL,
  `use_store_config_min_out_qty` int DEFAULT NULL,
  `min_sale_qty` decimal(15,5) DEFAULT NULL,
  `use_store_config_min_sale_qty` int DEFAULT NULL,
  `max_sale_qty` decimal(15,5) DEFAULT NULL,
  `use_store_config_max_sale_qty` int DEFAULT NULL,
  `notify_qty` decimal(15,5) DEFAULT NULL,
  `use_store_config_notify_qty` int DEFAULT NULL,
  `availability` int DEFAULT NULL,
  `sold` decimal(12,4) DEFAULT NULL,
  `allow_backorder` int NOT NULL,
  `isdefault_variant` int NOT NULL,
  PRIMARY KEY (`j2commerce_variant_id`),
  KEY `variant_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_vendors`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_vendors` (
  `j2commerce_vendor_id` int NOT NULL AUTO_INCREMENT,
  `j2commerce_user_id` int NOT NULL,
  `address_id` int NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_vendor_id`),
  UNIQUE KEY `j2commerce_user_id` (`j2commerce_user_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_vouchers`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_vouchers` (
  `j2commerce_voucher_id` int NOT NULL AUTO_INCREMENT,
  `order_id` varchar(255) NOT NULL,
  `email_to` varchar(255) NOT NULL,
  `voucher_code` varchar(255) NOT NULL,
  `voucher_type` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `email_body` longtext NOT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_to` datetime DEFAULT NULL,
  `from_order_id` varchar(255) NOT NULL DEFAULT '0',
  `voucher_value` decimal(15,8) NOT NULL,
  `ordering` int NOT NULL DEFAULT 0,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_voucher_id`),
  UNIQUE KEY `voucher_code` (`voucher_code`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_weights`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_weights` (
  `j2commerce_weight_id` int NOT NULL AUTO_INCREMENT,
  `weight_title` varchar(255) NOT NULL,
  `weight_unit` varchar(4) NOT NULL,
  `weight_value` decimal(15,8) NOT NULL,
  `num_decimals` int NOT NULL DEFAULT 2,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_weight_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for table `#__j2commerce_zones`
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `#__j2commerce_zones` (
  `j2commerce_zone_id` int NOT NULL AUTO_INCREMENT,
  `country_id` int NOT NULL,
  `zone_code` varchar(255) NOT NULL,
  `zone_name` varchar(255) NOT NULL,
  `enabled` tinyint NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `access` int UNSIGNED NOT NULL DEFAULT '0',
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` int UNSIGNED NOT NULL DEFAULT '0',
  `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified_by` int UNSIGNED NOT NULL DEFAULT '0',
  `checked_out` int UNSIGNED DEFAULT NULL,
  `checked_out_time` datetime DEFAULT NULL,
  PRIMARY KEY (`j2commerce_zone_id`),
  KEY `idx_access` (`access`),
  KEY `idx_checkout` (`checked_out`),
  KEY `idx_createdby` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- End of J2Commerce install script
