-- Durable upload throttle.
-- The previous per-hour upload limit lived in the Joomla session, so any visitor
-- could reset the counter simply by starting a new session. client_ip stores a
-- salted SHA-256 throttle key (never a reversible value): the user id for an
-- authenticated uploader, otherwise the requesting address, which lets the limit
-- be counted over the uploads table instead.
--
-- Fresh installs already have this column; the ALTERs no-op on those sites.

ALTER TABLE `#__j2commerce_uploads`
    ADD COLUMN `client_ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'Salted SHA-256 throttle key — u: user id, i: client IP; not reversible' AFTER `file_size` /** CAN FAIL **/;

ALTER TABLE `#__j2commerce_uploads`
    ADD INDEX `idx_client_ip` (`client_ip`, `created_on`) /** CAN FAIL **/;
