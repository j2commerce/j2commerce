-- Enable "Display Behind Add Link" on the Address Line 2 custom field by default (issue #1197).
-- Only sets it when the collapse option is not already present, so admin customisations are preserved.
UPDATE `#__j2commerce_customfields`
SET `field_options` = '{"field_collapse_toggle":1}'
WHERE `field_namekey` = 'address_2'
  AND (`field_options` IS NULL OR `field_options` = '' OR `field_options` NOT LIKE '%field_collapse_toggle%');
