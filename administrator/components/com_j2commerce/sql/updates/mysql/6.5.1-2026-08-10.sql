-- One-off cleanup of filter rows left behind by earlier group and value handling.
--
-- Neither `#__j2commerce_filters` nor `#__j2commerce_product_filters` carries a foreign key,
-- so rows whose parent has gone stayed in place and accumulated as auto-increment advanced.
-- The delete path now removes them with the group, but existing stores still hold whatever
-- earlier deletes left behind.
--
-- Values first, then pairs: that order also clears the pairs the first statement orphans,
-- and the pairs left over from the pre-reconciliation save behaviour.
--
-- Both statements are no-ops on a store with nothing orphaned.

DELETE `f` FROM `#__j2commerce_filters` AS `f`
    LEFT JOIN `#__j2commerce_filtergroups` AS `g`
        ON `g`.`j2commerce_filtergroup_id` = `f`.`group_id`
    WHERE `g`.`j2commerce_filtergroup_id` IS NULL;

DELETE `pf` FROM `#__j2commerce_product_filters` AS `pf`
    LEFT JOIN `#__j2commerce_filters` AS `f`
        ON `f`.`j2commerce_filter_id` = `pf`.`filter_id`
    WHERE `f`.`j2commerce_filter_id` IS NULL;
