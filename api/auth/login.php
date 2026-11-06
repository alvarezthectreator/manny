<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
require __DIR__ . '/../mail.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '' || strlen($password) > 4096) {
    auth_respond(['ok' => false, 'message' => 'Email or password is incorrect.'], 401);
}

try {
    $pdo = auth_database();
    $ip = auth_client_ip();
    if ($ip !== null) {
        $rateLimit = $pdo->prepare(
            'SELECT COUNT(*) FROM security_events WHERE event_type = \'login\' AND outcome = \'failure\' '
            . 'AND ip_address = INET6_ATON(:ip_address) AND occurred_at >= DATE_SUB(UTC_TIMESTAMP(6), INTERVAL 15 MINUTE)'
        );
        $rateLimit->execute(['ip_address' => $ip]);
        if ((int) $rateLimit->fetchColumn() >= 10) {
            auth_respond(['ok' => false, 'message' => 'Too many attempts. Wait 15 minutes and try again.'], 429);
        }
    }

    $statement = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE email = :email LIMIT 1');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    if (!$user || $user['status'] !== 'active' || !password_verify($password, $user['password_hash'])) {
        auth_record_security_event($pdo, isset($user['id']) ? (int) $user['id'] : null, 'login', 'failure');
        auth_respond(['ok' => false, 'message' => 'Email or password is incorrect.'], 401);
    }

    $userId = (int) $user['id'];
    $adminContext = ($_GET['context'] ?? '') === 'admin';
    if ($adminContext) {
        $adminCheck = $pdo->prepare('SELECT 1 FROM admin_users WHERE user_id = :user_id LIMIT 1');
        $adminCheck->execute(['user_id' => $userId]);
        if (!$adminCheck->fetchColumn()) {
            auth_record_security_event($pdo, $userId, 'admin_login', 'blocked');
            auth_respond(['ok' => false, 'message' => 'Administrator access is not enabled for this account.'], 403);
        }
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $rehash = $pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :user_id');
        $rehash->execute(['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'user_id' => $userId]);
    }
    $update = $pdo->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP(6) WHERE id = :user_id');
    $update->execute(['user_id' => $userId]);
    $clearNotifications = $pdo->prepare('UPDATE notifications SET read_at = UTC_TIMESTAMP(6) WHERE user_id = :user_id AND read_at IS NULL');
    if (!$adminContext) {
        $clearNotifications->execute(['user_id' => $userId]);
    }
    auth_record_security_event($pdo, $userId, 'login', 'success');
    auth_establish_session($pdo, $userId);
    cladvest_send_login_email($email, (string) (auth_client_ip() ?? ''));
    auth_respond(['ok' => true, 'user' => ['id' => $userId, 'email' => $email], 'redirect' => $adminContext ? 'admin.html' : 'dashboard.html']);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Sign-in is unavailable. Check that MAMP and MySQL are running.'], 503);
}