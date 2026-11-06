<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
require __DIR__ . '/../mail.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'PATCH'], true)) {
    header('Allow: GET, PATCH');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT cm.id, cm.transaction_id, cm.account_id, cm.movement_type, cm.status, cm.amount, cm.currency, '
            . 'cm.provider_reference, cm.initiated_at, ft.description, ft.status AS transaction_status, '
            . 'u.id AS user_id, u.email, p.withdrawal_details, p.withdrawal_wallet '
            . 'FROM cash_movements cm JOIN financial_transactions ft ON ft.id = cm.transaction_id '
            . 'JOIN users u ON u.id = ft.user_id LEFT JOIN user_profiles p ON p.user_id = u.id '
            . 'ORDER BY cm.initiated_at DESC LIMIT 500'
        );
        $requests = array_map(static function (array $row): array {
            $details = is_string($row['withdrawal_details']) ? json_decode($row['withdrawal_details'], true) : [];
            if (!is_array($details) || !$details) {
                $legacyWallet = trim((string) ($row['withdrawal_wallet'] ?? ''));
                $details = $legacyWallet === '' ? [] : ['preferred_method' => 'crypto', 'crypto_wallet' => $legacyWallet];
            }
            return [
                'id' => (int) $row['id'],
                'transaction_id' => (int) $row['transaction_id'],
                'account_id' => (int) $row['account_id'],
                'user_id' => (int) $row['user_id'],
                'email' => $row['email'],
                'type' => $row['movement_type'],
                'status' => $row['status'],
                'transaction_status' => $row['transaction_status'],
                'amount' => (float) $row['amount'],
                'currency' => $row['currency'],
                'reference' => $row['provider_reference'],
                'description' => $row['description'],
                'created_at' => $row['initiated_at'],
                'withdrawal_details' => $row['movement_type'] === 'withdrawal' ? $details : [],
            ];
        }, $statement->fetchAll());
        auth_respond(['ok' => true, 'requests' => $requests]);
    }

    $data = auth_request_data();
    admin_require_write($data);
    $movementId = filter_var($data['movement_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $data['action'] ?? '';
    $reason = trim((string) ($data['reason'] ?? ''));
    $reference = trim((string) ($data['provider_reference'] ?? ''));
    $settlementConfirmed = ($data['settlement_confirmed'] ?? false) === true;
    if ($action === 'approve' && $settlementConfirmed && $reason === '') {
        $reason = 'Admin confirmed external settlement.';
    }
    $storedReference = $reference === '' ? null : $reference;
    $approvalMissingSettlement = $action === 'approve' && !$settlementConfirmed && $reference === '';
    $approvalReferenceTooLong = $action === 'approve' && strlen($reference) > 128;
    if ($movementId === false || $movementId === null || !in_array($action, ['approve', 'reject'], true)
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500
        || $approvalMissingSettlement || $approvalReferenceTooLong) {
        auth_respond(['ok' => false, 'message' => 'Provide a valid request and review reason, plus a settlement reference or explicit settlement confirmation when approving.'], 422);
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare(
        'SELECT cm.id, cm.account_id, cm.movement_type, cm.status, cm.amount, cm.currency, cm.provider_reference, '
        . 'ft.id AS transaction_id, ft.user_id, u.email, ft.status AS transaction_status, fa.ledger_account_id '
        . 'FROM cash_movements cm JOIN financial_transactions ft ON ft.id = cm.transaction_id '
        . 'JOIN financial_accounts fa ON fa.id = cm.account_id JOIN users u ON u.id = ft.user_id '
        . 'WHERE cm.id = :id FOR UPDATE'
    );
    $lock->execute(['id' => $movementId]);
    $movement = $lock->fetch();
    if (!$movement) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Cash request not found.'], 404);
    }
    if ($movement['status'] !== 'pending' || $movement['transaction_status'] !== 'pending') {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'This cash request has already been reviewed.'], 409);
    }

    $before = [
        'status' => $movement['status'],
        'transaction_status' => $movement['transaction_status'],
        'amount' => (float) $movement['amount'],
        'type' => $movement['movement_type'],
    ];
    if ($action === 'reject') {
        $pdo->prepare('UPDATE cash_movements SET status = \'rejected\' WHERE id = :id')->execute(['id' => $movementId]);
        $pdo->prepare('UPDATE financial_transactions SET status = \'failed\', metadata = JSON_OBJECT(\'admin_reason\', :reason) WHERE id = :id')
            ->execute(['reason' => $reason, 'id' => $movement['transaction_id']]);
        $after = ['status' => 'rejected', 'reason' => $reason];
    } else {
        if ($movement['movement_type'] === 'withdrawal') {
            $account = admin_cash_account($pdo, (int) $movement['user_id'], true);
            if (!$account || (int) $account['ledger_account_id'] !== (int) $movement['ledger_account_id']) {
                $pdo->rollBack();
                auth_respond(['ok' => false, 'message' => 'The cash account is no longer available.'], 409);
            }
            $balance = admin_cash_balance($pdo, (int) $movement['ledger_account_id']);
            if ((float) $movement['amount'] > $balance) {
                $pdo->rollBack();
                auth_respond(['ok' => false, 'message' => 'Available cash is now below this withdrawal amount.'], 409);
            }
        }
        $clearingId = admin_clearing_account($pdo, (int) $movement['user_id'], $movement['currency']);
        admin_post_cash_ledger_entries(
            $pdo,
            (int) $movement['transaction_id'],
            (int) $movement['ledger_account_id'],
            $clearingId,
            $movement['movement_type'] === 'deposit' ? 'credit' : 'debit',
            number_format((float) $movement['amount'], 2, '.', ''),
            $movement['currency']
        );
        $updateMovement = $pdo->prepare(
            'UPDATE cash_movements SET status = \'completed\', provider_reference = :reference, completed_at = UTC_TIMESTAMP(6) WHERE id = :id'
        );
        $updateMovement->execute(['reference' => $storedReference, 'id' => $movementId]);
        $updateTransaction = $pdo->prepare(
            'UPDATE financial_transactions SET status = \'completed\', external_reference = :reference, occurred_at = COALESCE(occurred_at, UTC_TIMESTAMP(6)), '
            . 'metadata = JSON_OBJECT(\'admin_reason\', :reason) WHERE id = :id'
        );
        $updateTransaction->execute(['reference' => $storedReference, 'reason' => $reason, 'id' => $movement['transaction_id']]);
        $after = ['status' => 'completed', 'provider_reference' => $storedReference, 'reason' => $reason];
    }

    admin_record_audit($pdo, (int) $admin['id'], 'cash_request_' . $action, 'cash_movement', (int) $movementId, $before, $after);
    $notification = $pdo->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, body, payload) '
        . 'VALUES (:user_id, \'cash_request\', :title, :body, :payload)'
    );
    $notification->execute([
        'user_id' => $movement['user_id'],
        'title' => $action === 'approve' ? 'Cash request completed' : 'Cash request rejected',
        'body' => $action === 'approve' ? 'Your cash request was reviewed and recorded.' : 'Your cash request was rejected.',
        'payload' => json_encode(['movement_id' => (int) $movementId, 'reason' => $reason], JSON_UNESCAPED_SLASHES),
    ]);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_cash_request_' . $action, 'success');
    $pdo->commit();
    cladvest_send_cash_email($movement['email'], $movement['movement_type'], $action === 'approve' ? 'completed' : 'rejected', number_format((float) $movement['amount'], 2) . ' ' . $movement['currency']);
    auth_respond(['ok' => true, 'movement_id' => (int) $movementId, 'status' => $action === 'approve' ? 'completed' : 'rejected']);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Cash review service unavailable.'], 503);
}