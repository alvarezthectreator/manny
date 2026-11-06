<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
auth_require_method('GET');

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $statement = $pdo->prepare(
        'SELECT fa.id, fa.account_name, fa.account_type, fa.currency, '
        . 'COALESCE(SUM(CASE le.entry_side WHEN \'debit\' THEN le.amount ELSE -le.amount END), 0) AS balance '
        . 'FROM financial_accounts fa '
        . 'LEFT JOIN ledger_entries le ON le.ledger_account_id = fa.ledger_account_id '
        . 'WHERE fa.user_id = :user_id AND fa.status = \'active\' '
        . 'GROUP BY fa.id, fa.account_name, fa.account_type, fa.currency ORDER BY fa.id'
    );
    $statement->execute(['user_id' => $user['id']]);
    $accounts = $statement->fetchAll();
    $cash = 0.0;
    $invested = 0.0;
    foreach ($accounts as &$account) {
        $account['id'] = (int) $account['id'];
        $account['balance'] = (float) $account['balance'];
        if (in_array($account['account_type'], ['checking', 'savings'], true)) {
            $cash += $account['balance'];
        } elseif ($account['account_type'] === 'investment') {
            $invested += $account['balance'];
        }
    }
    unset($account);

    $positionsStatement = $pdo->prepare(
        'SELECT a.symbol, a.name, ip.quantity, ip.average_cost, ip.cost_basis, '
        . '(SELECT ap.price FROM asset_prices ap WHERE ap.asset_id = a.id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1) AS latest_price '
        . 'FROM investment_positions ip '
        . 'JOIN financial_accounts fa ON fa.id = ip.account_id '
        . 'JOIN assets a ON a.id = ip.asset_id '
        . 'WHERE fa.user_id = :user_id AND fa.status = \'active\' AND ip.quantity > 0 '
        . 'ORDER BY a.symbol'
    );
    $positionsStatement->execute(['user_id' => $user['id']]);
    $positions = $positionsStatement->fetchAll();
    foreach ($positions as &$position) {
        $position['quantity'] = (float) $position['quantity'];
        $position['average_cost'] = (float) $position['average_cost'];
        $position['cost_basis'] = (float) $position['cost_basis'];
        $position['latest_price'] = $position['latest_price'] === null ? null : (float) $position['latest_price'];
        $position['market_value'] = $position['latest_price'] === null ? null : $position['quantity'] * $position['latest_price'];
    }
    unset($position);

    auth_respond([
        'ok' => true,
        'accounts' => $accounts,
        'positions' => $positions,
        'summary' => [
            'cash_balance' => round($cash, 2),
            'invested_value' => round($invested, 2),
            'total_balance' => round($cash + $invested, 2),
            'account_count' => count($accounts),
        ],
    ]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Account service unavailable.'], 503);
}