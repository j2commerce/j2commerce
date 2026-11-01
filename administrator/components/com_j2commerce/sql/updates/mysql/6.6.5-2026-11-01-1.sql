-- Orders: index the stored invoice number used by the invoice range filter.
ALTER TABLE `#__j2commerce_orders` ADD INDEX `idx_invoice_number` (`invoice_number`) /** CAN FAIL **/;
