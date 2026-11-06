<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
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
            'SELECT l.id, l.user_id, u.email, l.requested_amount, l.approved_amount, l.currency, l.term_months, '
            . 'l.apr, l.status, l.created_at, l.submitted_at, l.decision_at '
            . 'FROM loan_applications l JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 500'
        );
        $loans = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
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
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'loans' => $loans]);
    }

    $data = auth_request_data();
    admin_require_write($data);
    $loanId = filter_var($data['loan_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $action = $data['action'] ?? '';
    $reason = trim((string) ($data['reason'] ?? ''));
    $approvedAmount = trim((string) ($data['approved_amount'] ?? ''));
    if ($loanId === false || $loanId === null || !in_array($action, ['approve', 'decline'], true)
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500
        || ($action === 'approve' && !preg_match('/^\d{1,9}(?:\.\d{1,2})?$/', $approvedAmount))) {
        auth_respond(['ok' => false, 'message' => 'Provide a loan, decision, reason, and valid approved amount.'], 422);
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare(
        'SELECT id, user_id, requested_amount, currency, term_months, apr, status, approved_amount '
        . 'FROM loan_applications WHERE id = :id FOR UPDATE'
    );
    $lock->execute(['id' => $loanId]);
    $loan = $lock->fetch();
    if (!$loan) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Loan application not found.'], 404);
    }
    if (!in_array($loan['status'], ['submitted', 'under_review'], true)) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'This loan application has already been decided.'], 409);
    }
    if ($action === 'approve' && ((float) $approvedAmount <= 0 || (float) $approvedAmount > (float) $loan['requested_amount'])) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Approved amount must be positive and no greater than the request.'], 422);
    }

    $status = $action === 'approve' ? 'approved' : 'declined';
    $approvedValue = $action === 'approve' ? number_format((float) $approvedAmount, 2, '.', '') : null;
    $update = $pdo->prepare(
        'UPDATE loan_applications SET status = :status, approved_amount = :approved_amount, decision_at = UTC_TIMESTAMP(6) WHERE id = :id'
    );
    $update->execute(['status' => $status, 'approved_amount' => $approvedValue, 'id' => $loanId]);
    if ($action === 'approve') {
        $pdo->prepare('DELETE FROM loan_repayment_schedule WHERE loan_application_id = :loan_id')
            ->execute(['loan_id' => $loanId]);
        $months = (int) $loan['term_months'];
        $monthlyRate = (float) $loan['apr'] / 1200;
        $principalBalance = (float) $approvedValue;
        $payment = $monthlyRate > 0
            ? $principalBalance * $monthlyRate / (1 - (1 + $monthlyRate) ** -$months)
            : $principalBalance / $months;
        $dueDate = new DateTimeImmutable('first day of next month', new DateTimeZone('UTC'));
        $schedule = $pdo->prepare(
            'INSERT INTO loan_repayment_schedule (loan_application_id, installment_number, due_date, principal_due, interest_due) '
            . 'VALUES (:loan_id, :installment_number, :due_date, :principal_due, :interest_due)'
        );
        for ($installment = 1; $installment <= $months; $installment++) {
            $interest = round($principalBalance * $monthlyRate, 2);
            $principal = $installment === $months ? $principalBalance : min($principalBalance, round($payment - $interest, 2));
            $schedule->execute([
                'loan_id' => $loanId,
                'installment_number' => $installment,
                'due_date' => $dueDate->modify('+' . ($installment - 1) . ' months')->format('Y-m-d'),
                'principal_due' => number_format($principal, 2, '.', ''),
                'interest_due' => number_format($interest, 2, '.', ''),
            ]);
            $principalBalance = max(0, $principalBalance - $principal);
        }
    }
    $after = ['status' => $status, 'approved_amount' => $approvedValue === null ? null : (float) $approvedValue, 'reason' => $reason];
    admin_record_audit($pdo, (int) $admin['id'], 'loan_' . $action, 'loan_application', (int) $loanId, [
        'status' => $loan['status'],
        'requested_amount' => (float) $loan['requested_amount'],
    ], $after);
    $notification = $pdo->prepare(
        'INSERT INTO notifications (user_id, notification_type, title, body, payload) '
        . 'VALUES (:user_id, \'loan_decision\', :title, :body, :payload)'
    );
    $notification->execute([
        'user_id' => $loan['user_id'],
        'title' => $action === 'approve' ? 'Loan request approved' : 'Loan request declined',
        'body' => $action === 'approve' ? 'Your approved loan is ready for you to review and accept.' : 'Your loan request was declined.',
        'payload' => json_encode(['loan_id' => (int) $loanId, 'approved_amount' => $approvedValue === null ? null : (float) $approvedValue], JSON_UNESCAPED_SLASHES),
    ]);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_loan_' . $action, 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'loan_id' => (int) $loanId, 'status' => $status, 'approved_amount' => $approvedValue === null ? null : (float) $approvedValue]);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Loan review service unavailable.'], 503);
}