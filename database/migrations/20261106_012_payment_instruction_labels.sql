UPDATE payment_channels
SET instructions = 'Bank: Example National Bank\nAccount name: Cladvest\nRouting number: 000000000\nAccount number: 0000000000\nReference: SAMPLE-ONLY\nThese are placeholder details and cannot receive funds.'
WHERE channel_key = 'bank_transfer'
  AND instructions = 'Bank: Example National Bank (demo only)\nAccount name: Cladvest Demo\nRouting number: 000000000\nAccount number: 0000000000\nReference: DEMO-ONLY\nThese are dummy details and cannot receive funds.';

UPDATE payment_channels
SET instructions = 'Recipient: cladvest@invalid.example\nName: Cladvest\nReference: SAMPLE-ONLY\nThese placeholder details cannot receive payments.'
WHERE channel_key = 'zelle'
  AND instructions = 'Recipient: demo@cladvest.invalid\nName: Cladvest Demo\nReference: DEMO-ONLY\nThis reserved address cannot receive payments.';

UPDATE payment_channels
SET instructions = 'Recipient: Not configured\nNo Cash App account is connected. Do not send funds.'
WHERE channel_key = 'cash_app'
  AND instructions = 'Recipient: Not configured. This demo does not have a Cash App account. Do not send money.';

UPDATE payment_channels
SET instructions = 'Network: Sepolia testnet (test network only)\nAsset: Test ETH\nAddress: NOT-A-VALID-CRYPTO-ADDRESS\nMemo: SAMPLE-ONLY\nNever send real assets.'
WHERE channel_key = 'crypto_deposit'
  AND instructions = 'Network: Sepolia testnet (demo only)\nAsset: Test ETH\nAddress: DEMO-NOT-A-VALID-CRYPTO-ADDRESS\nMemo: DEMO-ONLY\nNever send real assets.';

UPDATE payment_channels
SET instructions = 'Destination: NOT-A-VALID-CRYPTO-ADDRESS\nMemo: SAMPLE-ONLY\nThis placeholder is not a valid wallet address.'
WHERE channel_key = 'crypto_wallet'
  AND instructions = 'Destination: DEMO-NOT-A-VALID-CRYPTO-ADDRESS\nMemo: DEMO-ONLY\nThis is a placeholder, not a valid wallet address.';

INSERT IGNORE INTO schema_migrations (version) VALUES ('20261106_012_payment_instruction_labels');