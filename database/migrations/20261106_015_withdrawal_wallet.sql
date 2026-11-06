SET @withdrawal_wallet_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'user_profiles' AND column_name = 'withdrawal_wallet'
);
SET @withdrawal_wallet_sql = IF(
  @withdrawal_wallet_exists = 0,
  'ALTER TABLE user_profiles ADD COLUMN withdrawal_wallet VARCHAR(512) NULL AFTER region',
  'SELECT 1'
);
PREPARE withdrawal_wallet_statement FROM @withdrawal_wallet_sql;
EXECUTE withdrawal_wallet_statement;
DEALLOCATE PREPARE withdrawal_wallet_statement;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_015_withdrawal_wallet');