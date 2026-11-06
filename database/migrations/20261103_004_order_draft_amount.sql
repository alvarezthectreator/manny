SET @notional_amount_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'investment_orders' AND column_name = 'notional_amount'
);
SET @notional_amount_sql = IF(
  @notional_amount_exists = 0,
  'ALTER TABLE investment_orders ADD COLUMN notional_amount DECIMAL(20,8) NULL AFTER quantity',
  'SELECT 1'
);
PREPARE notional_amount_statement FROM @notional_amount_sql;
EXECUTE notional_amount_statement;
DEALLOCATE PREPARE notional_amount_statement;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261103_004_order_draft_amount');