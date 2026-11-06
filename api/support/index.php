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
            'SELECT c.id, c.subject, c.category, c.status, c.updated_at, '
            . '(SELECT m.message_text FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC, m.id DESC LIMIT 1) AS last_message '
            . 'FROM support_conversations c WHERE c.user_id = :user_id ORDER BY c.updated_at DESC, c.id DESC'
        );
        $statement->execute(['user_id' => $user['id']]);
        $conversations = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'subject' => $row['subject'],
            'category' => $row['category'],
            'status' => $row['status'],
            'updated_at' => $row['updated_at'],
            'last_message' => $row['last_message'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'conversations' => $conversations]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $subject = trim((string) ($data['subject'] ?? ''));
    $message = trim((string) ($data['message'] ?? ''));
    $category = trim((string) ($data['category'] ?? 'general'));
    $categories = ['general', 'account', 'loans', 'payments', 'technical', 'security'];
    if ($subject === '' || mb_strlen($subject) > 180 || $message === '' || mb_strlen($message) > 5000 || !in_array($category, $categories, true)) {
        auth_respond(['ok' => false, 'message' => 'Enter a subject and message, and choose a supported category.'], 422);
    }

    $pdo->beginTransaction();
    $conversation = $pdo->prepare(
        'INSERT INTO support_conversations (user_id, subject, category, status) '
        . 'VALUES (:user_id, :subject, :category, \'waiting_for_support\')'
    );
    $conversation->execute(['user_id' => $user['id'], 'subject' => $subject, 'category' => $category]);
    $conversationId = (int) $pdo->lastInsertId();
    $insertMessage = $pdo->prepare(
        'INSERT INTO support_messages (conversation_id, sender_user_id, sender_type, message_text) '
        . 'VALUES (:conversation_id, :user_id, \'customer\', :message_text)'
    );
    $insertMessage->execute([
        'conversation_id' => $conversationId,
        'user_id' => $user['id'],
        'message_text' => $message,
    ]);
    $messageId = (int) $pdo->lastInsertId();
    $pdo->commit();

    auth_respond(['ok' => true, 'conversation_id' => $conversationId, 'message_id' => $messageId], 201);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Support service unavailable.'], 503);
}