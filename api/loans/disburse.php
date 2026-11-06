<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
auth_require_method('POST');

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $data = auth_request_data();
    auth_require_csrf($data);
    $loanId = filter_var($data['loan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($loanId === false || $loanId === null) {
        auth_respond(['ok' => false, 'message' => 'A valid loan is required.'], 422);
    }

    $pdo->beginTransaction();
    $loanQuery = $pdo->prepare(
        'SELECT id, approved_amount, currency, status FROM loan_applications '
        . 'WHERE id = :id AND user_id = :user_id FOR UPDATE'
    );
    $loanQuery->execute(['id' => $loanId, 'user_id' => $user['id']]);
    $loan = $loanQuery->fetch();
    if (!$loan) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Loan application not found.'], 404);
    }
    if ($loan['status'] !== 'approved' || $loan['approved_amount'] === null || (float) $loan['approved_amount'] <= 0) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Only approved loans can be transferred to cash.'], 409);
    }
    $accountQuery = $pdo->prepare(
        'SELECT id, ledger_account_id, currency FROM financial_accounts '
        . 'WHERE user_id = :user_id AND account_type IN (\'checking\', \'savings\') AND status = \'active\' ORDER BY id LIMIT 1 FOR UPDATE'
    );
    $accountQuery->execute(['user_id' => $user['id']]);
    $account = $accountQuery->fetch();
    if (!$account || $account['currency'] !== $loan['currency']) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'A matching cash account is not available.'], 409);
    }
    $amount = number_format((float) $loan['approved_amount'], 2, '.', '');
    $idempotencyKey = 'loan-disbursement-' . (int) $loanId;
    $transaction = $pdo->prepare(
        'INSERT INTO financial_transactions (public_id, user_id, transaction_type, status, amount, currency, description, idempotency_key, metadata, occurred_at) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, \'loan_disbursement\', \'completed\', :amount, :currency, \'Approved loan disbursement\', :idempotency_key, JSON_OBJECT(\'loan_id\', :loan_id), UTC_TIMESTAMP(6))'
    );
    $transaction->execute([
        'user_id' => $user['id'],
        'amount' => $amount,
        'currency' => $loan['currency'],
        'idempotency_key' => $idempotencyKey,
        'loan_id' => (int) $loanId,
    ]);
    $transactionId = (int) $pdo->lastInsertId();
    $clearingCode = 'loan-funding-' . strtolower($loan['currency']);
    $clearingQuery = $pdo->prepare('SELECT id FROM ledger_accounts WHERE owner_user_id = :user_id AND account_code = :code FOR UPDATE');
    $clearingQuery->execute(['user_id' => $user['id'], 'code' => $clearingCode]);
    $clearingId = $clearingQuery->fetchColumn();
    if (!$clearingId) {
        $insertClearing = $pdo->prepare(
            'INSERT INTO ledger_accounts (owner_user_id, account_code, account_type, currency) '
            . 'VALUES (:user_id, :code, \'clearing\', :currency)'
        );
        $insertClearing->execute(['user_id' => $user['id'], 'code' => $clearingCode, 'currency' => $loan['currency']]);
        $clearingId = $pdo->lastInsertId();
    }
    $entries = $pdo->prepare(
        'INSERT INTO ledger_entries (transaction_id, ledger_account_id, entry_side, amount, currency) '
        . 'VALUES (:transaction_id, :ledger_account_id, :entry_side, :amount, :currency)'
    );
    $entries->execute([
        'transaction_id' => $transactionId,
        'ledger_account_id' => $account['ledger_account_id'],
        'entry_side' => 'debit',
        'amount' => $amount,
        'currency' => $loan['currency'],
    ]);
    $entries->execute([
        'transaction_id' => $transactionId,
        'ledger_account_id' => $clearingId,
        'entry_side' => 'credit',
        'amount' => $amount,
        'currency' => $loan['currency'],
    ]);
    $pdo->prepare('UPDATE loan_applications SET status = \'funded\' WHERE id = :id')->execute(['id' => $loanId]);
    $audit = $pdo->prepare(
        'INSERT INTO audit_events (actor_user_id, action, entity_type, entity_id, before_data, after_data) '
        . 'VALUES (:actor, \'loan_disbursement_accepted\', \'loan_application\', :entity_id, JSON_OBJECT(\'status\', \'approved\'), JSON_OBJECT(\'status\', \'funded\', \'amount\', :amount))'
    );
    $audit->execute(['actor' => $user['id'], 'entity_id' => (string) $loanId, 'amount' => (float) $amount]);
    auth_record_security_event($pdo, (int) $user['id'], 'loan_disbursement_accepted', 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'loan_id' => (int) $loanId, 'transaction_id' => $transactionId, 'amount' => (float) $amount, 'status' => 'funded'], 201);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => $error->getCode() === '23000' ? 'This loan has already been transferred.' : 'Loan transfer failed.'], $error->getCode() === '23000' ? 409 : 503);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Loan transfer service unavailable.'], 503);
}