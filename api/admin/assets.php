<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('PATCH');

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    $data = auth_request_data();
    admin_require_write($data);
    $assetId = filter_var($data['asset_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $name = trim((string) ($data['name'] ?? ''));
    $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
    $type = trim((string) ($data['asset_type'] ?? ''));
    $description = trim((string) ($data['description'] ?? ''));
    $status = trim((string) ($data['status'] ?? 'active'));
    $tradeable = $data['is_tradeable'] ?? false;
    $price = trim((string) ($data['price'] ?? ''));
    $reason = trim((string) ($data['reason'] ?? ''));
    $types = ['stock', 'etf', 'bond', 'fund', 'crypto', 'private_equity', 'other'];
    if ($assetId === false || $assetId === null || $name === '' || mb_strlen($name) > 160
        || !preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol) || !in_array($type, $types, true)
        || mb_strlen($description) > 5000 || !in_array($status, ['active', 'halted', 'delisted'], true)
        || !is_bool($tradeable) || ($price !== '' && (!preg_match('/^\d{1,12}(?:\.\d{1,10})?$/', $price) || (float) $price <= 0))
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        auth_respond(['ok' => false, 'message' => 'Enter valid asset details, optional positive quote, and an audit reason.'], 422);
    }
    if ($type === 'private_equity' && $tradeable) {
        auth_respond(['ok' => false, 'message' => 'Private assets cannot be marked tradeable in this system.'], 422);
    }

    $pdo->beginTransaction();
    $select = $pdo->prepare('SELECT symbol, name, asset_type, description, status, is_tradeable FROM assets WHERE id = :id FOR UPDATE');
    $select->execute(['id' => $assetId]);
    $before = $select->fetch();
    if (!$before) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Asset not found.'], 404);
    }
    $update = $pdo->prepare(
        'UPDATE assets SET symbol = :symbol, name = :name, asset_type = :asset_type, description = :description, '
        . 'status = :status, is_tradeable = :is_tradeable WHERE id = :id'
    );
    $update->execute([
        'symbol' => $symbol,
        'name' => $name,
        'asset_type' => $type,
        'description' => $description === '' ? null : $description,
        'status' => $status,
        'is_tradeable' => $tradeable ? 1 : 0,
        'id' => $assetId,
    ]);
    if ($price !== '') {
        $insertPrice = $pdo->prepare(
            'INSERT INTO asset_prices (asset_id, price, currency, price_source, observed_at) '
            . 'SELECT id, :price, quote_currency, \'admin-manual\', UTC_TIMESTAMP(6) FROM assets WHERE id = :asset_id'
        );
        $insertPrice->execute(['price' => $price, 'asset_id' => $assetId]);
    }
    $after = [
        'symbol' => $symbol,
        'name' => $name,
        'asset_type' => $type,
        'description' => $description,
        'status' => $status,
        'is_tradeable' => $tradeable,
        'price_added' => $price === '' ? null : (float) $price,
        'reason' => $reason,
    ];
    admin_record_audit($pdo, (int) $admin['id'], 'asset_updated', 'asset', (int) $assetId, $before, $after);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_asset_updated', 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'asset_id' => (int) $assetId, 'asset' => $after]);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => $error->getCode() === '23000' ? 'That symbol is already in use.' : 'Asset update failed.'], $error->getCode() === '23000' ? 409 : 503);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Asset update service unavailable.'], 503);
}