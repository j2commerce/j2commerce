-- Per-group input type for the product-filter sidebar.
--
-- Every group rendered as a checkbox list, which stops being usable once a group carries
-- dozens of values. The group now names the control it wants; 'checkbox' is the default, so
-- existing stores render exactly as before until an owner changes a group.
--
-- filter_color is the per-value swatch colour, read only by the 'color' input type.
--
-- Fresh installs already have these columns; the ALTERs no-op on those sites.

ALTER TABLE `#__j2commerce_filtergroups`
    ADD COLUMN `filter_input_type` varchar(20) NOT NULL DEFAULT 'checkbox' AFTER `group_name` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_filters`
    ADD COLUMN `filter_color` varchar(20) NOT NULL DEFAULT '' AFTER `filter_name` /** CAN FAIL **/;
