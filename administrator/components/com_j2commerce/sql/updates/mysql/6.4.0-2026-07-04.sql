-- Shared order-transaction ledger (core). See docs/plans/order_transactions_ledger_prd.md.
CREATE TABLE IF NOT EXISTS `#__j2commerce_ordertransactions` (
  `j2commerce_ordertransaction_id` INT NOT NULL AUTO_INCREMENT,
  `order_id`        INT            NOT NULL,
  `plugin`          VARCHAR(255)   NOT NULL DEFAULT '',
  `type`            VARCHAR(20)    NOT NULL DEFAULT 'DEBIT',
  `gateway_txn_id`  VARCHAR(255)   NULL DEFAULT NULL,
  `parent_txn_id`   VARCHAR(255)   NOT NULL DEFAULT '',
  `amount`          DECIMAL(15,5)  NOT NULL DEFAULT 0,
  `currency_code`   VARCHAR(255)   NOT NULL DEFAULT '',
  `state`           VARCHAR(20)    NOT NULL DEFAULT 'succeeded',
  `created_at`      DATETIME       NOT NULL,
  `created_by`      INT            NOT NULL DEFAULT 0,
  PRIMARY KEY (`j2commerce_ordertransaction_id`),
  UNIQUE KEY `uq_order_txn_type` (`order_id`, `gateway_txn_id`, `type`),
  KEY `idx_order` (`order_id`),
  KEY `idx_order_type_state` (`order_id`, `type`, `state`),
  KEY `idx_gateway_txn` (`gateway_txn_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
