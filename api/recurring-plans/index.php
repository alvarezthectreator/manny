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
        $pdo->beginTransaction();
        $due = $pdo->prepare(
            'SELECT ri.id, a.symbol FROM recurring_investments ri JOIN assets a ON a.id = ri.asset_id '
            . 'WHERE ri.user_id = :user_id AND ri.status = \'active\' AND ri.next_run_at <= UTC_TIMESTAMP(6) FOR UPDATE'
        );
        $due->execute(['user_id' => $user['id']]);
        $complete = $pdo->prepare(
            'UPDATE recurring_investments SET status = \'completed\' '
            . 'WHERE id = :id AND user_id = :user_id AND status = \'active\' AND next_run_at <= UTC_TIMESTAMP(6)'
        );
        $notification = $pdo->prepare(
            'INSERT INTO notifications (user_id, notification_type, title, body, action_url, payload) '
            . 'VALUES (:user_id, \'investment_ended\', :title, :body, \'investment-detail.html\', :payload)'
        );
        foreach ($due->fetchAll() as $plan) {
            $complete->execute(['id' => $plan['id'], 'user_id' => $user['id']]);
            if ($complete->rowCount() > 0) {
                $notification->execute([
                    'user_id' => $user['id'],
                    'title' => 'Investment period ended',
                    'body' => $plan['symbol'] . ' simulation ended. No trade or profit was generated in this demo.',
                    'payload' => json_encode(['plan_id' => (int) $plan['id'], 'symbol' => $plan['symbol']], JSON_UNESCAPED_SLASHES),
                ]);
            }
        }
        $pdo->commit();

        $statement = $pdo->prepare(
            'SELECT ri.id, a.symbol, a.name, ri.amount, ri.currency, ri.frequency, ri.next_run_at, ri.status '
            . 'FROM recurring_investments ri JOIN assets a ON a.id = ri.asset_id '
            . 'WHERE ri.user_id = :user_id AND ri.status <> \'cancelled\' ORDER BY ri.created_at DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $plans = array_map(static fn (array $plan): array => [
            'id' => (int) $plan['id'],
            'asset' => $plan['name'] . ' (' . $plan['symbol'] . ')',
            'symbol' => $plan['symbol'],
            'amount' => (float) $plan['amount'],
            'frequency' => $plan['frequency'] === 'semiannual' ? 'Biannually' : ucfirst($plan['frequency']),
            'nextRun' => $plan['next_run_at'],
            'endsAt' => $plan['next_run_at'],
            'status' => $plan['status'],
            'active' => $plan['status'] === 'active',
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'plans' => $plans]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
    $amount = (string) ($data['amount'] ?? '');
    $frequency = strtolower(trim((string) ($data['frequency'] ?? '')));
    $intervals = ['daily' => 'P1D', 'weekly' => 'P7D', 'biweekly' => 'P14D', 'monthly' => 'P1M', 'quarterly' => 'P3M', 'semiannual' => 'P6M'];
    if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) || (float) $amount < 1 || (float) $amount > 100000 || !isset($intervals[$frequency])) {
        auth_respond(['ok' => false, 'message' => 'Enter a valid amount and schedule frequency.'], 422);
    }
    $assetQuery = $pdo->prepare('SELECT id FROM assets WHERE symbol = :symbol AND status = \'active\' LIMIT 1');
    $assetQuery->execute(['symbol' => $symbol]);
    $assetId = $assetQuery->fetchColumn();
    if (!$assetId) {
        auth_respond(['ok' => false, 'message' => 'Choose a supported demo asset.'], 422);
    }
    $accountQuery = $pdo->prepare(
        'SELECT id FROM financial_accounts WHERE user_id = :user_id AND account_type = \'investment\' AND status = \'active\' ORDER BY id LIMIT 1'
    );
    $accountQuery->execute(['user_id' => $user['id']]);
    $accountId = $accountQuery->fetchColumn();
    if (!$accountId) {
        auth_respond(['ok' => false, 'message' => 'No active investment account is available.'], 409);
    }
    $nextRun = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->add(new DateInterval($intervals[$frequency]));
    $insert = $pdo->prepare(
        'INSERT INTO recurring_investments (user_id, account_id, asset_id, amount, currency, frequency, next_run_at) '
        . 'VALUES (:user_id, :account_id, :asset_id, :amount, \'USD\', :frequency, :next_run_at)'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'account_id' => $accountId,
        'asset_id' => $assetId,
        'amount' => number_format((float) $amount, 2, '.', ''),
        'frequency' => $frequency,
        'next_run_at' => $nextRun->format('Y-m-d H:i:s'),
    ]);
    auth_respond(['ok' => true, 'plan_id' => (int) $pdo->lastInsertId()], 201);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Recurring plan service unavailable.'], 503);
}