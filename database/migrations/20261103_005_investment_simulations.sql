CREATE TABLE IF NOT EXISTS investment_simulations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id BINARY(16) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  amount_usd DECIMAL(20,8) NOT NULL,
  estimated_quantity DECIMAL(28,12) NOT NULL,
  unit_price DECIMAL(24,10) NOT NULL,
  currency CHAR(3) NOT NULL,
  quote_source VARCHAR(64) NOT NULL,
  status ENUM('started', 'cancelled') NOT NULL DEFAULT 'started',
  idempotency_key VARCHAR(128) NULL,
  started_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_investment_simulations_public_id (public_id),
  UNIQUE KEY uq_investment_simulations_idempotency (user_id, idempotency_key),
  KEY idx_investment_simulations_user_time (user_id, started_at),
  CONSTRAINT chk_investment_simulations_amount CHECK (amount_usd > 0 AND estimated_quantity > 0 AND unit_price > 0),
  CONSTRAINT fk_investment_simulations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_simulations_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_simulations_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261103_005_investment_simulations');