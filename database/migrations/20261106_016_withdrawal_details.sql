SET @withdrawal_details_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'user_profiles' AND column_name = 'withdrawal_details'
);
SET @withdrawal_details_sql = IF(
  @withdrawal_details_exists = 0,
  'ALTER TABLE user_profiles ADD COLUMN withdrawal_details JSON NULL AFTER withdrawal_wallet',
  'SELECT 1'
);
PREPARE withdrawal_details_statement FROM @withdrawal_details_sql;
EXECUTE withdrawal_details_statement;
DEALLOCATE PREPARE withdrawal_details_statement;

UPDATE user_profiles
SET withdrawal_details = JSON_OBJECT(
  'preferred_method', 'crypto',
  'crypto_wallet', withdrawal_wallet
)
WHERE withdrawal_details IS NULL AND withdrawal_wallet IS NOT NULL AND TRIM(withdrawal_wallet) <> '';

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_016_withdrawal_details');