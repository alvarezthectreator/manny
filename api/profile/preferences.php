<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'PATCH'], true)) {
    header('Allow: GET, PATCH');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'GET') {
        $statement = $pdo->prepare('SELECT email_updates, loan_reminders FROM user_preferences WHERE user_id = :user_id');
        $statement->execute(['user_id' => $user['id']]);
        $preferences = $statement->fetch() ?: ['email_updates' => 1, 'loan_reminders' => 1];
        auth_respond(['ok' => true, 'preferences' => [
            'email_updates' => (bool) $preferences['email_updates'],
            'loan_reminders' => (bool) $preferences['loan_reminders'],
        ]]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $hasEmailUpdates = array_key_exists('email_updates', $data);
    $hasLoanReminders = array_key_exists('loan_reminders', $data);
    if ((!$hasEmailUpdates && !$hasLoanReminders)
        || ($hasEmailUpdates && !is_bool($data['email_updates']))
        || ($hasLoanReminders && !is_bool($data['loan_reminders']))) {
        auth_respond(['ok' => false, 'message' => 'Choose valid account notification preferences.'], 422);
    }
    $currentQuery = $pdo->prepare('SELECT email_updates, loan_reminders FROM user_preferences WHERE user_id = :user_id');
    $currentQuery->execute(['user_id' => $user['id']]);
    $current = $currentQuery->fetch() ?: ['email_updates' => 1, 'loan_reminders' => 1];
    $emailUpdates = $hasEmailUpdates ? ($data['email_updates'] ? 1 : 0) : (int) $current['email_updates'];
    $loanReminders = $hasLoanReminders ? ($data['loan_reminders'] ? 1 : 0) : (int) $current['loan_reminders'];
    $statement = $pdo->prepare(
        'INSERT INTO user_preferences (user_id, email_updates, loan_reminders) '
        . 'VALUES (:user_id, :email_updates, :loan_reminders) '
        . 'ON DUPLICATE KEY UPDATE email_updates = VALUES(email_updates), loan_reminders = VALUES(loan_reminders)'
    );
    $statement->execute([
        'user_id' => $user['id'],
        'email_updates' => $emailUpdates,
        'loan_reminders' => $loanReminders,
    ]);
    auth_respond(['ok' => true, 'preferences' => [
        'email_updates' => (bool) $emailUpdates,
        'loan_reminders' => (bool) $loanReminders,
    ]]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Preference service unavailable.'], 503);
}