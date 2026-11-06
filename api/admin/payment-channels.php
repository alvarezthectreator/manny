<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'PATCH', 'DELETE'], true)) {
    header('Allow: GET, PATCH, DELETE');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    if ($method === 'GET') {
        $statement = $pdo->query(
            'SELECT id, channel_key, display_name, channel_type, details, instructions, deposit_enabled, withdrawal_enabled, is_active, updated_at '
            . 'FROM payment_channels ORDER BY display_name'
        );
        $channels = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'channel_key' => $row['channel_key'],
            'display_name' => $row['display_name'],
            'channel_type' => $row['channel_type'],
            'details' => $row['details'] === null ? [] : (json_decode($row['details'], true) ?: []),
            'instructions' => $row['instructions'],
            'deposit_enabled' => (bool) $row['deposit_enabled'],
            'withdrawal_enabled' => (bool) $row['withdrawal_enabled'],
            'is_active' => (bool) $row['is_active'],
            'updated_at' => $row['updated_at'],
        ], $statement->fetchAll());
        auth_respond(['ok' => true, 'channels' => $channels]);
    }

    $data = auth_request_data();
    admin_require_write($data);
    $channelId = filter_var($data['channel_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($method === 'DELETE') {
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($channelId === false || $channelId === null || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            auth_respond(['ok' => false, 'message' => 'Choose a payment method and provide an audit reason.'], 422);
        }

        $pdo->beginTransaction();
        $select = $pdo->prepare(
            'SELECT channel_key, display_name, channel_type, details, instructions, deposit_enabled, withdrawal_enabled, is_active '
            . 'FROM payment_channels WHERE id = :id FOR UPDATE'
        );
        $select->execute(['id' => $channelId]);
        $before = $select->fetch();
        if (!$before) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'Payment method not found.'], 404);
        }
        $before['details'] = $before['details'] === null ? [] : (json_decode($before['details'], true) ?: []);
        $pdo->prepare('DELETE FROM payment_channels WHERE id = :id')->execute(['id' => $channelId]);
        admin_record_audit($pdo, (int) $admin['id'], 'payment_channel_deleted', 'payment_channel', (int) $channelId, $before, [
            'deleted' => true,
            'reason' => $reason,
        ]);
        auth_record_security_event($pdo, (int) $admin['id'], 'admin_payment_channel_deleted', 'success');
        $pdo->commit();
        auth_respond(['ok' => true, 'channel_id' => (int) $channelId, 'deleted' => true]);
    }

    $displayName = trim((string) ($data['display_name'] ?? ''));
    $channelType = trim((string) ($data['channel_type'] ?? ''));
    $details = $data['details'] ?? null;
    $instructions = trim((string) ($data['instructions'] ?? ''));
    $depositEnabled = $data['deposit_enabled'] ?? null;
    $withdrawalEnabled = $data['withdrawal_enabled'] ?? null;
    $isActive = $data['is_active'] ?? null;
    $reason = trim((string) ($data['reason'] ?? ''));
    $invalidDetailsShape = !is_array($details) || count($details) > 0 && array_is_list($details);
    if ($channelId === false || $channelId === null || $displayName === '' || mb_strlen($displayName) > 100
        || !in_array($channelType, ['bank_transfer', 'zelle', 'crypto', 'other'], true)
        || $invalidDetailsShape
        || mb_strlen($instructions) > 4000 || !is_bool($depositEnabled) || !is_bool($withdrawalEnabled) || !is_bool($isActive)
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        auth_respond(['ok' => false, 'message' => 'Enter valid payment channel settings and an audit reason.'], 422);
    }

    $pdo->beginTransaction();
    $select = $pdo->prepare(
        'SELECT channel_key, display_name, channel_type, details, instructions, deposit_enabled, withdrawal_enabled, is_active '
        . 'FROM payment_channels WHERE id = :id FOR UPDATE'
    );
    $select->execute(['id' => $channelId]);
    $before = $select->fetch();
    if (!$before) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Payment method not found.'], 404);
    }
    $allowedDetailFields = match ($before['channel_key']) {
        'bank_transfer' => ['bank_name', 'account_name', 'routing_number', 'account_number', 'reference'],
        'zelle' => ['recipient', 'account_name', 'reference'],
        'cash_app' => ['cash_app_id'],
        'crypto_deposit', 'crypto_wallet' => ['network', 'asset', 'wallet_address', 'memo'],
        default => ['recipient', 'reference'],
    };
    $normalizedDetails = [];
    foreach ($details as $key => $value) {
        if (!is_string($key) || !in_array($key, $allowedDetailFields, true) || !is_string($value) || mb_strlen($value) > 1000) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'Enter valid payment details for this method.'], 422);
        }
        $normalizedDetails[$key] = trim($value);
    }
    $detailsJson = json_encode($normalizedDetails, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $update = $pdo->prepare(
        'UPDATE payment_channels SET display_name = :display_name, channel_type = :channel_type, details = :details, instructions = :instructions, '
        . 'deposit_enabled = :deposit_enabled, withdrawal_enabled = :withdrawal_enabled, is_active = :is_active, updated_by = :admin_id '
        . 'WHERE id = :id'
    );
    $update->execute([
        'display_name' => $displayName,
        'channel_type' => $channelType,
        'details' => $detailsJson,
        'instructions' => $instructions,
        'deposit_enabled' => $depositEnabled ? 1 : 0,
        'withdrawal_enabled' => $withdrawalEnabled ? 1 : 0,
        'is_active' => $isActive ? 1 : 0,
        'admin_id' => $admin['id'],
        'id' => $channelId,
    ]);
    $after = [
        'channel_key' => $before['channel_key'],
        'display_name' => $displayName,
        'channel_type' => $channelType,
        'details' => $normalizedDetails,
        'instructions' => $instructions,
        'deposit_enabled' => $depositEnabled,
        'withdrawal_enabled' => $withdrawalEnabled,
        'is_active' => $isActive,
        'reason' => $reason,
    ];
    admin_record_audit($pdo, (int) $admin['id'], 'payment_channel_updated', 'payment_channel', (int) $channelId, $before, $after);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_payment_channel_updated', 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'channel_id' => (int) $channelId, 'channel' => $after]);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Payment channel settings service unavailable.'], 503);
}