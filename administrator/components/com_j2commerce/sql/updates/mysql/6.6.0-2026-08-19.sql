-- `order_state` is a denormalised copy of the status name that duplicates `order_state_id`
-- and its join to `#__j2commerce_orderstatuses`. Core no longer reads or writes it, but the
-- column is NOT NULL with no default, so an INSERT that simply omits it fails under
-- STRICT_TRANS_TABLES -- which Joomla sets on every connection. Give it a default so the
-- column can stand on its own now that nothing supplies a value. It stays in the schema for
-- extensions that still select it; dropping it is not proposed.
ALTER TABLE `#__j2commerce_orders` MODIFY `order_state` varchar(255) NOT NULL DEFAULT '';
