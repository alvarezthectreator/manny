UPDATE payment_channels
SET instructions = 'Cash App ID: NOT-CONFIGURED\nNo Cash App account is connected. Do not send funds.'
WHERE channel_key = 'cash_app'
  AND instructions = 'Recipient: Not configured\nNo Cash App account is connected. Do not send funds.';

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_013_cash_app_copy_identifier');