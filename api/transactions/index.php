<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
auth_require_method('GET');

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $limit = filter_var($_GET['limit'] ?? 50, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($limit === false) {
        auth_respond(['ok' => false, 'message' => 'Limit must be between 1 and 100.'], 422);
    }
    $statement = $pdo->prepare(
        'SELECT ft.id, ft.public_id, ft.transaction_type, ft.status, ft.amount, ft.currency, ft.description, '
        . 'ft.created_at, COALESCE(ft.occurred_at, ft.created_at) AS transaction_date, cm.id AS cash_movement_id '
        . 'FROM financial_transactions ft LEFT JOIN cash_movements cm ON cm.transaction_id = ft.id '
        . 'WHERE ft.user_id = :user_id ORDER BY COALESCE(ft.occurred_at, ft.created_at) DESC, ft.id DESC LIMIT ' . (int) $limit
    );
    $statement->execute(['user_id' => $user['id']]);
    $transactions = array_map(static function (array $row): array {
        $type = match ($row['transaction_type']) {
            'deposit' => 'Deposit',
            'withdrawal' => 'Withdrawal',
            'buy', 'sell' => 'Investment',
            'loan_disbursement', 'loan_payment' => 'Loan',
            default => ucfirst(str_replace('_', ' ', $row['transaction_type'])),
        };
        $amount = (float) $row['amount'];
        if (in_array($row['transaction_type'], ['withdrawal', 'buy', 'fee', 'loan_payment'], true)) {
            $amount *= -1;
        }
        return [
            'id' => (int) $row['id'],
            'receipt_number' => 'CV-' . strtoupper(bin2hex($row['public_id'])),
            'has_receipt' => $row['cash_movement_id'] !== null,
            'type' => $type,
            'detail' => $row['description'] ?? $type,
            'date' => $row['transaction_date'],
            'submitted_at' => $row['created_at'],
            'amount' => $amount,
            'currency' => $row['currency'],
            'status' => $row['status'] === 'completed' ? 'Recorded' : ucfirst($row['status']),
        ];
    }, $statement->fetchAll());

    auth_respond(['ok' => true, 'transactions' => $transactions]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Transaction service unavailable.'], 503);
}