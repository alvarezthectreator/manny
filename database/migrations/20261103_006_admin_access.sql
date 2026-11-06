CREATE TABLE IF NOT EXISTS admin_users (
  user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  role ENUM('admin', 'super_admin') NOT NULL DEFAULT 'admin',
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_admin_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261103_006_admin_access');