-- Add valid_to to vouchers table
ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `valid_to` datetime DEFAULT NULL AFTER `email_body`/** CAN FAIL **/;

-- Add valid_from to vouchers table
ALTER TABLE `#__j2commerce_vouchers`
    ADD COLUMN `valid_from` datetime DEFAULT NULL AFTER `email_body`/** CAN FAIL **/;
