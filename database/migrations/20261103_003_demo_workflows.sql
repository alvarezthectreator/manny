SET @loan_reminders_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'user_preferences' AND column_name = 'loan_reminders'
);
SET @loan_reminders_sql = IF(
  @loan_reminders_exists = 0,
  'ALTER TABLE user_preferences ADD COLUMN loan_reminders TINYINT NOT NULL DEFAULT 1 AFTER email_updates',
  'SELECT 1'
);
PREPARE loan_reminders_statement FROM @loan_reminders_sql;
EXECUTE loan_reminders_statement;
DEALLOCATE PREPARE loan_reminders_statement;

CREATE TABLE IF NOT EXISTS swap_simulations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id BINARY(16) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  from_asset_id BIGINT UNSIGNED NOT NULL,
  to_asset_id BIGINT UNSIGNED NOT NULL,
  input_quantity DECIMAL(28,12) NOT NULL,
  output_quantity DECIMAL(28,12) NOT NULL,
  from_price DECIMAL(24,10) NOT NULL,
  to_price DECIMAL(24,10) NOT NULL,
  notional_usd DECIMAL(20,8) NOT NULL,
  fee_usd DECIMAL(20,8) NOT NULL DEFAULT 0,
  status ENUM('simulated', 'cancelled') NOT NULL DEFAULT 'simulated',
  idempotency_key VARCHAR(128) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_swap_simulations_public_id (public_id),
  UNIQUE KEY uq_swap_simulations_idempotency (user_id, idempotency_key),
  KEY idx_swap_simulations_user_time (user_id, created_at),
  CONSTRAINT chk_swap_simulations_values CHECK (input_quantity > 0 AND output_quantity > 0 AND from_price > 0 AND to_price > 0 AND notional_usd > 0 AND fee_usd >= 0),
  CONSTRAINT chk_swap_simulations_different_assets CHECK (from_asset_id <> to_asset_id),
  CONSTRAINT fk_swap_simulations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_swap_simulations_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_swap_simulations_from_asset FOREIGN KEY (from_asset_id) REFERENCES assets(id) ON DELETE RESTRICT,
  CONSTRAINT fk_swap_simulations_to_asset FOREIGN KEY (to_asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_agents (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  is_active TINYINT NOT NULL DEFAULT 1,
  provisioned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_support_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261103_003_demo_workflows');