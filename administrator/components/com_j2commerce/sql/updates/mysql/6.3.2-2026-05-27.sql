-- Widen paymentprofiles.payment_token for gateway token hrefs that exceed 100 chars (e.g. Worldpay verified-token URLs ~117 chars). See issue #1103.
UPDATE `#__j2commerce_paymentprofiles`
    SET `payment_token` = ''
    WHERE `payment_token` IS NULL;

ALTER TABLE `#__j2commerce_paymentprofiles`
    DROP INDEX `uq_user_provider_env_token` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_paymentprofiles`
    MODIFY `payment_token` VARCHAR(255) NOT NULL DEFAULT '';
ALTER TABLE `#__j2commerce_paymentprofiles`
    ADD UNIQUE KEY `uq_user_provider_env_token` (`user_id`,`provider`,`environment`,`payment_token`) /** CAN FAIL **/;
