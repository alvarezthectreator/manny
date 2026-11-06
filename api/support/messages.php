<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

$conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($conversationId === false || $conversationId === null) {
    auth_respond(['ok' => false, 'message' => 'A valid conversation is required.'], 422);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    $conversationQuery = $pdo->prepare(
        'SELECT id, status FROM support_conversations WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $conversationQuery->execute(['id' => $conversationId, 'user_id' => $user['id']]);
    $conversation = $conversationQuery->fetch();
    if (!$conversation) {
        auth_respond(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }

    if ($method === 'GET') {
        $statement = $pdo->prepare(
            'SELECT m.id, m.sender_type, m.message_text, m.sent_at, u.email AS sender_email '
            . 'FROM support_messages m LEFT JOIN users u ON u.id = m.sender_user_id '
            . 'WHERE m.conversation_id = :conversation_id ORDER BY m.sent_at ASC, m.id ASC'
        );
        $statement->execute(['conversation_id' => $conversationId]);
        $messages = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'sender_type' => $row['sender_type'],
            'sender_email' => $row['sender_email'],
            'message' => $row['message_text'],
            'sent_at' => $row['sent_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'messages' => $messages]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $message = trim((string) ($data['message'] ?? ''));
    if ($message === '' || mb_strlen($message) > 5000) {
        auth_respond(['ok' => false, 'message' => 'Messages must contain between 1 and 5,000 characters.'], 422);
    }
    if (in_array($conversation['status'], ['resolved', 'closed'], true)) {
        auth_respond(['ok' => false, 'message' => 'This conversation is closed. Start a new conversation to continue.'], 409);
    }

    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO support_messages (conversation_id, sender_user_id, sender_type, message_text) '
        . 'VALUES (:conversation_id, :user_id, \'customer\', :message_text)'
    );
    $insert->execute([
        'conversation_id' => $conversationId,
        'user_id' => $user['id'],
        'message_text' => $message,
    ]);
    $messageId = (int) $pdo->lastInsertId();
    $pdo->prepare('UPDATE support_conversations SET status = \'waiting_for_support\' WHERE id = :id')
        ->execute(['id' => $conversationId]);
    $pdo->commit();

    auth_respond(['ok' => true, 'message_id' => $messageId], 201);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Support service unavailable.'], 503);
}