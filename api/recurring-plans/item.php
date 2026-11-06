<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['PATCH', 'DELETE'], true)) {
    header('Allow: PATCH, DELETE');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$planId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($planId === false) {
    auth_respond(['ok' => false, 'message' => 'Choose a valid plan.'], 422);
}
$data = auth_request_data();
auth_require_csrf($data);

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'DELETE' || ($data['action'] ?? '') === 'remove') {
        $statement = $pdo->prepare(
            'UPDATE recurring_investments SET status = \'cancelled\' WHERE id = :id AND user_id = :user_id AND status <> \'cancelled\''
        );
        $statement->execute(['id' => $planId, 'user_id' => $user['id']]);
    } elseif (($data['action'] ?? '') === 'toggle') {
        $statement = $pdo->prepare(
            'UPDATE recurring_investments SET status = IF(status = \'active\', \'paused\', \'active\') '
            . 'WHERE id = :id AND user_id = :user_id AND status IN (\'active\', \'paused\')'
        );
        $statement->execute(['id' => $planId, 'user_id' => $user['id']]);
    } else {
        auth_respond(['ok' => false, 'message' => 'Choose a valid plan action.'], 422);
    }
    if ($statement->rowCount() === 0) {
        auth_respond(['ok' => false, 'message' => 'Plan not found or no longer active.'], 404);
    }
    auth_respond(['ok' => true]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Could not update the recurring plan.'], 503);
}