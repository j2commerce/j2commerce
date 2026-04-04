-- Guided Tours
-- @package     J2Commerce
-- @subpackage  com_j2commerce
-- @copyright   (C)2024-2026 J2Commerce, LLC <https://www.j2commerce.com>
-- @license     GNU General Public License version 2 or later; see LICENSE.txt
--
-- Note: Using INSERT IGNORE to prevent errors on component reinstall

-- cleanup to avoid issues with duplicate entries when installing the same version multiple times

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'com_j2commerce.creating-product');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'com_j2commerce.managing-countries');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtours` (`title`, `uid`, `description`, `extensions`, `url`, `published`, `access`, `created`, `modified`, `language`, `ordering`, `note`, `autostart`) VALUES
('COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_TITLE', 'com_j2commerce.creating-product', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_DESC', '["com_j2commerce"]', 'administrator/index.php?option=com_j2commerce&view=products', 1, 1, NOW(), NOW(), '*', 1, '', 0),
('COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_TITLE', 'com_j2commerce.managing-countries', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_DESC', '["com_j2commerce"]', 'administrator/index.php?option=com_j2commerce&view=countries', 1, 1, NOW(), NOW(), '*', 2, '', 0);
-- ('COM_J2COMMERCE_GUIDEDTOUR_SETTING_UP_PAYMENTS_TITLE', 'com_j2commerce.setting-up-payments', 'COM_J2COMMERCE_GUIDEDTOUR_SETTING_UP_PAYMENTS_DESC', '["com_j2commerce"]', 'administrator/index.php?option=com_j2commerce&view=apps&folder=payment', 1, 1, NOW(), NOW(), '*', 3, '', 0),
-- ('COM_J2COMMERCE_GUIDEDTOUR_CONFIGURING_SHIPPING_TITLE', 'com_j2commerce.configuring-shipping', 'COM_J2COMMERCE_GUIDEDTOUR_CONFIGURING_SHIPPING_DESC', '["com_j2commerce"]', 'administrator/index.php?option=com_j2commerce&view=shippingmethods', 1, 1, NOW(), NOW(), '*', 4, '', 0);

-- Guided Tour Steps: Creating Your First Product (18 steps)

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP0_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP0_DESC', 'bottom', '#toolbar-new', 2, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP1_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP1_DESC', 'bottom', '#jform_title', 2, 2, 'administrator/index.php?option=com_content&view=article&layout=edit', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP2_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP2_DESC', 'bottom', 'button[aria-controls="attrib-j2commerce"]', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP3_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP3_DESC', 'top', '#j2commerce-product-enabled-radio-group0', 2, 5, '', 1, '*', '', '{"required":1,"requiredvalue":"1"}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP4_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP4_DESC', 'top', '#product_type', 2, 6, '', 1, '*', '', '{"required":1,"requiredvalue":"simple"}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP5_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP5_DESC', 'bottom', '#submit_button', 2, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP6_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP6_DESC', 'bottom', 'button[aria-controls="attrib-j2commerce"] span', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP7_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP7_DESC', 'bottom', '#j2commerce-product-visibility-radio-group0', 2, 5, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP8_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP8_DESC', 'bottom', '#j2commerce-product-sku-group', 2, 2, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP9_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP9_DESC', 'top', 'joomla-field-fancy-select:has(#j2commerce-product-taxprofile_id-select-group)', 2, 6, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP10_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP10_DESC', 'top', 'button[aria-controls="pricingTab"]', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP11_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP11_DESC', 'bottom', '#j2commerce-product-price-field', 2, 2, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP12_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP12_DESC', 'top', 'button[aria-controls="inventoryTab"]', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP13_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP13_DESC', 'bottom', '#j2commerce-product-manage_stock-radio-group1', 2, 5, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP14_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP14_DESC', 'top', 'button[aria-controls="shippingTab"]', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP15_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP15_DESC', 'bottom', '#j2commerce-product-shipping-radio-group1', 2, 5, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP16_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP16_DESC', 'bottom', '#save-group-children-save .button-save', 2, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP17_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_CREATING_PRODUCT_STEP17_DESC', 'center', '', 0, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.creating-product';

-- Guided Tour Steps: Managing Countries

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP0_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP0_DESC', 'bottom', '#filter_search', 2, 2, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP1_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP1_DESC', 'bottom', 'td:has(a[data-item-id="cb0"])', 2, 5, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP2_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP2_DESC', 'bottom', 'td:has(input[name="checkall-toggle"])', 2, 5, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP3_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP3_DESC', 'right', '#toolbar-status-group', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP4_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP4_DESC', 'bottom', '#status-group-children-unpublish', 2, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT IGNORE INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP5_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP5_DESC', 'center', '', 0, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';
