-- The lock that stops a retry buying a second billable shipping label used to be held by
-- writing a sentinel into `ordershipping_tracking_id`. That column is also the customer's
-- tracking number, so while the lock was held the sentinel was rendered verbatim on the
-- shopper's order page, in shipment email, in the admin and over the API. Give the lock its
-- own column so `ordershipping_tracking_id` only ever contains a real carrier tracking
-- number and no read site has to know the lock exists.
ALTER TABLE `#__j2commerce_ordershippings` ADD COLUMN `ordershipping_label_claim` varchar(64) NOT NULL DEFAULT '' COMMENT 'Internal lock held while a shipping label is being bought. Never rendered as a tracking number.';

-- Move any lock that is still held out of the tracking column. The claim is preserved rather
-- than cleared: a held slot is what prevents a second label being bought, and these rows are
-- exactly the ones where a label may already have been paid for.
UPDATE `#__j2commerce_ordershippings` SET `ordershipping_label_claim` = '__j2c_label_pending__', `ordershipping_tracking_id` = '' WHERE `ordershipping_tracking_id` = '__j2c_label_pending__';
