-- The seventeen transactional rows this table was installed with duplicated a list the component
-- already derives: EmailTypeRegistry::getCoreTags() now builds the transactional vocabulary from
-- MessageHelper, which is the same set EmailHelper substitutes. The seeded rows named fifteen tags
-- that appear in no substitution map under any spelling. Of the two that do resolve, CUSTOMER_NAME
-- is already in the derived list, and SHIPPING_METHOD is the derived list's SHIPPING_TYPE under a
-- second name -- both keep substituting either way. The install SQL no longer seeds any of them.
--
-- Scoped to 'transactional' and to those seventeen names: rows a plugin registered for its own
-- email type, and any other tag a merchant added, are untouched. DELETE carries no check query, so
-- Extensions -> Manage -> Database reports this file as skipped rather than as a mismatch.

DELETE FROM `#__j2commerce_emailtype_tags`
  WHERE `email_type` = 'transactional'
  AND `tag_name` IN (
    'ORDER_ID', 'ORDER_DATE', 'ORDER_STATUS', 'ORDER_TOTAL', 'ORDER_SUBTOTAL',
    'ORDER_TAX', 'ORDER_SHIPPING', 'ORDER_DISCOUNT', 'ORDER_ITEMS',
    'CUSTOMER_NAME', 'CUSTOMER_EMAIL', 'BILLING_ADDRESS', 'SHIPPING_ADDRESS',
    'PAYMENT_METHOD', 'SHIPPING_METHOD', 'SITE_NAME', 'SITE_URL'
  );
