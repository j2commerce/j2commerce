-- Repair variants that hold stock but are flagged unavailable, so they can be sold again.
--
-- availability is a derived flag: InventoryHelper::adjustStockAndAvailability() is the only
-- thing that maintains it, and it runs on the order path alone. Every other route left it at
-- NULL or 0 -- the column defaults to NULL, one of the three variant-creation paths wrote 0,
-- and VariantTable::check() re-stamped 0 on every save. ProductHelper::validateStock() refuses
-- to sell a variant whose availability is falsy, so an in-stock product reported "Only N left
-- in stock" and could never be added to the cart, and re-saving the product re-broke it.
--
-- Sites upgraded from J2Store are hit hardest: nothing in that import populates availability.
--
-- Only rows that are genuinely sellable are touched. A deliberate "in stock but not for sale"
-- is expressed by unpublishing the product or zeroing its quantity, not by this flag.

UPDATE `#__j2commerce_variants` v
    INNER JOIN `#__j2commerce_productquantities` pq ON pq.`variant_id` = v.`j2commerce_variant_id`
    SET v.`availability` = 1
    WHERE COALESCE(v.`availability`, 0) = 0
      AND pq.`quantity` > 0;

-- Variants that do not manage stock never consult quantity, so they have no quantity row to
-- join against and are stranded at NULL by the statement above.

UPDATE `#__j2commerce_variants`
    SET `availability` = 1
    WHERE `availability` IS NULL
      AND COALESCE(`manage_stock`, 0) <> 1;

-- Joomla's schema-repair path (Database -> Fix) only builds check queries for RENAME/ALTER/
-- CREATE, so both UPDATEs above are skipped when a site's schema version is already advanced
-- past this file. The installer script's postflight runs the same repair for that case.
