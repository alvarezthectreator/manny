<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT id, requested_amount, approved_amount, currency, term_months, apr, status, created_at '
            . 'FROM loan_applications WHERE user_id = :user_id ORDER BY created_at DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $requests = array_map(static function (array $row): array {
            $amount = (float) $row['requested_amount'];
            $months = (int) $row['term_months'];
            $rate = (float) $row['apr'] / 1200;
            $payment = $rate > 0 ? $amount * $rate / (1 - (1 + $rate) ** -$months) : $amount / $months;
            return [
                'id' => (int) $row['id'],
                'amount' => $amount,
                'approvedAmount' => $row['approved_amount'] === null ? null : (float) $row['approved_amount'],
                'months' => $months,
                'payment' => round($payment, 2),
                'date' => $row['created_at'],
                'status' => ucfirst(str_replace('_', ' ', $row['status'])),
                'canDisburse' => $row['status'] === 'approved' && $row['approved_amount'] !== null && (float) $row['approved_amount'] > 0,
            ];
        }, $statement->fetchAll());
        auth_respond(['ok' => true, 'requests' => $requests]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $amount = (string) ($data['amount'] ?? '');
    $months = filter_var($data['months'] ?? null, FILTER_VALIDATE_INT);
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || (float) $amount < 500 || (float) $amount > 25000 || !in_array($months, [12, 24, 36, 60], true)) {
        auth_respond(['ok' => false, 'message' => 'Enter an amount from $500 to $25,000 and choose a supported repayment term.'], 422);
    }

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO loan_applications (public_id, user_id, requested_amount, currency, term_months, apr, status, submitted_at) '
        . 'VALUES (UNHEX(REPLACE(UUID(), \'-\', \'\')), :user_id, :amount, \'USD\', :months, 8.9, \'submitted\', UTC_TIMESTAMP(6))'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'amount' => number_format((float) $amount, 2, '.', ''),
        'months' => $months,
    ]);
    $loanId = (int) $pdo->lastInsertId();
    $monthlyRate = 8.9 / 1200;
    $payment = (float) $amount * $monthlyRate / (1 - (1 + $monthlyRate) ** -$months);
    $balance = (float) $amount;
    $dueDate = new DateTimeImmutable('first day of next month', new DateTimeZone('UTC'));
    $schedule = $pdo->prepare(
        'INSERT INTO loan_repayment_schedule (loan_application_id, installment_number, due_date, principal_due, interest_due) '
        . 'VALUES (:loan_id, :installment_number, :due_date, :principal_due, :interest_due)'
    );
    for ($installment = 1; $installment <= $months; $installment++) {
        $interest = round($balance * $monthlyRate, 2);
        $principal = $installment === $months ? $balance : min($balance, round($payment - $interest, 2));
        $schedule->execute([
            'loan_id' => $loanId,
            'installment_number' => $installment,
            'due_date' => $dueDate->modify('+' . ($installment - 1) . ' months')->format('Y-m-d'),
            'principal_due' => number_format($principal, 2, '.', ''),
            'interest_due' => number_format($interest, 2, '.', ''),
        ]);
        $balance = max(0, $balance - $principal);
    }
    $pdo->commit();
    auth_respond(['ok' => true, 'loan_id' => $loanId], 201);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Loan estimate service unavailable.'], 503);
}