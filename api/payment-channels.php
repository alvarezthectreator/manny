<?php

declare(strict_types=1);

require __DIR__ . '/auth/common.php';
auth_require_method('GET');

$direction = strtolower(trim((string) ($_GET['direction'] ?? '')));
if (!in_array($direction, ['deposit', 'withdrawal'], true)) {
    auth_respond(['ok' => false, 'message' => 'Choose deposit or withdrawal.'], 422);
}

try {
    $pdo = auth_database();
    auth_require_user($pdo);
    $enabledField = $direction === 'deposit' ? 'deposit_enabled' : 'withdrawal_enabled';
    $statement = $pdo->query(
        'SELECT channel_key, display_name, channel_type, details, instructions FROM payment_channels '
        . 'WHERE is_active = 1 AND ' . $enabledField . ' = 1 ORDER BY display_name'
    );
    $channels = array_map(static fn (array $row): array => [
        'channel_key' => $row['channel_key'],
        'display_name' => $row['display_name'],
        'channel_type' => $row['channel_type'],
        'details' => $row['details'] === null ? [] : (json_decode($row['details'], true) ?: []),
        'instructions' => $row['instructions'],
    ], $statement->fetchAll());
    auth_respond(['ok' => true, 'direction' => $direction, 'channels' => $channels]);
} catch (Throwable) {
    auth_respond(['ok' => false, 'message' => 'Payment method service unavailable.'], 503);
}