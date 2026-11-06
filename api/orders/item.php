<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
auth_require_method('PATCH');

$orderId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($orderId === false || $orderId === null) {
    auth_respond(['ok' => false, 'message' => 'A valid order is required.'], 422);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $data = auth_request_data();
    auth_require_csrf($data);
    if (($data['action'] ?? '') !== 'cancel') {
        auth_respond(['ok' => false, 'message' => 'Unsupported order action.'], 422);
    }
    $statement = $pdo->prepare(
        'UPDATE investment_orders SET status = \'cancelled\' '
        . 'WHERE id = :id AND user_id = :user_id AND status = \'draft\''
    );
    $statement->execute(['id' => $orderId, 'user_id' => $user['id']]);
    if ($statement->rowCount() === 0) {
        $check = $pdo->prepare('SELECT status FROM investment_orders WHERE id = :id AND user_id = :user_id');
        $check->execute(['id' => $orderId, 'user_id' => $user['id']]);
        $status = $check->fetchColumn();
        if ($status === false) {
            auth_respond(['ok' => false, 'message' => 'Order not found.'], 404);
        }
        if ($status !== 'cancelled') {
            auth_respond(['ok' => false, 'message' => 'Only unsubmitted drafts can be cancelled.'], 409);
        }
    }
    auth_respond(['ok' => true, 'order_id' => $orderId, 'status' => 'cancelled']);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Order service unavailable.'], 503);
}