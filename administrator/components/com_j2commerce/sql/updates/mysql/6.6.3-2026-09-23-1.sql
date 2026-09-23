-- The retention purge selects on `status` AND `processed_at`. `idx_status_next` narrows on its
-- leading `status` column only, so `processed_at` was filtered without index support and the purge
-- examined the whole completed partition on every run. That partition now grows for the length of
-- the configured retention window instead of being emptied at the end of each run.
ALTER TABLE `#__j2commerce_queues` ADD KEY `idx_status_processed` (`status`, `processed_at`);
