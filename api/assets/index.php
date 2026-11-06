<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
auth_require_method('GET');

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $symbol = strtoupper(trim((string) ($_GET['symbol'] ?? '')));
    if ($symbol !== '' && !preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol)) {
        auth_respond(['ok' => false, 'message' => 'Enter a valid asset symbol.'], 422);
    }

    $statement = $pdo->prepare(
        'SELECT a.id, a.symbol, a.name, a.asset_type, a.exchange_code, a.quote_currency, a.description, a.is_tradeable, '
        . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS latest_price, '
        . '(SELECT p.price FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1 OFFSET 1) AS previous_price, '
        . '(SELECT p.price_source FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quote_source, '
        . '(SELECT p.observed_at FROM asset_prices p WHERE p.asset_id = a.id ORDER BY p.observed_at DESC, p.id DESC LIMIT 1) AS quoted_at, '
        . 'EXISTS (SELECT 1 FROM watchlist_items w WHERE w.asset_id = a.id AND w.user_id = :user_id) AS is_watchlisted '
        . 'FROM assets a WHERE a.status = \'active\' AND (:symbol_filter = \'\' OR a.symbol = :symbol_match) ORDER BY a.symbol'
    );
    $statement->execute([
        'user_id' => $user['id'],
        'symbol_filter' => $symbol,
        'symbol_match' => $symbol,
    ]);
    $assets = array_map(static function (array $row): array {
        $latest = $row['latest_price'] === null ? null : (float) $row['latest_price'];
        $previous = $row['previous_price'] === null ? null : (float) $row['previous_price'];
        return [
            'symbol' => $row['symbol'],
            'name' => $row['name'],
            'type' => $row['asset_type'],
            'exchange' => $row['exchange_code'],
            'currency' => $row['quote_currency'],
            'description' => $row['description'],
            'tradeable' => (bool) $row['is_tradeable'],
            'price' => $latest,
            'previous_price' => $previous,
            'change_percent' => $latest !== null && $previous !== null && $previous > 0
                ? round(($latest - $previous) / $previous * 100, 4)
                : null,
            'quote_source' => $row['quote_source'],
            'quoted_at' => $row['quoted_at'],
            'is_demo_quote' => is_string($row['quote_source']) && str_starts_with($row['quote_source'], 'demo-'),
            'watchlisted' => (bool) $row['is_watchlisted'],
        ];
    }, $statement->fetchAll());

    auth_respond(['ok' => true, 'assets' => $assets]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Investment data service unavailable.'], 503);
}