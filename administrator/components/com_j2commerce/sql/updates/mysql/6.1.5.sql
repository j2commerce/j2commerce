-- Fix coupon date columns: allow NULL instead of storing 0000-00-00 00:00:00
ALTER TABLE `#__j2commerce_coupons`
    MODIFY `valid_from` datetime DEFAULT NULL/** CAN FAIL **/;

ALTER TABLE `#__j2commerce_coupons`
    MODIFY `valid_to` datetime DEFAULT NULL/** CAN FAIL **/;

-- Clean up existing zero-date records
UPDATE `#__j2commerce_coupons`
SET `valid_from` = NULL
WHERE `valid_from` = '0000-00-00 00:00:00'/** CAN FAIL **/;

UPDATE `#__j2commerce_coupons`
SET `valid_to` = NULL
WHERE `valid_to` = '0000-00-00 00:00:00'/** CAN FAIL **/;
