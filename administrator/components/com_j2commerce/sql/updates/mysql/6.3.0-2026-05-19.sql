-- Issue #1065 — Consistency in display of file and image uploads
-- Adds attribute_type column to #__j2commerce_uploads so admin order list view
-- can render distinct icons for file-type vs image-type custom-option uploads.
--
-- Without this column the list view can only show a single generic indicator —
-- mime_type is the wrong proxy (a file-type option may hold an image-mime file).

ALTER TABLE `#__j2commerce_uploads`
    ADD COLUMN `attribute_type` ENUM('file','image') NOT NULL DEFAULT 'file' AFTER `status`,
    ADD INDEX `idx_attribute_type` (`attribute_type`);
