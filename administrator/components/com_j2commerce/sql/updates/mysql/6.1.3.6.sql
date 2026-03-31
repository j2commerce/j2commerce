-- Fix checkout field ordering: email first, then name, address, city, zip,
-- country, zone, phone, company, tax number.
-- Default is US order (address_1=4, address_2=5). Non-US stores get swapped
-- during onboarding via OnboardingHelper::reorderAddressFields().
UPDATE `#__j2commerce_customfields` SET `ordering` = 1 WHERE `field_namekey` = 'email' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 2 WHERE `field_namekey` = 'first_name' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 3 WHERE `field_namekey` = 'last_name' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 4 WHERE `field_namekey` = 'address_1' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 5 WHERE `field_namekey` = 'address_2' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 6 WHERE `field_namekey` = 'city' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 7 WHERE `field_namekey` = 'zip' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 8 WHERE `field_namekey` = 'country_id' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 9 WHERE `field_namekey` = 'zone_id' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 10 WHERE `field_namekey` = 'phone_1' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 11 WHERE `field_namekey` = 'phone_2' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 12 WHERE `field_namekey` = 'company' AND `field_core` = 1;
UPDATE `#__j2commerce_customfields` SET `ordering` = 13 WHERE `field_namekey` = 'tax_number' AND `field_core` = 1;
