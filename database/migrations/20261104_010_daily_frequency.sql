ALTER TABLE recurring_investments
  MODIFY COLUMN frequency ENUM('daily', 'weekly', 'biweekly', 'monthly', 'quarterly', 'semiannual') NOT NULL;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261104_010_daily_frequency');