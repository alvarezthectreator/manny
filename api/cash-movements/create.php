<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
require __DIR__ . '/../mail.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

$type = $data['type'] ?? '';
$channelInput = trim((string) ($data['method'] ?? $data['channel_key'] ?? ''));
$amountInput = (string) ($data['amount'] ?? '');
$transactionDate = (string) ($data['transaction_date'] ?? '');
$idempotencyKey = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? $data['idempotency_key'] ?? ''));
if (!in_array($type, ['deposit', 'withdrawal'], true) || $channelInput === '' || mb_strlen($channelInput) > 100) {
    auth_respond(['ok' => false, 'message' => 'Choose a payment method enabled for this request.'], 422);
}
if (!preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amountInput)) {
    auth_respond(['ok' => false, 'message' => 'Enter an amount with no more than two decimal places.'], 422);
}
$amount = (float) $amountInput;
if ($amount < 1 || $amount > 100000) {
    auth_respond(['ok' => false, 'message' => 'Enter an amount from $1 to $100,000.'], 422);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transactionDate)) {
    auth_respond(['ok' => false, 'message' => 'Choose a valid transaction date.'], 422);
}
[$year, $month, $day] = array_map('intval', explode('-', $transactionDate));
if (!checkdate($month, $day, $year) || $transactionDate > gmdate('Y-m-d')) {
    auth_respond(['ok' => false, 'message' => 'Choose a valid date that is not in the future.'], 422);
}
if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
    auth_respond(['ok' => false, 'message' => 'Request identifier is missing. Please try again.'], 422);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $existing = $pdo->prepare(
        'SELECT id, transaction_type, status FROM financial_transactions WHERE user_id = :user_id AND idempotency_key = :idempotency_key LIMIT 1'
    );
    $existing->execute(['user_id' => $user['id'], 'idempotency_key' => $idempotencyKey]);
    if ($transaction = $existing->fetch()) {
        auth_respond(['ok' => true, 'transaction_id' => (int) $transaction['id'], 'duplicate' => true]);
    }

    $channelField = $type === 'deposit' ? 'deposit_enabled' : 'withdrawal_enabled';
    $channelQuery = $pdo->prepare(
        'SELECT id, display_name FROM payment_channels WHERE (channel_key = :channel_key OR display_name = :display_name) '
        . 'AND is_active = 1 AND ' . $channelField . ' = 1 LIMIT 1'
    );
    $channelQuery->execute(['channel_key' => strtolower($channelInput), 'display_name' => $channelInput]);
    $channel = $channelQuery->fetch();
    if (!$channel) {
        auth_respond(['ok' => false, 'message' => 'That payment method is currently unavailable.'], 422);
    }

    $pdo->beginTransaction();
    $accountQuery = $pdo->prepare(
        'SELECT id, ledger_account_id FROM financial_accounts '
        . 'WHERE user_id = :user_id AND account_type IN (\'checking\', \'savings\') AND status = \'active\' '
        . 'ORDER BY id LIMIT 1 FOR UPDATE'
    );
    $accountQuery->execute(['user_id' => $user['id']]);
    $account = $accountQuery->fetch();
    if (!$account) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'No active cash account is available.'], 409);
    }

    $balanceQuery = $pdo->prepare(
        'SELECT COALESCE(SUM(CASE entry_side WHEN \'debit\' THEN amount ELSE -amount END), 0) '
        . 'FROM ledger_entries WHERE ledger_account_id = :ledger_account_id'
    );
    $balanceQuery->execute(['ledger_account_id' => $account['ledger_account_id']]);
    $balance = (float) $balanceQuery->fetchColumn();
    if ($type === 'withdrawal' && $amount > $balance) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'That amount is above your available cash balance.'], 409);
    }

    $description = $channel['display_name'] . ' (pending admin review)';
    $transactionInsert = $pdo->prepare(
        'INSERT INTO financial_transactions '
        . '(public_id, user_id, transaction_type, status, amount, currency, description, idempotency_key, occurred_at) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, :transaction_type, \'pending\', :amount, \'USD\', :description, :idempotency_key, :occurred_at)'
    );
    $transactionInsert->execute([
        'user_id' => $user['id'],
        'transaction_type' => $type,
        'amount' => number_format($amount, 2, '.', ''),
        'description' => $description,
        'idempotency_key' => $idempotencyKey,
        'occurred_at' => $transactionDate . ' 00:00:00.000000',
    ]);
    $transactionId = (int) $pdo->lastInsertId();

    $movementInsert = $pdo->prepare(
        'INSERT INTO cash_movements (transaction_id, account_id, payment_channel_id, movement_type, status, amount, currency, initiated_at, completed_at) '
        . 'VALUES (:transaction_id, :account_id, :payment_channel_id, :movement_type, \'pending\', :amount, \'USD\', :initiated_at, NULL)'
    );
    $movementInsert->execute([
        'transaction_id' => $transactionId,
        'account_id' => $account['id'],
        'payment_channel_id' => $channel['id'],
        'movement_type' => $type === 'deposit' ? 'deposit' : 'withdrawal',
        'amount' => number_format($amount, 2, '.', ''),
        'initiated_at' => $transactionDate . ' 00:00:00.000000',
    ]);

    auth_record_security_event($pdo, (int) $user['id'], 'cash_movement_' . $type . '_requested', 'success');
    $pdo->commit();

    cladvest_send_cash_email($user['email'], $type, 'pending', number_format($amount, 2) . ' USD');
    auth_respond(['ok' => true, 'transaction_id' => $transactionId, 'status' => 'pending', 'balance' => round($balance, 2)], 201);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond([
        'ok' => false,
        'message' => $error->getCode() === '23000' ? 'This request was already recorded.' : 'Could not record this simulated cash movement.',
    ], $error->getCode() === '23000' ? 409 : 500);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Cash movement service unavailable.'], 503);
}