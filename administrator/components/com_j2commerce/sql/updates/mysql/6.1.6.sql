-- Fix Phone and Mobile custom fields: change field_type from 'text' to 'telephone'
-- so they render with the telephone input (country code selector + flag icons)
UPDATE `#__j2commerce_customfields`
SET `field_type` = 'telephone'
WHERE `field_namekey` IN ('phone_1', 'phone_2')
AND `field_type` = 'text'/** CAN FAIL **/;
