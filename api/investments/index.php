<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);

    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT s.id, a.symbol, a.name AS asset_name, s.amount_usd, s.estimated_quantity, s.unit_price, '
            . 's.currency, s.quote_source, s.status, s.started_at '
            . 'FROM investment_simulations s JOIN assets a ON a.id = s.asset_id '
            . 'WHERE s.user_id = :user_id ORDER BY s.started_at DESC, s.id DESC LIMIT 50'
        );
        $statement->execute(['user_id' => $user['id']]);
        $investments = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'symbol' => $row['symbol'],
            'asset_name' => $row['asset_name'],
            'amount' => (float) $row['amount_usd'],
            'quantity' => (float) $row['estimated_quantity'],
            'unit_price' => (float) $row['unit_price'],
            'currency' => $row['currency'],
            'quote_source' => $row['quote_source'],
            'status' => $row['status'],
            'started_at' => $row['started_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'investments' => $investments, 'execution_enabled' => false]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
    $amount = trim((string) ($data['amount'] ?? ''));
    if (!preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol)
        || !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amount)
        || (float) $amount < 1) {
        auth_respond(['ok' => false, 'message' => 'Choose an asset and enter an amount of at least $1.'], 422);
    }

    $assetQuery = $pdo->prepare(
        'SELECT a.id, a.asset_type, a.quote_currency, '
        . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS latest_price, '
        . '(SELECT p.price_source FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quote_source '
        . 'FROM assets a WHERE a.symbol = :symbol AND a.status = \'active\' LIMIT 1'
    );
    $assetQuery->execute(['symbol' => $symbol]);
    $asset = $assetQuery->fetch();
    if (!$asset || !in_array($asset['asset_type'], ['stock', 'etf', 'bond', 'fund', 'crypto'], true)) {
        auth_respond(['ok' => false, 'message' => 'This asset is not available for investment simulation.'], 409);
    }
    if ($asset['quote_currency'] !== 'USD' || $asset['latest_price'] === null || (float) $asset['latest_price'] <= 0) {
        auth_respond(['ok' => false, 'message' => 'A stored USD quote is required to start this simulation.'], 409);
    }

    $cashQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(CASE le.entry_side WHEN \'debit\' THEN le.amount ELSE -le.amount END), 0) '
        . 'FROM financial_accounts fa LEFT JOIN ledger_entries le ON le.ledger_account_id = fa.ledger_account_id '
        . 'WHERE fa.user_id = :user_id AND fa.account_type IN (\'checking\', \'savings\') AND fa.status = \'active\''
    );
    $cashQuery->execute(['user_id' => $user['id']]);
    $availableCash = (float) $cashQuery->fetchColumn();
    if ((float) $amount > $availableCash + 0.000001) {
        auth_respond(['ok' => false, 'message' => 'Amount exceeds your available cash balance.'], 422);
    }

    $accountQuery = $pdo->prepare(
        'SELECT id FROM financial_accounts WHERE user_id = :user_id AND account_type = \'investment\' '
        . 'AND status = \'active\' ORDER BY id LIMIT 1'
    );
    $accountQuery->execute(['user_id' => $user['id']]);
    $accountId = $accountQuery->fetchColumn();
    if (!$accountId) {
        auth_respond(['ok' => false, 'message' => 'No active investment account is available.'], 409);
    }

    $idempotencyKey = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if ($idempotencyKey !== '' && !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $idempotencyKey)) {
        auth_respond(['ok' => false, 'message' => 'Invalid idempotency key.'], 422);
    }
    $quantity = number_format((float) $amount / (float) $asset['latest_price'], 12, '.', '');
    if ((float) $quantity <= 0 || (float) $quantity > 1000000000) {
        auth_respond(['ok' => false, 'message' => 'The amount is outside the supported simulation range.'], 422);
    }
    $insert = $pdo->prepare(
        'INSERT INTO investment_simulations (public_id, user_id, account_id, asset_id, amount_usd, estimated_quantity, unit_price, currency, quote_source, idempotency_key) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, :account_id, :asset_id, :amount, :quantity, :unit_price, :currency, :quote_source, :idempotency_key) '
        . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'account_id' => $accountId,
        'asset_id' => $asset['id'],
        'amount' => number_format((float) $amount, 2, '.', ''),
        'quantity' => $quantity,
        'unit_price' => $asset['latest_price'],
        'currency' => $asset['quote_currency'],
        'quote_source' => $asset['quote_source'] ?? 'stored-quote',
        'idempotency_key' => $idempotencyKey === '' ? null : $idempotencyKey,
    ]);
    auth_respond([
        'ok' => true,
        'investment_id' => (int) $pdo->lastInsertId(),
        'symbol' => $symbol,
        'amount' => (float) $amount,
        'quantity' => (float) $quantity,
        'status' => 'started',
        'execution_enabled' => false,
        'message' => 'Investment simulation started successfully. No real trade was placed and no cash was deducted.',
    ], 201);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Investment simulation service unavailable.'], 503);
}