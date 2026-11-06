<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
require __DIR__ . '/../mail.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

$email = strtolower(trim((string) ($data['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    auth_respond(['ok' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
}

try {
    $pdo = auth_database();
    $userQuery = $pdo->prepare('SELECT id, email FROM users WHERE email = :email AND status = \'active\' LIMIT 1');
    $userQuery->execute(['email' => $email]);
    $user = $userQuery->fetch();
    if ($user) {
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE user_tokens SET consumed_at = UTC_TIMESTAMP(6) WHERE user_id = :user_id AND token_type = \'password_reset\' AND consumed_at IS NULL')
            ->execute(['user_id' => $user['id']]);
        $rawToken = bin2hex(random_bytes(32));
        $tokenInsert = $pdo->prepare(
            'INSERT INTO user_tokens (user_id, token_type, token_hash, expires_at) '
            . 'VALUES (:user_id, \'password_reset\', :token_hash, DATE_ADD(UTC_TIMESTAMP(6), INTERVAL 1 HOUR))'
        );
        $tokenInsert->execute(['user_id' => $user['id'], 'token_hash' => hash('sha256', $rawToken)]);
        auth_record_security_event($pdo, (int) $user['id'], 'password_reset_requested', 'success');
        $pdo->commit();
        $baseUrl = rtrim((string) (getenv('CLADVEST_APP_URL') ?: 'http://localhost:8888/manuel'), '/');
        cladvest_send_password_reset_email($user['email'], $baseUrl . '/reset-password.html?token=' . rawurlencode($rawToken));
    }
    auth_respond(['ok' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => true, 'message' => 'If an account exists for that email, a reset link has been sent.']);
}