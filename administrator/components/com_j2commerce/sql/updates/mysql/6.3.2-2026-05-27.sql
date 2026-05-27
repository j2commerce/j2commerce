-- Widen paymentprofiles.payment_token for gateway token hrefs that exceed 100 chars (e.g. Worldpay verified-token URLs ~117 chars). See issue #1103.
ALTER TABLE `#__j2commerce_paymentprofiles`
    MODIFY COLUMN `payment_token` varchar(255) NOT NULL DEFAULT '';
