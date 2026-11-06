<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'DELETE'], true)) {
    header('Allow: GET, POST, DELETE');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT a.symbol, a.name, a.asset_type, a.quote_currency, '
            . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS price, '
            . '(SELECT p.price_source FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quote_source, '
            . '(SELECT p.observed_at FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quoted_at '
            . 'FROM watchlist_items w JOIN assets a ON a.id = w.asset_id '
            . 'WHERE w.user_id = :user_id AND a.status = \'active\' ORDER BY w.added_at DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $assets = array_map(static fn (array $row): array => [
            'symbol' => $row['symbol'],
            'name' => $row['name'],
            'type' => $row['asset_type'],
            'currency' => $row['quote_currency'],
            'price' => $row['price'] === null ? null : (float) $row['price'],
            'quote_source' => $row['quote_source'],
            'quoted_at' => $row['quoted_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'watchlist' => $assets]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
    if (!preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol)) {
        auth_respond(['ok' => false, 'message' => 'Enter a valid asset symbol.'], 422);
    }
    $assetQuery = $pdo->prepare('SELECT id FROM assets WHERE symbol = :symbol AND status = \'active\' LIMIT 1');
    $assetQuery->execute(['symbol' => $symbol]);
    $assetId = $assetQuery->fetchColumn();
    if (!$assetId) {
        auth_respond(['ok' => false, 'message' => 'Asset not found.'], 404);
    }

    if ($method === 'POST') {
        $statement = $pdo->prepare('INSERT IGNORE INTO watchlist_items (user_id, asset_id) VALUES (:user_id, :asset_id)');
        $statement->execute(['user_id' => $user['id'], 'asset_id' => $assetId]);
        auth_respond(['ok' => true, 'symbol' => $symbol, 'watchlisted' => true], 201);
    }

    $statement = $pdo->prepare('DELETE FROM watchlist_items WHERE user_id = :user_id AND asset_id = :asset_id');
    $statement->execute(['user_id' => $user['id'], 'asset_id' => $assetId]);
    auth_respond(['ok' => true, 'symbol' => $symbol, 'watchlisted' => false]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Watchlist service unavailable.'], 503);
}