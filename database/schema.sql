CREATE DATABASE IF NOT EXISTS cladvest_app
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

USE cladvest_app;

CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(64) NOT NULL PRIMARY KEY,
  applied_at TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  status ENUM('pending_verification', 'active', 'locked', 'closed') NOT NULL DEFAULT 'pending_verification',
  email_verified_at DATETIME(6) NULL,
  last_login_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_status_created (status, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  first_name VARCHAR(80) NOT NULL,
  last_name VARCHAR(80) NOT NULL,
  phone_country_code VARCHAR(8) NULL,
  phone_number VARCHAR(32) NULL,
  country_code CHAR(2) NULL,
  region VARCHAR(120) NULL,
  withdrawal_wallet VARCHAR(512) NULL,
  withdrawal_details JSON NULL,
  timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
  locale VARCHAR(16) NOT NULL DEFAULT 'en',
  avatar_storage_key VARCHAR(512) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_user_profiles_country (country_code)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_preferences (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  email_updates TINYINT NOT NULL DEFAULT 1,
  loan_reminders TINYINT NOT NULL DEFAULT 1,
  security_alerts TINYINT NOT NULL DEFAULT 1,
  product_updates TINYINT NOT NULL DEFAULT 0,
  in_app_notifications TINYINT NOT NULL DEFAULT 1,
  preferred_currency CHAR(3) NOT NULL DEFAULT 'USD',
  preferred_locale VARCHAR(16) NOT NULL DEFAULT 'en',
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  last_seen_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  revoked_at DATETIME(6) NULL,
  UNIQUE KEY uq_user_sessions_token_hash (token_hash),
  KEY idx_user_sessions_user_expiry (user_id, expires_at),
  CONSTRAINT fk_user_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  token_type ENUM('email_verification', 'password_reset', 'mfa_challenge') NOT NULL,
  token_hash CHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expires_at DATETIME(6) NOT NULL,
  consumed_at DATETIME(6) NULL,
  UNIQUE KEY uq_user_tokens_hash (token_hash),
  KEY idx_user_tokens_user_type_expiry (user_id, token_type, expires_at),
  CONSTRAINT fk_user_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS consent_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  consent_type VARCHAR(64) NOT NULL,
  document_version VARCHAR(32) NOT NULL,
  granted TINYINT NOT NULL,
  recorded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  ip_address VARBINARY(16) NULL,
  KEY idx_consent_user_type_time (user_id, consent_type, recorded_at),
  CONSTRAINT fk_consent_records_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS security_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(64) NOT NULL,
  outcome ENUM('success', 'failure', 'blocked', 'review') NOT NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(512) NULL,
  details JSON NULL,
  occurred_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_security_events_user_time (user_id, occurred_at),
  KEY idx_security_events_type_time (event_type, occurred_at),
  CONSTRAINT fk_security_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ledger_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  owner_user_id BIGINT UNSIGNED NULL,
  account_code VARCHAR(64) NOT NULL,
  account_type ENUM('cash', 'investment', 'receivable', 'payable', 'revenue', 'expense', 'clearing', 'equity') NOT NULL,
  currency CHAR(3) NOT NULL,
  status ENUM('active', 'frozen', 'closed') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_ledger_owner_code (owner_user_id, account_code),
  KEY idx_ledger_accounts_owner_type (owner_user_id, account_type),
  CONSTRAINT fk_ledger_accounts_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_accounts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  ledger_account_id BIGINT UNSIGNED NOT NULL,
  account_name VARCHAR(100) NOT NULL,
  account_type ENUM('checking', 'savings', 'investment', 'loan') NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  account_number_last4 CHAR(4) NULL,
  status ENUM('pending', 'active', 'restricted', 'closed') NOT NULL DEFAULT 'active',
  opened_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  closed_at DATETIME(6) NULL,
  UNIQUE KEY uq_financial_accounts_ledger (ledger_account_id),
  KEY idx_financial_accounts_user_status (user_id, status),
  CONSTRAINT fk_financial_accounts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_financial_accounts_ledger FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS financial_transactions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id BINARY(16) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'buy', 'sell', 'dividend', 'fee', 'loan_disbursement', 'loan_payment', 'adjustment') NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'reversed') NOT NULL DEFAULT 'pending',
  amount DECIMAL(20,8) NOT NULL,
  currency CHAR(3) NOT NULL,
  description VARCHAR(255) NULL,
  external_reference VARCHAR(128) NULL,
  idempotency_key VARCHAR(128) NULL,
  metadata JSON NULL,
  occurred_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_financial_transactions_public_id (public_id),
  UNIQUE KEY uq_financial_transactions_idempotency (user_id, idempotency_key),
  KEY idx_financial_transactions_user_time (user_id, created_at),
  KEY idx_financial_transactions_status_time (status, created_at),
  CONSTRAINT chk_financial_transactions_amount CHECK (amount > 0),
  CONSTRAINT fk_financial_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ledger_entries (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  ledger_account_id BIGINT UNSIGNED NOT NULL,
  entry_side ENUM('debit', 'credit') NOT NULL,
  amount DECIMAL(20,8) NOT NULL,
  currency CHAR(3) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_ledger_entries_transaction (transaction_id),
  KEY idx_ledger_entries_account_time (ledger_account_id, created_at),
  CONSTRAINT chk_ledger_entries_amount CHECK (amount > 0),
  CONSTRAINT fk_ledger_entries_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_ledger_entries_account FOREIGN KEY (ledger_account_id) REFERENCES ledger_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payment_methods (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  method_type ENUM('bank_account', 'debit_card', 'credit_card', 'wallet') NOT NULL,
  provider VARCHAR(64) NULL,
  provider_token VARCHAR(255) NULL,
  display_name VARCHAR(100) NOT NULL,
  card_brand VARCHAR(32) NULL,
  last4 CHAR(4) NULL,
  expiry_month TINYINT UNSIGNED NULL,
  expiry_year SMALLINT UNSIGNED NULL,
  is_default TINYINT NOT NULL DEFAULT 0,
  status ENUM('pending', 'verified', 'disabled', 'expired') NOT NULL DEFAULT 'pending',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_payment_methods_user_status (user_id, status),
  CONSTRAINT chk_payment_methods_expiry_month CHECK (expiry_month IS NULL OR expiry_month BETWEEN 1 AND 12),
  CONSTRAINT fk_payment_methods_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS cash_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  transaction_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  payment_method_id BIGINT UNSIGNED NULL,
  payment_channel_id BIGINT UNSIGNED NULL,
  movement_type ENUM('deposit', 'withdrawal') NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'returned', 'rejected') NOT NULL DEFAULT 'pending',
  amount DECIMAL(20,8) NOT NULL,
  fee DECIMAL(20,8) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  provider_reference VARCHAR(128) NULL,
  initiated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  completed_at DATETIME(6) NULL,
  UNIQUE KEY uq_cash_movements_transaction (transaction_id),
  KEY idx_cash_movements_account_time (account_id, initiated_at),
  CONSTRAINT chk_cash_movements_amount CHECK (amount > 0),
  CONSTRAINT chk_cash_movements_fee CHECK (fee >= 0),
  CONSTRAINT fk_cash_movements_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cash_movements_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_cash_movements_payment FOREIGN KEY (payment_method_id) REFERENCES payment_methods(id) ON DELETE SET NULL,
  CONSTRAINT fk_cash_movements_channel FOREIGN KEY (payment_channel_id) REFERENCES payment_channels(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  symbol VARCHAR(24) NOT NULL,
  name VARCHAR(160) NOT NULL,
  asset_type ENUM('stock', 'etf', 'bond', 'fund', 'crypto', 'private_equity', 'other') NOT NULL,
  exchange_code VARCHAR(32) NULL,
  quote_currency CHAR(3) NOT NULL DEFAULT 'USD',
  description TEXT NULL,
  is_tradeable TINYINT NOT NULL DEFAULT 0,
  status ENUM('active', 'halted', 'delisted') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_assets_symbol_exchange (symbol, exchange_code),
  KEY idx_assets_type_status (asset_type, status),
  FULLTEXT KEY ft_assets_name_symbol (name, symbol)
) ENGINE=InnoDB;

INSERT IGNORE INTO assets (symbol, name, asset_type, exchange_code, quote_currency, is_tradeable, status) VALUES
  ('TSLA', 'Tesla', 'stock', 'NASDAQ', 'USD', 0, 'active'),
  ('NVDA', 'NVIDIA', 'stock', 'NASDAQ', 'USD', 0, 'active'),
  ('BTC', 'Bitcoin', 'crypto', 'CRYPTO', 'USD', 0, 'active');

CREATE TABLE IF NOT EXISTS asset_prices (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  asset_id BIGINT UNSIGNED NOT NULL,
  price DECIMAL(24,10) NOT NULL,
  currency CHAR(3) NOT NULL,
  price_source VARCHAR(64) NOT NULL,
  observed_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_asset_prices_asset_time (asset_id, observed_at),
  KEY idx_asset_prices_observed (observed_at),
  CONSTRAINT chk_asset_prices_price CHECK (price >= 0),
  CONSTRAINT fk_asset_prices_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS investment_orders (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id BINARY(16) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  side ENUM('buy', 'sell') NOT NULL,
  order_type ENUM('market', 'limit', 'stop', 'recurring') NOT NULL,
  status ENUM('draft', 'pending', 'accepted', 'partially_filled', 'filled', 'cancelled', 'rejected', 'expired') NOT NULL DEFAULT 'pending',
  quantity DECIMAL(28,12) NOT NULL,
  notional_amount DECIMAL(20,8) NULL,
  limit_price DECIMAL(24,10) NULL,
  currency CHAR(3) NOT NULL,
  idempotency_key VARCHAR(128) NULL,
  submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  completed_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_investment_orders_public_id (public_id),
  UNIQUE KEY uq_investment_orders_idempotency (user_id, idempotency_key),
  KEY idx_investment_orders_user_status (user_id, status, submitted_at),
  CONSTRAINT chk_investment_orders_quantity CHECK (quantity > 0),
  CONSTRAINT chk_investment_orders_limit_price CHECK (limit_price IS NULL OR limit_price > 0),
  CONSTRAINT fk_investment_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_orders_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_orders_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS investment_fills (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  execution_reference VARCHAR(128) NULL,
  quantity DECIMAL(28,12) NOT NULL,
  price DECIMAL(24,10) NOT NULL,
  fee DECIMAL(20,8) NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL,
  executed_at DATETIME(6) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_investment_fills_order_time (order_id, executed_at),
  CONSTRAINT chk_investment_fills_quantity CHECK (quantity > 0),
  CONSTRAINT chk_investment_fills_price CHECK (price > 0),
  CONSTRAINT chk_investment_fills_fee CHECK (fee >= 0),
  CONSTRAINT fk_investment_fills_order FOREIGN KEY (order_id) REFERENCES investment_orders(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS investment_positions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  quantity DECIMAL(28,12) NOT NULL DEFAULT 0,
  average_cost DECIMAL(24,10) NOT NULL DEFAULT 0,
  cost_basis DECIMAL(20,8) NOT NULL DEFAULT 0,
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_investment_positions_account_asset (account_id, asset_id),
  CONSTRAINT chk_investment_positions_quantity CHECK (quantity >= 0),
  CONSTRAINT fk_investment_positions_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_investment_positions_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watchlist_items (
  user_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  added_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (user_id, asset_id),
  CONSTRAINT fk_watchlist_items_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_watchlist_items_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS financial_goals (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  target_amount DECIMAL(20,8) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  target_date DATE NULL,
  status ENUM('active', 'paused', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_financial_goals_user_status (user_id, status),
  CONSTRAINT chk_financial_goals_target CHECK (target_amount > 0),
  CONSTRAINT fk_financial_goals_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS goal_contributions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  goal_id BIGINT UNSIGNED NOT NULL,
  transaction_id BIGINT UNSIGNED NULL,
  amount DECIMAL(20,8) NOT NULL,
  currency CHAR(3) NOT NULL,
  contributed_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_goal_contributions_goal_time (goal_id, contributed_at),
  CONSTRAINT chk_goal_contributions_amount CHECK (amount > 0),
  CONSTRAINT fk_goal_contributions_goal FOREIGN KEY (goal_id) REFERENCES financial_goals(id) ON DELETE RESTRICT,
  CONSTRAINT fk_goal_contributions_transaction FOREIGN KEY (transaction_id) REFERENCES financial_transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS recurring_investments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(20,8) NOT NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  frequency ENUM('daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual') NOT NULL,
  next_run_at DATETIME(6) NOT NULL,
  last_run_at DATETIME(6) NULL,
  status ENUM('active', 'paused', 'cancelled', 'completed') NOT NULL DEFAULT 'active',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_recurring_investments_due (status, next_run_at),
  KEY idx_recurring_investments_user (user_id, status),
  CONSTRAINT chk_recurring_investments_amount CHECK (amount > 0),
  CONSTRAINT fk_recurring_investments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_recurring_investments_account FOREIGN KEY (account_id) REFERENCES financial_accounts(id) ON DELETE RESTRICT,
  CONSTRAINT fk_recurring_investments_asset FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_applications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  public_id BINARY(16) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  requested_amount DECIMAL(20,8) NOT NULL,
  approved_amount DECIMAL(20,8) NULL,
  currency CHAR(3) NOT NULL DEFAULT 'USD',
  term_months SMALLINT UNSIGNED NOT NULL,
  apr DECIMAL(7,4) NULL,
  status ENUM('draft', 'submitted', 'under_review', 'approved', 'declined', 'withdrawn', 'funded', 'closed') NOT NULL DEFAULT 'draft',
  submitted_at DATETIME(6) NULL,
  decision_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_loan_applications_public_id (public_id),
  KEY idx_loan_applications_user_status (user_id, status, created_at),
  CONSTRAINT chk_loan_applications_amount CHECK (requested_amount > 0),
  CONSTRAINT chk_loan_applications_term CHECK (term_months > 0),
  CONSTRAINT chk_loan_applications_apr CHECK (apr IS NULL OR apr >= 0),
  CONSTRAINT fk_loan_applications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS loan_repayment_schedule (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  loan_application_id BIGINT UNSIGNED NOT NULL,
  installment_number SMALLINT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  principal_due DECIMAL(20,8) NOT NULL,
  interest_due DECIMAL(20,8) NOT NULL,
  amount_paid DECIMAL(20,8) NOT NULL DEFAULT 0,
  status ENUM('scheduled', 'due', 'paid', 'late', 'waived') NOT NULL DEFAULT 'scheduled',
  paid_at DATETIME(6) NULL,
  UNIQUE KEY uq_loan_schedule_installment (loan_application_id, installment_number),
  KEY idx_loan_schedule_due (status, due_date),
  CONSTRAINT chk_loan_schedule_values CHECK (principal_due >= 0 AND interest_due >= 0 AND amount_paid >= 0),
  CONSTRAINT fk_loan_schedule_application FOREIGN KEY (loan_application_id) REFERENCES loan_applications(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS identity_verifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(64) NULL,
  provider_reference VARCHAR(128) NULL,
  verification_type VARCHAR(48) NOT NULL,
  status ENUM('not_started', 'pending', 'verified', 'rejected', 'expired', 'manual_review') NOT NULL DEFAULT 'not_started',
  country_code CHAR(2) NULL,
  submitted_at DATETIME(6) NULL,
  reviewed_at DATETIME(6) NULL,
  expires_at DATETIME(6) NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  KEY idx_identity_verifications_user_status (user_id, status),
  UNIQUE KEY uq_identity_verifications_provider_ref (provider, provider_reference),
  CONSTRAINT fk_identity_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  verification_id BIGINT UNSIGNED NULL,
  document_type VARCHAR(48) NOT NULL,
  storage_key VARCHAR(512) NOT NULL,
  original_filename VARCHAR(255) NULL,
  mime_type VARCHAR(100) NOT NULL,
  file_size_bytes BIGINT UNSIGNED NOT NULL,
  status ENUM('uploaded', 'pending_review', 'accepted', 'rejected', 'deleted') NOT NULL DEFAULT 'uploaded',
  uploaded_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  reviewed_at DATETIME(6) NULL,
  KEY idx_user_documents_user_status (user_id, status),
  CONSTRAINT fk_user_documents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_user_documents_verification FOREIGN KEY (verification_id) REFERENCES identity_verifications(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  category VARCHAR(48) NULL,
  status ENUM('open', 'waiting_for_user', 'waiting_for_support', 'resolved', 'closed') NOT NULL DEFAULT 'open',
  priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  assigned_agent_id BIGINT UNSIGNED NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  closed_at DATETIME(6) NULL,
  KEY idx_support_conversations_user_status (user_id, status, updated_at),
  KEY idx_support_conversations_queue (status, priority, updated_at),
  CONSTRAINT fk_support_conversations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  CONSTRAINT fk_support_conversations_agent FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_user_id BIGINT UNSIGNED NULL,
  sender_type ENUM('customer', 'agent', 'system') NOT NULL,
  message_text TEXT NOT NULL,
  attachment_storage_key VARCHAR(512) NULL,
  sent_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  read_at DATETIME(6) NULL,
  KEY idx_support_messages_conversation_time (conversation_id, sent_at),
  CONSTRAINT fk_support_messages_conversation FOREIGN KEY (conversation_id) REFERENCES support_conversations(id) ON DELETE RESTRICT,
  CONSTRAINT fk_support_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS support_agents (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  is_active TINYINT NOT NULL DEFAULT 1,
  provisioned_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_support_agents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS admin_users (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_admin_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  notification_type VARCHAR(64) NOT NULL,
  title VARCHAR(180) NOT NULL,
  body TEXT NULL,
  action_url VARCHAR(512) NULL,
  payload JSON NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  read_at DATETIME(6) NULL,
  KEY idx_notifications_user_unread (user_id, read_at, created_at),
  CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS audit_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(96) NOT NULL,
  entity_type VARCHAR(64) NOT NULL,
  entity_id VARCHAR(64) NULL,
  request_id CHAR(36) NULL,
  ip_address VARBINARY(16) NULL,
  before_data JSON NULL,
  after_data JSON NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY idx_audit_events_entity (entity_type, entity_id, created_at),
  KEY idx_audit_events_actor_time (actor_user_id, created_at),
  CONSTRAINT fk_audit_events_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

INSERT INTO schema_migrations (version)
SELECT '20261102_001_initial_schema'
WHERE NOT EXISTS (
  SELECT 1 FROM schema_migrations WHERE version = '20261102_001_initial_schema'
);