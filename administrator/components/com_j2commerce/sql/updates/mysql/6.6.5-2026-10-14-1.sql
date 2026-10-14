ALTER TABLE `#__j2commerce_queues` DROP INDEX `idx_priority` /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_queues` ADD INDEX `idx_queue_priority` (`priority`) /** CAN FAIL **/;
