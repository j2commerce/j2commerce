-- Add Iran province "Alborz" (split from Tehran province in 2010), missing from initial zone seed data. See issue #1176.
INSERT IGNORE INTO `#__j2commerce_zones` (`j2commerce_zone_id`, `country_id`, `zone_code`, `zone_name`, `enabled`, `ordering`) VALUES
(4016, 101, 'ALB', 'Alborz', 1, 0);
