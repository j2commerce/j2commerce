-- uninstall old tour j2commerce-creating-product

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'j2commerce-managing-countries');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'j2commerce-managing-countries';

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'j2commerce-setting-up-payments');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'j2commerce-setting-up-payments';

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'j2commerce-configuring-shipping');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'j2commerce-configuring-shipping';

-- cleanup to avoid issues with duplicate entries when installing the same version multiple times during development

DELETE FROM `#__guidedtour_steps`
WHERE `tour_id` = (SELECT `id` FROM `#__guidedtours` WHERE `uid` = 'com_j2commerce.managing-countries');

DELETE FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

-- Guided Tour: Managing Countries (6 steps)

INSERT INTO `#__guidedtours` (`title`, `description`, `extensions`, `url`, `published`, `language`, `note`, `access`, `uid`, `autostart`, `created`, `created_by`, `modified`, `modified_by`) VALUES
    ('COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_DESC', '["com_j2commerce"]', 'administrator/index.php?option=com_j2commerce&view=countries', 1, '*', '', 1, 'com_j2commerce.managing-countries', 0, CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0);

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_0_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_0_DESC', 'bottom', '#filter_search', 2, 2, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_1_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_1_DESC', 'bottom', 'td:has(a[data-item-id="cb0"])', 2, 5, '', 1, '*', '', '{"required":0,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_2_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_2_DESC', 'bottom', 'td:has(input[name="checkall-toggle"])', 2, 5, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_3_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_3_DESC', 'right', '#toolbar-status-group', 2, 4, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_4_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_4_DESC', 'bottom', '#status-group-children-unpublish', 2, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';

INSERT INTO `#__guidedtour_steps` (`title`, `description`, `position`, `target`, `type`, `interactive_type`, `url`, `published`, `language`, `note`, `params`, `created`, `created_by`, `modified`, `modified_by`, `tour_id`)
SELECT 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_5_TITLE', 'COM_J2COMMERCE_GUIDEDTOUR_MANAGING_COUNTRIES_STEP_5_DESC', 'center', '', 0, 1, '', 1, '*', '', '{"required":1,"requiredvalue":""}', CURRENT_TIMESTAMP(), 0, CURRENT_TIMESTAMP(), 0, MAX(`id`)
FROM `#__guidedtours`
WHERE `uid` = 'com_j2commerce.managing-countries';
