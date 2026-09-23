-- Processed is an authorised payment that has not settled, which is what the 'approved' lifecycle
-- type describes. It was seeded as 'open' alongside Pending, so the two became indistinguishable by
-- type and every test that has to tell them apart fell back to status ids. Guarded on the seeded
-- value so a merchant who classified it deliberately keeps their choice.
UPDATE `#__j2commerce_orderstatuses` SET `orderstatus_type` = 'approved' WHERE `orderstatus_core` = 1 AND `orderstatus_name` = 'J2COMMERCE_PROCESSED' AND `orderstatus_type` = 'open';
