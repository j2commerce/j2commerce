UPDATE `#__j2commerce_orders` SET `order_subtotal_ex_tax` = `order_subtotal` - `order_tax` WHERE `is_including_tax` = 1 AND `order_tax` > 0 AND `order_subtotal_ex_tax` = `order_subtotal`;
