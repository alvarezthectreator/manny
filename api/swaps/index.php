<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

function swap_quote(PDO $pdo, string $fromSymbol, string $toSymbol, string $quantity): array
{
    $fromSymbol = strtoupper(trim($fromSymbol));
    $toSymbol = strtoupper(trim($toSymbol));
    if (!preg_match('/^[A-Z0-9._-]{1,24}$/', $fromSymbol)
        || !preg_match('/^[A-Z0-9._-]{1,24}$/', $toSymbol)
        || $fromSymbol === $toSymbol
        || !preg_match('/^\d{1,12}(?:\.\d{1,12})?$/', $quantity)
        || (float) $quantity <= 0
        || (float) $quantity > 1000000000) {
        auth_respond(['ok' => false, 'message' => 'Choose two different assets and enter a valid amount.'], 422);
    }

    $statement = $pdo->prepare(
        'SELECT a.id, a.symbol, a.name, a.asset_type, a.quote_currency, '
        . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS price, '
        . '(SELECT p.price_source FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quote_source, '
        . '(SELECT p.observed_at FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quoted_at '
        . 'FROM assets a WHERE a.symbol IN (:from_symbol, :to_symbol) AND a.status = \'active\''
    );
    $statement->execute(['from_symbol' => $fromSymbol, 'to_symbol' => $toSymbol]);
    $assets = [];
    foreach ($statement->fetchAll() as $asset) {
        $assets[$asset['symbol']] = $asset;
    }
    if (!isset($assets[$fromSymbol], $assets[$toSymbol])
        || $assets[$fromSymbol]['asset_type'] !== 'crypto'
        || $assets[$toSymbol]['asset_type'] !== 'crypto') {
        auth_respond(['ok' => false, 'message' => 'Choose supported crypto assets.'], 422);
    }
    $from = $assets[$fromSymbol];
    $to = $assets[$toSymbol];
    if ($from['quote_currency'] !== 'USD' || $to['quote_currency'] !== 'USD'
        || $from['price'] === null || $to['price'] === null
        || (float) $from['price'] <= 0 || (float) $to['price'] <= 0) {
        auth_respond(['ok' => false, 'message' => 'A stored quote is not available for this pair.'], 409);
    }

    $input = (float) $quantity;
    $fromPrice = (float) $from['price'];
    $toPrice = (float) $to['price'];
    return [
        'from' => $from,
        'to' => $to,
        'input_quantity' => $input,
        'output_quantity' => round($input * $fromPrice / $toPrice, 12),
        'rate' => $fromPrice / $toPrice,
        'notional_usd' => round($input * $fromPrice, 8),
        'fee_usd' => 0.0,
        'is_demo_quote' => str_starts_with((string) $from['quote_source'], 'demo-')
            || str_starts_with((string) $to['quote_source'], 'demo-'),
    ];
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'GET') {
        $fromSymbol = trim((string) ($_GET['from'] ?? ''));
        $toSymbol = trim((string) ($_GET['to'] ?? ''));
        $quantity = trim((string) ($_GET['amount'] ?? ''));
        if ($fromSymbol === '' && $toSymbol === '' && $quantity === '') {
            $statement = $pdo->prepare(
                'SELECT s.id, f.symbol AS from_symbol, t.symbol AS to_symbol, s.input_quantity, s.output_quantity, '
                . 's.notional_usd, s.fee_usd, s.status, s.created_at '
                . 'FROM swap_simulations s JOIN assets f ON f.id = s.from_asset_id JOIN assets t ON t.id = s.to_asset_id '
                . 'WHERE s.user_id = :user_id ORDER BY s.created_at DESC, s.id DESC LIMIT 50'
            );
            $statement->execute(['user_id' => $user['id']]);
            auth_respond(['ok' => true, 'simulations' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'from' => $row['from_symbol'],
                'to' => $row['to_symbol'],
                'input_quantity' => (float) $row['input_quantity'],
                'output_quantity' => (float) $row['output_quantity'],
                'notional_usd' => (float) $row['notional_usd'],
                'fee_usd' => (float) $row['fee_usd'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
            ], $statement->fetchAll())]);
        }
        if ($fromSymbol === '' || $toSymbol === '' || $quantity === '') {
            auth_respond(['ok' => false, 'message' => 'Choose a pair and enter an amount to preview a swap.'], 422);
        }
        $quote = swap_quote($pdo, $fromSymbol, $toSymbol, $quantity);
        auth_respond(['ok' => true, 'quote' => [
            'from' => $quote['from']['symbol'],
            'to' => $quote['to']['symbol'],
            'input_quantity' => $quote['input_quantity'],
            'output_quantity' => $quote['output_quantity'],
            'rate' => $quote['rate'],
            'notional_usd' => $quote['notional_usd'],
            'fee_usd' => $quote['fee_usd'],
            'from_quote_source' => $quote['from']['quote_source'],
            'to_quote_source' => $quote['to']['quote_source'],
            'from_quoted_at' => $quote['from']['quoted_at'],
            'to_quoted_at' => $quote['to']['quoted_at'],
            'is_demo_quote' => $quote['is_demo_quote'],
            'execution_enabled' => false,
        ]]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $quote = swap_quote($pdo, (string) ($data['from'] ?? ''), (string) ($data['to'] ?? ''), (string) ($data['amount'] ?? ''));
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
    $insert = $pdo->prepare(
        'INSERT INTO swap_simulations (public_id, user_id, account_id, from_asset_id, to_asset_id, input_quantity, output_quantity, from_price, to_price, notional_usd, fee_usd, idempotency_key) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, :account_id, :from_asset_id, :to_asset_id, :input_quantity, :output_quantity, :from_price, :to_price, :notional_usd, 0, :idempotency_key) '
        . 'ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'account_id' => $accountId,
        'from_asset_id' => $quote['from']['id'],
        'to_asset_id' => $quote['to']['id'],
        'input_quantity' => number_format($quote['input_quantity'], 12, '.', ''),
        'output_quantity' => number_format($quote['output_quantity'], 12, '.', ''),
        'from_price' => number_format((float) $quote['from']['price'], 10, '.', ''),
        'to_price' => number_format((float) $quote['to']['price'], 10, '.', ''),
        'notional_usd' => number_format($quote['notional_usd'], 8, '.', ''),
        'idempotency_key' => $idempotencyKey === '' ? null : $idempotencyKey,
    ]);
    auth_respond([
        'ok' => true,
        'simulation_id' => (int) $pdo->lastInsertId(),
        'from' => $quote['from']['symbol'],
        'to' => $quote['to']['symbol'],
        'input_quantity' => $quote['input_quantity'],
        'output_quantity' => $quote['output_quantity'],
        'status' => 'simulated',
        'execution_enabled' => false,
        'message' => 'Swap preview saved. No tokens or balances were moved.',
    ], 201);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Swap preview service unavailable.'], 503);
}