-- Widen currency_thousands to varchar so a stored "Space" separator survives MySQL's char(1) trailing-space trim on read.
ALTER TABLE `#__j2commerce_currencies`
    MODIFY `currency_thousands` VARCHAR(12) NOT NULL DEFAULT ',';
