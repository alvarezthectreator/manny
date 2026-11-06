<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('GET');

try {
    $user = auth_current_user(auth_database());
    if (!$user) {
        auth_respond(['ok' => false, 'message' => 'Sign-in required.'], 401);
    }
    auth_respond(['ok' => true, 'user' => $user]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Authentication service unavailable.'], 503);
}