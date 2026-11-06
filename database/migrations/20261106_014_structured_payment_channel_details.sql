SET @payment_details_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'payment_channels' AND column_name = 'details'
);
SET @payment_details_sql = IF(
  @payment_details_exists = 0,
  'ALTER TABLE payment_channels ADD COLUMN details JSON NULL AFTER channel_type',
  'SELECT 1'
);
PREPARE payment_details_statement FROM @payment_details_sql;
EXECUTE payment_details_statement;
DEALLOCATE PREPARE payment_details_statement;

UPDATE payment_channels
SET details = JSON_OBJECT(
    'bank_name', 'Example National Bank',
    'account_name', 'Cladvest',
    'routing_number', '000000000',
    'account_number', '0000000000',
    'reference', 'SAMPLE-ONLY'
  ),
  instructions = 'These are placeholder details and cannot receive funds.'
WHERE channel_key = 'bank_transfer' AND details IS NULL
  AND instructions = CONCAT(
    'Bank: Example National Bank', CHAR(10),
    'Account name: Cladvest', CHAR(10),
    'Routing number: 000000000', CHAR(10),
    'Account number: 0000000000', CHAR(10),
    'Reference: SAMPLE-ONLY', CHAR(10),
    'These are placeholder details and cannot receive funds.'
  );

UPDATE payment_channels
SET details = JSON_OBJECT('recipient', 'cladvest@invalid.example', 'account_name', 'Cladvest', 'reference', 'SAMPLE-ONLY'),
  instructions = 'These placeholder details cannot receive payments.'
WHERE channel_key = 'zelle' AND details IS NULL
  AND instructions = CONCAT(
    'Recipient: cladvest@invalid.example', CHAR(10),
    'Name: Cladvest', CHAR(10),
    'Reference: SAMPLE-ONLY', CHAR(10),
    'These placeholder details cannot receive payments.'
  );

UPDATE payment_channels
SET details = JSON_OBJECT('cash_app_id', 'NOT-CONFIGURED'),
  instructions = 'No Cash App account is connected. Do not send funds.'
WHERE channel_key = 'cash_app' AND details IS NULL
  AND instructions = CONCAT(
    'Cash App ID: NOT-CONFIGURED', CHAR(10),
    'No Cash App account is connected. Do not send funds.'
  );

UPDATE payment_channels
SET details = JSON_OBJECT(
    'network', 'Sepolia testnet',
    'asset', 'Test ETH',
    'wallet_address', 'NOT-A-VALID-CRYPTO-ADDRESS',
    'memo', 'SAMPLE-ONLY'
  ),
  instructions = 'Test-network placeholder only. Never send real assets.'
WHERE channel_key = 'crypto_deposit' AND details IS NULL
  AND instructions = CONCAT(
    'Network: Sepolia testnet (test network only)', CHAR(10),
    'Asset: Test ETH', CHAR(10),
    'Address: NOT-A-VALID-CRYPTO-ADDRESS', CHAR(10),
    'Memo: SAMPLE-ONLY', CHAR(10),
    'Never send real assets.'
  );

UPDATE payment_channels
SET details = JSON_OBJECT(
    'network', '',
    'asset', '',
    'wallet_address', 'NOT-A-VALID-CRYPTO-ADDRESS',
    'memo', 'SAMPLE-ONLY'
  ),
  instructions = 'This placeholder is not a valid wallet address.'
WHERE channel_key = 'crypto_wallet' AND details IS NULL
  AND instructions = CONCAT(
    'Destination: NOT-A-VALID-CRYPTO-ADDRESS', CHAR(10),
    'Memo: SAMPLE-ONLY', CHAR(10),
    'This placeholder is not a valid wallet address.'
  );

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_014_structured_payment_channel_details');