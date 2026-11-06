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
            'SELECT g.id, g.name, g.target_amount, g.currency, g.target_date, g.status, '
            . 'COALESCE(SUM(c.amount), 0) AS current_amount '
            . 'FROM financial_goals g LEFT JOIN goal_contributions c ON c.goal_id = g.id '
            . 'WHERE g.user_id = :user_id GROUP BY g.id ORDER BY g.created_at DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $goals = array_map(static fn (array $goal): array => [
            'id' => (int) $goal['id'],
            'name' => $goal['name'],
            'target' => (float) $goal['target_amount'],
            'current' => (float) $goal['current_amount'],
            'dueDate' => $goal['target_date'],
            'status' => $goal['status'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'goals' => $goals]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $name = trim((string) ($data['name'] ?? ''));
    $target = (string) ($data['target'] ?? '');
    $dueDate = trim((string) ($data['due_date'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120 || !preg_match('/^\d+(?:\.\d{1,2})?$/', $target) || (float) $target < 1 || (float) $target > 10000000) {
        auth_respond(['ok' => false, 'message' => 'Enter a goal name and a target from $1 to $10,000,000.'], 422);
    }
    if ($dueDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        auth_respond(['ok' => false, 'message' => 'Enter a valid target date.'], 422);
    }
    $insert = $pdo->prepare(
        'INSERT INTO financial_goals (user_id, name, target_amount, currency, target_date) '
        . 'VALUES (:user_id, :name, :target_amount, \'USD\', :target_date)'
    );
    $insert->execute([
        'user_id' => $user['id'],
        'name' => $name,
        'target_amount' => number_format((float) $target, 2, '.', ''),
        'target_date' => $dueDate === '' ? null : $dueDate,
    ]);
    auth_respond(['ok' => true, 'goal_id' => (int) $pdo->lastInsertId()], 201);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Goal service unavailable.'], 503);
}