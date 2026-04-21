UPDATE `#__j2commerce_paymentprofiles`
    SET `payment_token` = ''
    WHERE `payment_token` IS NULL;

ALTER TABLE `#__j2commerce_paymentprofiles`
    MODIFY `payment_token` VARCHAR(100) NOT NULL DEFAULT '' /** CAN FAIL **/;
