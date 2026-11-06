<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

$token = trim((string) ($data['token'] ?? ''));
$password = (string) ($data['password'] ?? '');
if (!preg_match('/^[a-f0-9]{64}$/i', $token) || strlen($password) < 12 || strlen($password) > 4096) {
    auth_respond(['ok' => false, 'message' => 'This reset link is invalid or the password does not meet the requirements.'], 422);
}

try {
    $pdo = auth_database();
    $pdo->beginTransaction();
    $tokenQuery = $pdo->prepare(
        'SELECT t.id, t.user_id FROM user_tokens t JOIN users u ON u.id = t.user_id '
        . 'WHERE t.token_type = \'password_reset\' AND t.token_hash = :token_hash '
        . 'AND t.consumed_at IS NULL AND t.expires_at > UTC_TIMESTAMP(6) AND u.status = \'active\' LIMIT 1 FOR UPDATE'
    );
    $tokenQuery->execute(['token_hash' => hash('sha256', hex2bin($token))]);
    $tokenRow = $tokenQuery->fetch();
    if (!$tokenRow) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'This reset link is invalid or expired.'], 422);
    }
    $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id')
        ->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $tokenRow['user_id']]);
    $pdo->prepare('UPDATE user_tokens SET consumed_at = UTC_TIMESTAMP(6) WHERE id = :id')->execute(['id' => $tokenRow['id']]);
    $pdo->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(6) WHERE user_id = :user_id AND revoked_at IS NULL')
        ->execute(['user_id' => $tokenRow['user_id']]);
    auth_record_security_event($pdo, (int) $tokenRow['user_id'], 'password_reset_completed', 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'message' => 'Password updated. You can now sign in.']);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Password reset is unavailable. Try again later.'], 503);
}