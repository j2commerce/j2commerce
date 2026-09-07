-- The audit and checkout columns on the two template tables, which until now existed only in
-- install.mysql.utf8.sql. No delta ever added them, so a site created before they entered the
-- install SQL never received them, and Extensions -> Manage -> Database could neither report the
-- gap nor fix it: ChangeSet builds its change items from the deltas on disk, and no delta
-- described these columns.
--
-- The absence is not cosmetic. Both Table::store() overrides set created_on/created_by on insert
-- and modified_on/modified_by on every save, and on an affected site those values go nowhere.
-- Core Table::checkIn() returns true untouched when the two checkout columns are missing, so the
-- Check-in task on both list views reports success and changes nothing. And the core.edit.own
-- branch in both list layouts sits commented out behind a TODO naming created_by.
--
-- Fresh installs already have these columns and are stamped at the newest delta, so they run this
-- file only on their next update; the CAN FAIL markers are what keep that update from aborting.
-- One column per ALTER TABLE, and ADD keeps the COLUMN keyword: MysqlChangeItem reads ADD COLUMN
-- at fixed word offsets 3 and 4, and splitSql() splits on `;` only, so a comma-chained clause list
-- would collapse into a single change item of which only the first clause is ever checked.

ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD COLUMN `checked_out_time` datetime DEFAULT NULL /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_emailtemplates` ADD KEY `idx_access` (`access`) /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD KEY `idx_checkout` (`checked_out`) /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_emailtemplates` ADD KEY `idx_createdby` (`created_by`) /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `access` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `created_by` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `modified_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `modified_by` int UNSIGNED NOT NULL DEFAULT '0' /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `checked_out` int UNSIGNED DEFAULT NULL /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD COLUMN `checked_out_time` datetime DEFAULT NULL /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_invoicetemplates` ADD KEY `idx_access` (`access`) /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD KEY `idx_checkout` (`checked_out`) /** CAN FAIL **/;
ALTER TABLE `#__j2commerce_invoicetemplates` ADD KEY `idx_createdby` (`created_by`) /** CAN FAIL **/;
