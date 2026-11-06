<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('GET');

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    $usersQuery = $pdo->query(
        'SELECT u.id, u.email, u.status, u.created_at, p.first_name, p.last_name, p.phone_country_code, p.phone_number, '
        . 'p.country_code, p.region, p.timezone, p.locale, p.withdrawal_details, p.withdrawal_wallet, '
        . '(SELECT COALESCE(SUM(CASE le.entry_side WHEN \'debit\' THEN le.amount ELSE -le.amount END), 0) '
        . 'FROM financial_accounts fa JOIN ledger_entries le ON le.ledger_account_id = fa.ledger_account_id '
        . 'WHERE fa.user_id = u.id AND fa.account_type IN (\'checking\', \'savings\') AND fa.status = \'active\') AS cash_balance, '
        . '(SELECT COALESCE(SUM(ip.quantity * COALESCE((SELECT ap.price FROM asset_prices ap WHERE ap.asset_id = ip.asset_id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1), ip.average_cost) - ip.cost_basis), 0) '
        . 'FROM investment_positions ip JOIN financial_accounts fa ON fa.id = ip.account_id '
        . 'WHERE fa.user_id = u.id AND fa.status = \'active\') AS investment_profit '
        . 'FROM users u LEFT JOIN user_profiles p ON p.user_id = u.id ORDER BY u.created_at DESC LIMIT 500'
    );
    $users = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'email' => $row['email'],
        'status' => $row['status'],
        'first_name' => $row['first_name'],
        'last_name' => $row['last_name'],
        'phone_country_code' => $row['phone_country_code'],
        'phone_number' => $row['phone_number'],
        'country_code' => $row['country_code'],
        'region' => $row['region'],
        'timezone' => $row['timezone'],
        'locale' => $row['locale'],
        'withdrawal_details' => is_string($row['withdrawal_details'] ?? null) ? (json_decode($row['withdrawal_details'], true) ?: []) : ($row['withdrawal_wallet'] ? ['preferred_method' => 'crypto', 'crypto_wallet' => $row['withdrawal_wallet']] : []),
        'cash_balance' => (float) $row['cash_balance'],
        'investment_profit' => (float) $row['investment_profit'],
        'created_at' => $row['created_at'],
    ], $usersQuery->fetchAll());

    $locationsQuery = $pdo->query(
        'SELECT p.country_code, COUNT(*) AS user_count FROM users u '
        . 'LEFT JOIN user_profiles p ON p.user_id = u.id GROUP BY p.country_code '
        . 'ORDER BY user_count DESC, p.country_code ASC'
    );
    $userLocations = array_map(static fn (array $row): array => [
        'country_code' => $row['country_code'],
        'user_count' => (int) $row['user_count'],
    ], $locationsQuery->fetchAll());

    $moneyQuery = $pdo->query(
        'SELECT cm.id, cm.transaction_id, cm.account_id, cm.movement_type, cm.status, cm.amount, cm.currency, '
        . 'cm.initiated_at, cm.provider_reference, ft.description, u.id AS user_id, u.email '
        . 'FROM cash_movements cm JOIN financial_transactions ft ON ft.id = cm.transaction_id '
        . 'JOIN users u ON u.id = ft.user_id ORDER BY cm.initiated_at DESC LIMIT 500'
    );
    $moneyRequests = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'transaction_id' => (int) $row['transaction_id'],
        'user_id' => (int) $row['user_id'],
        'email' => $row['email'],
        'type' => $row['movement_type'],
        'status' => $row['status'],
        'amount' => (float) $row['amount'],
        'currency' => $row['currency'],
        'description' => $row['description'],
        'provider_reference' => $row['provider_reference'],
        'created_at' => $row['initiated_at'],
    ], $moneyQuery->fetchAll());

    $loansQuery = $pdo->query(
        'SELECT l.id, l.public_id, l.user_id, u.email, l.requested_amount, l.approved_amount, l.currency, '
        . 'l.term_months, l.apr, l.status, l.submitted_at, l.decision_at '
        . 'FROM loan_applications l JOIN users u ON u.id = l.user_id '
        . 'ORDER BY l.created_at DESC LIMIT 500'
    );
    $loans = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'public_id' => bin2hex($row['public_id']),
        'user_id' => (int) $row['user_id'],
        'email' => $row['email'],
        'requested_amount' => (float) $row['requested_amount'],
        'approved_amount' => $row['approved_amount'] === null ? null : (float) $row['approved_amount'],
        'currency' => $row['currency'],
        'term_months' => (int) $row['term_months'],
        'apr' => $row['apr'] === null ? null : (float) $row['apr'],
        'status' => $row['status'],
        'submitted_at' => $row['submitted_at'],
        'decision_at' => $row['decision_at'],
    ], $loansQuery->fetchAll());

    $supportQuery = $pdo->query(
        'SELECT c.id, c.user_id, u.email, c.subject, c.category, c.status, c.priority, c.assigned_agent_id, c.updated_at, '
        . '(SELECT m.message_text FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC, m.id DESC LIMIT 1) AS last_message '
        . 'FROM support_conversations c JOIN users u ON u.id = c.user_id ORDER BY c.updated_at DESC LIMIT 500'
    );
    $conversations = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'email' => $row['email'],
        'subject' => $row['subject'],
        'category' => $row['category'],
        'status' => $row['status'],
        'priority' => $row['priority'],
        'assigned_agent_id' => $row['assigned_agent_id'] === null ? null : (int) $row['assigned_agent_id'],
        'last_message' => $row['last_message'],
        'updated_at' => $row['updated_at'],
    ], $supportQuery->fetchAll());

    $positionsQuery = $pdo->query(
        'SELECT ip.id, fa.user_id, u.email, ip.account_id, a.id AS asset_id, a.symbol, a.name, ip.quantity, ip.average_cost, ip.cost_basis, '
        . '(SELECT ap.price FROM asset_prices ap WHERE ap.asset_id = a.id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1) AS latest_price '
        . 'FROM investment_positions ip JOIN financial_accounts fa ON fa.id = ip.account_id '
        . 'JOIN users u ON u.id = fa.user_id JOIN assets a ON a.id = ip.asset_id ORDER BY u.email, a.symbol LIMIT 1000'
    );
    $positions = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'user_id' => (int) $row['user_id'],
        'email' => $row['email'],
        'account_id' => (int) $row['account_id'],
        'asset_id' => (int) $row['asset_id'],
        'symbol' => $row['symbol'],
        'name' => $row['name'],
        'quantity' => (float) $row['quantity'],
        'average_cost' => (float) $row['average_cost'],
        'cost_basis' => (float) $row['cost_basis'],
        'latest_price' => $row['latest_price'] === null ? null : (float) $row['latest_price'],
    ], $positionsQuery->fetchAll());

    $assetsQuery = $pdo->query(
        'SELECT a.id, a.symbol, a.name, a.asset_type, a.exchange_code, a.quote_currency, a.description, a.is_tradeable, a.status, '
        . '(SELECT ap.price FROM asset_prices ap WHERE ap.asset_id = a.id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1) AS latest_price, '
        . '(SELECT ap.price_source FROM asset_prices ap WHERE ap.asset_id = a.id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1) AS quote_source, '
        . '(SELECT ap.observed_at FROM asset_prices ap WHERE ap.asset_id = a.id ORDER BY ap.observed_at DESC, ap.id DESC LIMIT 1) AS quoted_at '
        . 'FROM assets a ORDER BY a.symbol'
    );
    $assets = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'symbol' => $row['symbol'],
        'name' => $row['name'],
        'type' => $row['asset_type'],
        'exchange' => $row['exchange_code'],
        'currency' => $row['quote_currency'],
        'description' => $row['description'],
        'tradeable' => (bool) $row['is_tradeable'],
        'status' => $row['status'],
        'price' => $row['latest_price'] === null ? null : (float) $row['latest_price'],
        'quote_source' => $row['quote_source'],
        'quoted_at' => $row['quoted_at'],
    ], $assetsQuery->fetchAll());

    auth_respond([
        'ok' => true,
        'admin' => ['id' => (int) $admin['id'], 'email' => $admin['email'], 'role' => $admin['admin_role']],
        'users' => $users,
        'user_locations' => $userLocations,
        'money_requests' => $moneyRequests,
        'loans' => $loans,
        'conversations' => $conversations,
        'positions' => $positions,
        'assets' => $assets,
    ]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Admin workspace service unavailable.'], 503);
}