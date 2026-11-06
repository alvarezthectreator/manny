<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'DELETE'], true)) {
    header('Allow: GET, DELETE');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'DELETE') {
        $data = auth_request_data();
        auth_require_csrf($data);
        $statement = $pdo->prepare('UPDATE notifications SET read_at = UTC_TIMESTAMP(6) WHERE user_id = :user_id AND read_at IS NULL');
        $statement->execute(['user_id' => $user['id']]);
        auth_respond(['ok' => true, 'dismissed' => $statement->rowCount()]);
    }

    $statement = $pdo->prepare(
        'SELECT id, notification_type, title, body, action_url, created_at FROM notifications '
        . 'WHERE user_id = :user_id AND read_at IS NULL ORDER BY created_at DESC, id DESC LIMIT 20'
    );
    $statement->execute(['user_id' => $user['id']]);
    $notifications = array_map(static fn (array $row): array => [
        'id' => (int) $row['id'],
        'type' => $row['notification_type'],
        'title' => $row['title'],
        'body' => $row['body'],
        'action_url' => $row['action_url'],
        'created_at' => $row['created_at'],
    ], $statement->fetchAll());
    auth_respond(['ok' => true, 'notifications' => $notifications]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Notification service unavailable.'], 503);
}