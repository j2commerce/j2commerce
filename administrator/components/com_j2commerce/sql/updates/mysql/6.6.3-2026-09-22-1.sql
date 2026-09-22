-- Variant-defining option checks join product_optionvalues on productoption_id inside a correlated
-- EXISTS (OrderModel::variantOptionCondition(), ProductHelper::whereOptionOffered()).
ALTER TABLE `#__j2commerce_product_optionvalues` ADD KEY `idx_productoption_id` (`productoption_id`) /** CAN FAIL **/;
