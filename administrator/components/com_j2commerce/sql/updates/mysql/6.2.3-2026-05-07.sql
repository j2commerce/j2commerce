-- remove the configurations table, no longer used

DROP TABLE IF EXISTS `#__j2commerce_configurations`;

-- add Iranian Rial currency if missing, positioned after all existing currencies
INSERT IGNORE INTO `#__j2commerce_currencies`
  (`currency_title`, `currency_code`, `currency_position`, `currency_symbol`, `currency_num_decimals`, `currency_decimal`, `currency_thousands`, `currency_value`, `enabled`, `ordering`)
SELECT 'Iranian Rial', 'IRR', 'pre', '﷼', 0, '.', ',', 1.00000000, 1, COALESCE(MAX(`ordering`), 48) + 1
FROM `#__j2commerce_currencies`
WHERE `currency_code` != 'IRR';

