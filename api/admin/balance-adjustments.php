<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('POST');

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    $data = auth_request_data();
    admin_require_write($data);
    $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $direction = $data['direction'] ?? '';
    $amount = trim((string) ($data['amount'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));
    $idempotencyKey = trim((string) ($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ''));
    if ($userId === false || $userId === null || !in_array($direction, ['credit', 'debit'], true)
        || !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $amount) || (float) $amount < 0.01 || (float) $amount > 10000000
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500
        || !preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $idempotencyKey)) {
        auth_respond(['ok' => false, 'message' => 'Enter a user, adjustment direction, amount, reason, and request identifier.'], 422);
    }

    $pdo->beginTransaction();
    $account = admin_cash_account($pdo, (int) $userId, true);
    if (!$account || $account['currency'] !== 'USD') {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'An active USD cash account is required.'], 409);
    }
    $beforeBalance = admin_cash_balance($pdo, (int) $account['ledger_account_id']);
    if ($direction === 'debit' && (float) $amount > $beforeBalance) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'The adjustment is larger than the available cash balance.'], 409);
    }
    $transaction = $pdo->prepare(
        'INSERT INTO financial_transactions (public_id, user_id, transaction_type, status, amount, currency, description, idempotency_key, metadata, occurred_at) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, \'adjustment\', \'completed\', :amount, \'USD\', :description, :idempotency_key, JSON_OBJECT(\'admin_reason\', :reason), UTC_TIMESTAMP(6))'
    );
    $transaction->execute([
        'user_id' => $userId,
        'amount' => number_format((float) $amount, 2, '.', ''),
        'description' => 'Administrator balance adjustment',
        'idempotency_key' => $idempotencyKey,
        'reason' => $reason,
    ]);
    $transactionId = (int) $pdo->lastInsertId();
    $clearingId = admin_clearing_account($pdo, (int) $userId, 'USD');
    admin_post_cash_ledger_entries(
        $pdo,
        $transactionId,
        (int) $account['ledger_account_id'],
        $clearingId,
        $direction,
        number_format((float) $amount, 2, '.', ''),
        'USD'
    );
    $afterBalance = admin_cash_balance($pdo, (int) $account['ledger_account_id']);
    admin_record_audit($pdo, (int) $admin['id'], 'balance_' . $direction, 'user', (int) $userId, ['cash_balance' => $beforeBalance], [
        'cash_balance' => $afterBalance,
        'amount' => (float) $amount,
        'reason' => $reason,
        'transaction_id' => $transactionId,
    ]);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_balance_' . $direction, 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'transaction_id' => $transactionId, 'balance' => round($afterBalance, 2)]);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => $error->getCode() === '23000' ? 'This adjustment request was already applied.' : 'Balance adjustment failed.'], $error->getCode() === '23000' ? 409 : 503);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Balance adjustment service unavailable.'], 503);
}