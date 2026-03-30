--
-- J2Commerce 6.1.5 Update Script
-- Rename campaign_addr_id to params on addresses table for plugin JSON metadata storage.
--

ALTER TABLE `#__j2commerce_addresses`
  CHANGE `campaign_addr_id` `params` TEXT NULL;
