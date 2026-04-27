--
-- Repair em-dashes that were stored as `???` during the install seed
-- because the installer connection charset was not utf8mb4 when the
-- emailtemplates.sql / invoicetemplates.sql files were executed.
--
-- Source seed has been ASCII-ified to use `-` instead of `—`, but
-- existing installs already contain the corrupted `???` byte sequence.
-- This migration replaces ONLY the exact known corrupted seed strings,
-- so any row a user has already edited (different content) is untouched.
--
-- Tracking: GitHub issue #870
--

-- Email subjects (3 templates)
UPDATE `#__j2commerce_emailtemplates`
   SET `subject` = REPLACE(`subject`,
       'Thanks for your order, [BILLING_FIRSTNAME]! ??? #[ORDERID]',
       'Thanks for your order, [BILLING_FIRSTNAME]! - #[ORDERID]')
 WHERE `subject` LIKE '%Thanks for your order, [BILLING_FIRSTNAME]! ??? #[ORDERID]%';

UPDATE `#__j2commerce_emailtemplates`
   SET `subject` = REPLACE(`subject`,
       'Good news, [BILLING_FIRSTNAME] ??? Your order is on its way!',
       'Good news, [BILLING_FIRSTNAME] - Your order is on its way!')
 WHERE `subject` LIKE '%Good news, [BILLING_FIRSTNAME] ??? Your order is on its way!%';

UPDATE `#__j2commerce_emailtemplates`
   SET `subject` = REPLACE(`subject`,
       'Order #[ORDERID] ??? Cancellation Confirmed',
       'Order #[ORDERID] - Cancellation Confirmed')
 WHERE `subject` LIKE '%Order #[ORDERID] ??? Cancellation Confirmed%';

-- Email bodies (3 HTML comment dividers)
UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Order Confirmed ??? Modern Template -->',
       '<!-- J2Commerce Order Confirmed - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Order Confirmed ??? Modern Template -->%';

UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Shipped Email ??? Modern Template -->',
       '<!-- J2Commerce Shipped Email - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Shipped Email ??? Modern Template -->%';

UPDATE `#__j2commerce_emailtemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Cancelled Order Email ??? Modern Template -->',
       '<!-- J2Commerce Cancelled Order Email - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Cancelled Order Email ??? Modern Template -->%';

-- Invoice bodies (4 HTML comment dividers)
UPDATE `#__j2commerce_invoicetemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Packing Slip ??? Modern Template -->',
       '<!-- J2Commerce Packing Slip - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Packing Slip ??? Modern Template -->%';

UPDATE `#__j2commerce_invoicetemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Invoice ??? Modern Template -->',
       '<!-- J2Commerce Invoice - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Invoice ??? Modern Template -->%';

UPDATE `#__j2commerce_invoicetemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Receipt ??? Thermal / POS Template -->',
       '<!-- J2Commerce Receipt - Thermal / POS Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Receipt ??? Thermal / POS Template -->%';

UPDATE `#__j2commerce_invoicetemplates`
   SET `body` = REPLACE(`body`,
       '<!-- J2Commerce Receipt ??? Modern Template -->',
       '<!-- J2Commerce Receipt - Modern Template -->')
 WHERE `body` LIKE '%<!-- J2Commerce Receipt ??? Modern Template -->%';
