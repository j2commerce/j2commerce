ALTER TABLE `#__j2commerce_orderitems` ADD INDEX `idx_orderitem_variant_id` (`variant_id`) /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_variants` ADD INDEX `idx_variant_sku` (`sku`) /** CAN FAIL **/;
