-- Widen paymentprofiles.payment_token for gateway token hrefs that exceed 100 chars (e.g. Worldpay verified-token URLs ~117 chars). See issue #1103.
UPDATE `#__j2commerce_paymentprofiles`
    SET `payment_token` = ''
    WHERE `payment_token` IS NULL;

ALTER TABLE `#__j2commerce_paymentprofiles` ROW_FORMAT=DYNAMIC /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_paymentprofiles`
    MODIFY `payment_token` VARCHAR(255) NOT NULL DEFAULT '';
