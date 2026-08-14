-- The Stock Status select (v.availability) only ever reached the sellability checks for
-- variants with manage_stock = 1, so for every other variant the column was written but
-- never read. A 0 sitting on one of those rows is therefore an artefact of the old
-- VariantTable::check() default, which stamped 0 on any save that left the value unset --
-- it cannot represent a choice the owner made, because the choice had no effect. Clear
-- them before the column becomes authoritative, so upgrading does not silently pull those
-- products off sale.
UPDATE `#__j2commerce_variants` SET `availability` = 1
 WHERE `availability` = 0 AND (`manage_stock` IS NULL OR `manage_stock` <> 1);
