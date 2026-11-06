CREATE TABLE IF NOT EXISTS payment_channels (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  channel_key VARCHAR(32) NOT NULL,
  display_name VARCHAR(100) NOT NULL,
  channel_type ENUM('bank_transfer', 'zelle', 'crypto', 'other') NOT NULL,
  instructions TEXT NOT NULL,
  deposit_enabled TINYINT NOT NULL DEFAULT 0,
  withdrawal_enabled TINYINT NOT NULL DEFAULT 0,
  is_active TINYINT NOT NULL DEFAULT 1,
  updated_by BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_payment_channels_key (channel_key),
  KEY idx_payment_channels_active (is_active, deposit_enabled, withdrawal_enabled),
  CONSTRAINT fk_payment_channels_admin FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT IGNORE INTO payment_channels (channel_key, display_name, channel_type, instructions, deposit_enabled, withdrawal_enabled) VALUES
  ('bank_transfer', 'Bank transfer', 'bank_transfer', '', 1, 1),
  ('zelle', 'Zelle', 'zelle', '', 1, 1),
  ('crypto_deposit', 'Crypto deposit', 'crypto', '', 1, 0),
  ('crypto_wallet', 'Crypto wallet', 'crypto', '', 0, 1);

SET @payment_channel_column_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'cash_movements' AND column_name = 'payment_channel_id'
);
SET @payment_channel_column_sql = IF(
  @payment_channel_column_exists = 0,
  'ALTER TABLE cash_movements ADD COLUMN payment_channel_id BIGINT UNSIGNED NULL AFTER payment_method_id',
  'SELECT 1'
);
PREPARE payment_channel_column_statement FROM @payment_channel_column_sql;
EXECUTE payment_channel_column_statement;
DEALLOCATE PREPARE payment_channel_column_statement;

SET @payment_channel_fk_exists = (
  SELECT COUNT(*) FROM information_schema.table_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'cash_movements' AND constraint_name = 'fk_cash_movements_channel'
);
SET @payment_channel_fk_sql = IF(
  @payment_channel_fk_exists = 0,
  'ALTER TABLE cash_movements ADD CONSTRAINT fk_cash_movements_channel FOREIGN KEY (payment_channel_id) REFERENCES payment_channels(id) ON DELETE SET NULL',
  'SELECT 1'
);
PREPARE payment_channel_fk_statement FROM @payment_channel_fk_sql;
EXECUTE payment_channel_fk_statement;
DEALLOCATE PREPARE payment_channel_fk_statement;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261104_008_payment_channels');