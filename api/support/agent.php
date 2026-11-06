<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'POST', 'PATCH'], true)) {
    header('Allow: GET, POST, PATCH');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $agent = auth_require_user($pdo);
    $access = $pdo->prepare('SELECT is_active FROM support_agents WHERE user_id = :user_id');
    $access->execute(['user_id' => $agent['id']]);
    if (!(bool) $access->fetchColumn()) {
        auth_respond(['ok' => false, 'message' => 'Support-agent access required.'], 403);
    }

    if ($method === 'GET') {
        $conversationId = filter_var($_GET['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($conversationId !== false && $conversationId !== null) {
            $conversation = $pdo->prepare(
                'SELECT c.id, c.user_id, c.subject, c.category, c.status, c.priority, c.assigned_agent_id, '
                . 'c.created_at, c.updated_at, u.email '
                . 'FROM support_conversations c JOIN users u ON u.id = c.user_id WHERE c.id = :id LIMIT 1'
            );
            $conversation->execute(['id' => $conversationId]);
            $row = $conversation->fetch();
            if (!$row) {
                auth_respond(['ok' => false, 'message' => 'Conversation not found.'], 404);
            }
            $messagesQuery = $pdo->prepare(
                'SELECT m.id, m.sender_type, m.message_text, m.sent_at, u.email AS sender_email '
                . 'FROM support_messages m LEFT JOIN users u ON u.id = m.sender_user_id '
                . 'WHERE m.conversation_id = :conversation_id ORDER BY m.sent_at ASC, m.id ASC'
            );
            $messagesQuery->execute(['conversation_id' => $conversationId]);
            $messages = array_map(static fn (array $message): array => [
                'id' => (int) $message['id'],
                'sender_type' => $message['sender_type'],
                'sender_email' => $message['sender_email'],
                'message' => $message['message_text'],
                'sent_at' => $message['sent_at'],
            ], $messagesQuery->fetchAll());
            auth_respond(['ok' => true, 'conversation' => [
                'id' => (int) $row['id'],
                'customer_email' => $row['email'],
                'subject' => $row['subject'],
                'category' => $row['category'],
                'status' => $row['status'],
                'priority' => $row['priority'],
                'assigned_to_me' => (int) $row['assigned_agent_id'] === (int) $agent['id'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ], 'messages' => $messages]);
        }

        $status = trim((string) ($_GET['status'] ?? ''));
        $statuses = ['open', 'waiting_for_user', 'waiting_for_support', 'resolved', 'closed'];
        if ($status !== '' && !in_array($status, $statuses, true)) {
            auth_respond(['ok' => false, 'message' => 'Choose a valid conversation status.'], 422);
        }
        $where = $status === ''
            ? 'WHERE c.status NOT IN (\'resolved\', \'closed\')'
            : 'WHERE c.status = :status';
        $statement = $pdo->prepare(
            'SELECT c.id, c.user_id, c.subject, c.category, c.status, c.priority, c.assigned_agent_id, c.updated_at, u.email, '
            . '(SELECT m.message_text FROM support_messages m WHERE m.conversation_id = c.id ORDER BY m.sent_at DESC, m.id DESC LIMIT 1) AS last_message '
            . 'FROM support_conversations c JOIN users u ON u.id = c.user_id '
            . $where
            . ' ORDER BY FIELD(c.priority, \'urgent\', \'high\', \'normal\', \'low\'), c.updated_at ASC LIMIT 100'
        );
        $statement->execute($status === '' ? [] : ['status' => $status]);
        $conversations = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
            'customer_email' => $row['email'],
            'subject' => $row['subject'],
            'category' => $row['category'],
            'status' => $row['status'],
            'priority' => $row['priority'],
            'assigned_to_me' => (int) $row['assigned_agent_id'] === (int) $agent['id'],
            'last_message' => $row['last_message'],
            'updated_at' => $row['updated_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'conversations' => $conversations]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    if ($method === 'POST' && !array_key_exists('conversation_id', $data)) {
        $recipientId = filter_var($data['recipient_user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $subject = trim((string) ($data['subject'] ?? 'Support follow-up'));
        $message = trim((string) ($data['message'] ?? ''));
        if ($recipientId === false || $recipientId === null || (int) $recipientId === (int) $agent['id']
            || $subject === '' || mb_strlen($subject) > 180 || $message === '' || mb_strlen($message) > 5000) {
            auth_respond(['ok' => false, 'message' => 'Choose another active user, enter a subject, and write a message.'], 422);
        }

        $recipientQuery = $pdo->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\' LIMIT 1');
        $recipientQuery->execute(['id' => $recipientId]);
        if (!$recipientQuery->fetchColumn()) {
            auth_respond(['ok' => false, 'message' => 'Active user not found.'], 404);
        }

        $pdo->beginTransaction();
        $conversationInsert = $pdo->prepare(
            'INSERT INTO support_conversations (user_id, subject, category, status, priority, assigned_agent_id) '
            . 'VALUES (:user_id, :subject, \'general\', \'waiting_for_user\', \'normal\', :agent_id)'
        );
        $conversationInsert->execute(['user_id' => $recipientId, 'subject' => $subject, 'agent_id' => $agent['id']]);
        $newConversationId = (int) $pdo->lastInsertId();
        $messageInsert = $pdo->prepare(
            'INSERT INTO support_messages (conversation_id, sender_user_id, sender_type, message_text) '
            . 'VALUES (:conversation_id, :sender_user_id, \'agent\', :message_text)'
        );
        $messageInsert->execute([
            'conversation_id' => $newConversationId,
            'sender_user_id' => $agent['id'],
            'message_text' => $message,
        ]);
        $notification = $pdo->prepare(
            'INSERT INTO notifications (user_id, notification_type, title, body, action_url, payload) '
            . 'VALUES (:user_id, \'support_message\', :title, :body, \'chat.html\', :payload)'
        );
        $notification->execute([
            'user_id' => $recipientId,
            'title' => 'New support message',
            'body' => $message,
            'payload' => json_encode(['conversation_id' => $newConversationId], JSON_UNESCAPED_SLASHES),
        ]);
        $audit = $pdo->prepare(
            'INSERT INTO audit_events (actor_user_id, action, entity_type, entity_id, after_data) '
            . 'VALUES (:actor_id, \'support_conversation_started\', \'support_conversation\', :entity_id, :after_data)'
        );
        $audit->execute([
            'actor_id' => $agent['id'],
            'entity_id' => (string) $newConversationId,
            'after_data' => json_encode(['recipient_user_id' => (int) $recipientId, 'subject' => $subject], JSON_UNESCAPED_SLASHES),
        ]);
        auth_record_security_event($pdo, (int) $agent['id'], 'support_conversation_started', 'success');
        $pdo->commit();
        auth_respond(['ok' => true, 'conversation_id' => $newConversationId, 'status' => 'waiting_for_user'], 201);
    }

    $conversationId = filter_var($data['conversation_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($conversationId === false || $conversationId === null) {
        auth_respond(['ok' => false, 'message' => 'A valid conversation is required.'], 422);
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare('SELECT id, status, assigned_agent_id FROM support_conversations WHERE id = :id FOR UPDATE');
    $lock->execute(['id' => $conversationId]);
    $conversation = $lock->fetch();
    if (!$conversation) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Conversation not found.'], 404);
    }
    if ($conversation['assigned_agent_id'] !== null && (int) $conversation['assigned_agent_id'] !== (int) $agent['id']) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Conversation is assigned to another agent.'], 409);
    }
    if ($method === 'POST' && in_array($conversation['status'], ['resolved', 'closed'], true)) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Reopen this conversation before updating it.'], 409);
    }

    if ($method === 'POST') {
        $message = trim((string) ($data['message'] ?? ''));
        if ($message === '' || mb_strlen($message) > 5000) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'Messages must contain between 1 and 5,000 characters.'], 422);
        }
        $insert = $pdo->prepare(
            'INSERT INTO support_messages (conversation_id, sender_user_id, sender_type, message_text) '
            . 'VALUES (:conversation_id, :user_id, \'agent\', :message_text)'
        );
        $insert->execute(['conversation_id' => $conversationId, 'user_id' => $agent['id'], 'message_text' => $message]);
        $update = $pdo->prepare(
            'UPDATE support_conversations SET assigned_agent_id = :agent_id, status = \'waiting_for_user\' WHERE id = :id'
        );
        $update->execute(['agent_id' => $agent['id'], 'id' => $conversationId]);
        $messageId = (int) $pdo->lastInsertId();
        $pdo->commit();
        auth_respond(['ok' => true, 'message_id' => $messageId, 'status' => 'waiting_for_user'], 201);
    }

    $status = trim((string) ($data['status'] ?? ''));
    if (!in_array($status, ['open', 'waiting_for_support', 'waiting_for_user', 'resolved', 'closed'], true)) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Choose a valid conversation status.'], 422);
    }
    $update = $pdo->prepare(
        'UPDATE support_conversations SET assigned_agent_id = :agent_id, status = :status, '
        . 'closed_at = CASE WHEN :is_closed = 1 THEN COALESCE(closed_at, UTC_TIMESTAMP(6)) ELSE NULL END WHERE id = :id'
    );
    $isClosed = in_array($status, ['resolved', 'closed'], true) ? 1 : 0;
    $update->execute(['agent_id' => $agent['id'], 'status' => $status, 'is_closed' => $isClosed, 'id' => $conversationId]);
    $pdo->commit();
    auth_respond(['ok' => true, 'conversation_id' => (int) $conversationId, 'status' => $status]);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Support-agent service unavailable.'], 503);
}