ALTER TABLE cash_movements
  MODIFY status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled', 'returned', 'rejected') NOT NULL DEFAULT 'pending';

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261103_007_cash_review');