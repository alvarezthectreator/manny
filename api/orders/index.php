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
        $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            auth_respond(['ok' => false, 'message' => 'Limit must be between 1 and 100.'], 422);
        }
        $statement = $pdo->prepare(
            'SELECT o.id, a.symbol, a.name AS asset_name, o.side, o.order_type, o.status, o.quantity, o.notional_amount, '
            . 'o.limit_price, o.currency, o.submitted_at '
            . 'FROM investment_orders o JOIN assets a ON a.id = o.asset_id '
            . 'WHERE o.user_id = :user_id ORDER BY o.submitted_at DESC, o.id DESC LIMIT ' . (int) $limit
        );
        $statement->execute(['user_id' => $user['id']]);
        $orders = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'symbol' => $row['symbol'],
            'asset_name' => $row['asset_name'],
            'side' => $row['side'],
            'type' => $row['order_type'],
            'status' => $row['status'],
            'quantity' => (float) $row['quantity'],
            'notional_amount' => $row['notional_amount'] === null ? null : (float) $row['notional_amount'],
            'limit_price' => $row['limit_price'] === null ? null : (float) $row['limit_price'],
            'currency' => $row['currency'],
            'created_at' => $row['submitted_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'orders' => $orders, 'execution_enabled' => false]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
    $side = strtolower(trim((string) ($data['side'] ?? '')));
    $type = strtolower(trim((string) ($data['type'] ?? 'market')));
    $quantity = (string) ($data['quantity'] ?? '');
    $requestedAmount = trim((string) ($data['amount'] ?? ''));
    $limitPrice = (string) ($data['limit_price'] ?? '');
    if (!preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol)
        || !in_array($side, ['buy', 'sell'], true)
        || !in_array($type, ['market', 'limit'], true)
        || ($requestedAmount === '' && (!preg_match('/^\d{1,12}(?:\.\d{1,12})?$/', $quantity) || (float) $quantity <= 0 || (float) $quantity > 1000000000))
        || ($requestedAmount !== '' && ($side !== 'buy' || $type !== 'market' || !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $requestedAmount) || (float) $requestedAmount < 1))
        || ($type === 'limit' && (!preg_match('/^\d{1,12}(?:\.\d{1,10})?$/', $limitPrice) || (float) $limitPrice <= 0))) {
        auth_respond(['ok' => false, 'message' => 'Enter a supported asset, side, quantity, and order type.'], 422);
    }

    $assetQuery = $pdo->prepare(
        'SELECT a.id, a.quote_currency, a.asset_type, '
        . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS latest_price '
        . 'FROM assets a WHERE a.symbol = :symbol AND a.status = \'active\' LIMIT 1'
    );
    $assetQuery->execute(['symbol' => $symbol]);
    $asset = $assetQuery->fetch();
    if (!$asset) {
        auth_respond(['ok' => false, 'message' => 'Asset not found.'], 404);
    }
    if (!in_array($asset['asset_type'], ['stock', 'etf', 'bond', 'fund', 'crypto'], true)) {
        auth_respond(['ok' => false, 'message' => 'Drafts are unavailable for this asset type.'], 409);
    }
    $notionalAmount = null;
    if ($requestedAmount !== '') {
        if ($asset['quote_currency'] !== 'USD' || $asset['latest_price'] === null || (float) $asset['latest_price'] <= 0) {
            auth_respond(['ok' => false, 'message' => 'A stored USD quote is required for an amount-based draft.'], 409);
        }
        $cashQuery = $pdo->prepare(
            'SELECT COALESCE(SUM(CASE le.entry_side WHEN \'debit\' THEN le.amount ELSE -le.amount END), 0) '
            . 'FROM financial_accounts fa LEFT JOIN ledger_entries le ON le.ledger_account_id = fa.ledger_account_id '
            . 'WHERE fa.user_id = :user_id AND fa.account_type IN (\'checking\', \'savings\') AND fa.status = \'active\''
        );
        $cashQuery->execute(['user_id' => $user['id']]);
        $availableCash = (float) $cashQuery->fetchColumn();
        if ((float) $requestedAmount > $availableCash + 0.000001) {
            auth_respond(['ok' => false, 'message' => 'Amount exceeds your available cash balance.'], 422);
        }
        $quantity = number_format((float) $requestedAmount / (float) $asset['latest_price'], 12, '.', '');
        if ((float) $quantity <= 0 || (float) $quantity > 1000000000) {
            auth_respond(['ok' => false, 'message' => 'The amount is outside the supported draft range.'], 422);
        }
        $notionalAmount = number_format((float) $requestedAmount, 2, '.', '');
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
    if ($idempotencyKey !== '' && (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $idempotencyKey))) {
        auth_respond(['ok' => false, 'message' => 'Invalid idempotency key.'], 422);
    }
    $insert = $pdo->prepare(
        'INSERT INTO investment_orders (public_id, user_id, account_id, asset_id, side, order_type, status, quantity, notional_amount, limit_price, currency, idempotency_key) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, :account_id, :asset_id, :side, :order_type, \'draft\', :quantity, :notional_amount, :limit_price, :currency, :idempotency_key) '
        . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'account_id' => $accountId,
        'asset_id' => $asset['id'],
        'side' => $side,
        'order_type' => $type,
        'quantity' => $quantity,
        'notional_amount' => $notionalAmount,
        'limit_price' => $type === 'limit' ? $limitPrice : null,
        'currency' => $asset['quote_currency'],
        'idempotency_key' => $idempotencyKey === '' ? null : $idempotencyKey,
    ]);
    auth_respond([
        'ok' => true,
        'order_id' => (int) $pdo->lastInsertId(),
        'amount' => $notionalAmount === null ? null : (float) $notionalAmount,
        'quantity' => (float) $quantity,
        'status' => 'draft',
        'execution_enabled' => false,
        'message' => 'Draft saved. No order was submitted or executed.',
    ], 201);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Order service unavailable.'], 503);
}