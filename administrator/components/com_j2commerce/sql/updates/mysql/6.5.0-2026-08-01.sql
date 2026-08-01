-- Record whether an order holds deducted stock, instead of inferring it from its status.
--
-- Inventory was moved on a status transition by asking whether the previous and next
-- statuses were "stock holding". That inference is only ever right for orders created
-- under the rule currently in force, and the rule has changed three times: reserving at
-- Confirmed only, then at Confirmed and Pending, then at everything except Failed, New
-- and Cancelled. Orders created under an earlier rule are misread by a later one, which
-- has produced both phantom credits and missing debits.
--
-- stock_committed records what actually happened, so every path becomes idempotent
-- regardless of how the order reached its current status.
--
-- Fresh installs already have this column; the ALTER no-ops on those sites.

ALTER TABLE `#__j2commerce_orders`
    ADD COLUMN `stock_committed` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether this order currently holds deducted stock' AFTER `order_state_id` /** CAN FAIL **/;

-- Existing rows are seeded from the installer script's postflight, not from here. Joomla's
-- schema-repair path only builds check queries for RENAME/ALTER/CREATE, so an UPDATE in a
-- delta file is skipped by Database -> Fix while the schema version is still advanced past
-- it. A seed lost that way would leave already-deducted orders marked uncommitted, and the
-- next status change would deduct them a second time.
