-- from_order_id is in the install schema of these tables; sites whose tables predate it get it here.
ALTER TABLE `#__j2commerce_orders` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_orderitems` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_orderinfos` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_orderhistories` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_ordertaxes` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_orderdiscounts` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_orderdownloads` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `order_id` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_vouchers` ADD COLUMN `from_order_id` varchar(255) NOT NULL DEFAULT '0' AFTER `valid_to` /** CAN FAIL **/;
