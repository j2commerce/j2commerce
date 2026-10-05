-- Filter group value sources. `source` names who owns a group's values: 'j2commerce' (entered
-- by hand, the default) or a key a plugin registers through onJ2CommerceGetFilterSources.
-- `source_params` holds that plugin's own settings; `source_ref` is a value's identity in the
-- source system, so a plugin can rename or remove the row it wrote without touching others.
ALTER TABLE `#__j2commerce_filtergroups` ADD COLUMN `source` varchar(50) NOT NULL DEFAULT 'j2commerce';
ALTER TABLE `#__j2commerce_filtergroups` ADD COLUMN `source_params` text;
ALTER TABLE `#__j2commerce_filters` ADD COLUMN `source_ref` varchar(100) NOT NULL DEFAULT '';
ALTER TABLE `#__j2commerce_filters` ADD KEY `idx_group_source_ref` (`group_id`, `source_ref`);
-- Product links are replaced by filter value (a group rebuild); the primary key leads with product_id.
ALTER TABLE `#__j2commerce_product_filters` ADD KEY `idx_filter_id` (`filter_id`);
