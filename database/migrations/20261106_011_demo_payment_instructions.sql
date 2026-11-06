INSERT IGNORE INTO payment_channels
  (channel_key, display_name, channel_type, instructions, deposit_enabled, withdrawal_enabled)
VALUES
  ('cash_app', 'Cash App', 'other', 'Recipient: Not configured. This demo does not have a Cash App account. Do not send money.', 1, 0);

UPDATE payment_channels
SET instructions = CASE channel_key
  WHEN 'bank_transfer' THEN 'Bank: Example National Bank (demo only)\nAccount name: Cladvest Demo\nRouting number: 000000000\nAccount number: 0000000000\nReference: DEMO-ONLY\nThese are dummy details and cannot receive funds.'
  WHEN 'zelle' THEN 'Recipient: demo@cladvest.invalid\nName: Cladvest Demo\nReference: DEMO-ONLY\nThis reserved address cannot receive payments.'
  WHEN 'crypto_deposit' THEN 'Network: Sepolia testnet (demo only)\nAsset: Test ETH\nAddress: DEMO-NOT-A-VALID-CRYPTO-ADDRESS\nMemo: DEMO-ONLY\nNever send real assets.'
  WHEN 'crypto_wallet' THEN 'Destination: DEMO-NOT-A-VALID-CRYPTO-ADDRESS\nMemo: DEMO-ONLY\nThis is a placeholder, not a valid wallet address.'
  ELSE instructions
END
WHERE channel_key IN ('bank_transfer', 'zelle', 'crypto_deposit', 'crypto_wallet')
  AND TRIM(instructions) = '';

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_011_demo_payment_instructions');