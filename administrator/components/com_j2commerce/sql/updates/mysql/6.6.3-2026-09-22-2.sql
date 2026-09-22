-- Shipping Method condition for email templates, alongside the existing paymentmethod condition.
-- '*' is the wildcard, so existing rows keep matching every shipping method.
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `shippingmethod` varchar(255) NOT NULL DEFAULT '*' /** CAN FAIL **/;
