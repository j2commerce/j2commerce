-- The install seed classified J2COMMERCE_CONFIRMED as 'open', but PayPalOrderStates::CONFIRMED
-- already resolves that outcome to OrderStatusHelper::TYPE_COMPLETE by name -- the seed and the
-- resolver disagreed on what the row means. Bring the seed in line with what the resolver has
-- always expected.
--
-- Scoped to the 'open' value the earlier delta 6.6.1-2026-09-04-2.sql backfilled: a merchant who
-- has since chosen their own classification for this row is never overwritten, and a second run
-- is a no-op. A bare UPDATE carries no check query, so Extensions -> Manage -> Database reports
-- this file as skipped rather than as a mismatch.

UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'complete' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_CONFIRMED' AND `orderstatus_type` = 'open';
