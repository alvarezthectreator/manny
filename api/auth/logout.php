<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

try {
    if (isset($_SESSION['user_id'])) {
        $statement = auth_database()->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(6) WHERE token_hash = :token_hash AND revoked_at IS NULL');
        $statement->execute(['token_hash' => hash('sha256', session_id())]);
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
    }
    session_destroy();
    auth_respond(['ok' => true]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Could not end the session cleanly.'], 500);
}