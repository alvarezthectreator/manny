<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$email = strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php database/grant_admin.php user@example.com\n");
    exit(2);
}

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $statement = $pdo->prepare('SELECT id FROM users WHERE email = :email AND status = \'active\' LIMIT 1');
    $statement->execute(['email' => $email]);
    $userId = $statement->fetchColumn();
    if (!$userId) {
        fwrite(STDERR, "No active account exists for that email.\n");
        exit(1);
    }

    $pdo->beginTransaction();
    $admin = $pdo->prepare('INSERT INTO admin_users (user_id, role) VALUES (:user_id, \'admin\') ON DUPLICATE KEY UPDATE role = \'admin\'');
    $admin->execute(['user_id' => $userId]);
    $support = $pdo->prepare('INSERT IGNORE INTO support_agents (user_id, is_active) VALUES (:user_id, 1)');
    $support->execute(['user_id' => $userId]);
    $pdo->commit();
    fwrite(STDOUT, "Admin access granted to {$email}. Sign in with this account, then open admin.html.\n");
} catch (Throwable) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Could not provision admin access. Check the database connection and migration.\n");
    exit(1);
}