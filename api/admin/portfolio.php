<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['POST', 'PATCH'], true)) {
    header('Allow: POST, PATCH');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    $data = auth_request_data();
    admin_require_write($data);
    if ($method === 'POST') {
        $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $symbol = strtoupper(trim((string) ($data['symbol'] ?? '')));
        $quantity = (string) ($data['quantity'] ?? '');
        $averageCost = (string) ($data['average_cost'] ?? '');
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($userId === false || $userId === null || !preg_match('/^[A-Z0-9._-]{1,24}$/', $symbol)
            || !preg_match('/^\d{1,12}(?:\.\d{1,12})?$/', $quantity) || (float) $quantity <= 0
            || !preg_match('/^\d{1,12}(?:\.\d{1,10})?$/', $averageCost) || (float) $averageCost <= 0
            || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            auth_respond(['ok' => false, 'message' => 'Enter a user, supported asset, positive quantity and cost, and audit reason.'], 422);
        }

        $pdo->beginTransaction();
        $userQuery = $pdo->prepare('SELECT id FROM users WHERE id = :id AND status = \'active\'');
        $userQuery->execute(['id' => $userId]);
        if (!$userQuery->fetchColumn()) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'Active user not found.'], 404);
        }
        $accountQuery = $pdo->prepare(
            'SELECT id FROM financial_accounts WHERE user_id = :user_id AND account_type = \'investment\' '
            . 'AND status = \'active\' ORDER BY id LIMIT 1 FOR UPDATE'
        );
        $accountQuery->execute(['user_id' => $userId]);
        $accountId = $accountQuery->fetchColumn();
        if (!$accountId) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'User has no active investment account.'], 409);
        }
        $assetQuery = $pdo->prepare('SELECT id FROM assets WHERE symbol = :symbol AND status = \'active\' LIMIT 1');
        $assetQuery->execute(['symbol' => $symbol]);
        $assetId = $assetQuery->fetchColumn();
        if (!$assetId) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'Active asset not found.'], 404);
        }
        $existing = $pdo->prepare('SELECT id FROM investment_positions WHERE account_id = :account_id AND asset_id = :asset_id');
        $existing->execute(['account_id' => $accountId, 'asset_id' => $assetId]);
        if ($existing->fetchColumn()) {
            $pdo->rollBack();
            auth_respond(['ok' => false, 'message' => 'A position already exists for this asset. Edit the existing position instead.'], 409);
        }
        $costBasis = number_format((float) $quantity * (float) $averageCost, 8, '.', '');
        $insert = $pdo->prepare(
            'INSERT INTO investment_positions (account_id, asset_id, quantity, average_cost, cost_basis) '
            . 'VALUES (:account_id, :asset_id, :quantity, :average_cost, :cost_basis)'
        );
        $insert->execute([
            'account_id' => $accountId,
            'asset_id' => $assetId,
            'quantity' => $quantity,
            'average_cost' => $averageCost,
            'cost_basis' => $costBasis,
        ]);
        $positionId = (int) $pdo->lastInsertId();
        $after = ['user_id' => (int) $userId, 'symbol' => $symbol, 'quantity' => (float) $quantity, 'average_cost' => (float) $averageCost, 'cost_basis' => (float) $costBasis, 'reason' => $reason];
        admin_record_audit($pdo, (int) $admin['id'], 'portfolio_position_created', 'investment_position', $positionId, null, $after);
        auth_record_security_event($pdo, (int) $admin['id'], 'admin_portfolio_position_created', 'success');
        $pdo->commit();
        auth_respond(['ok' => true, 'position_id' => $positionId, 'position' => $after], 201);
    }

    $positionId = filter_var($data['position_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = (string) ($data['quantity'] ?? '');
    $averageCost = (string) ($data['average_cost'] ?? '');
    $reason = trim((string) ($data['reason'] ?? ''));
    if ($positionId === false || $positionId === null
        || !preg_match('/^\d{1,12}(?:\.\d{1,12})?$/', $quantity)
        || !preg_match('/^\d{1,12}(?:\.\d{1,10})?$/', $averageCost)
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        auth_respond(['ok' => false, 'message' => 'Enter a valid holding quantity, cost, and audit reason.'], 422);
    }

    $pdo->beginTransaction();
    $select = $pdo->prepare(
        'SELECT ip.id, ip.quantity, ip.average_cost, ip.cost_basis, fa.user_id, a.symbol '
        . 'FROM investment_positions ip JOIN financial_accounts fa ON fa.id = ip.account_id '
        . 'JOIN assets a ON a.id = ip.asset_id WHERE ip.id = :id FOR UPDATE'
    );
    $select->execute(['id' => $positionId]);
    $before = $select->fetch();
    if (!$before) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'Portfolio position not found.'], 404);
    }
    $quantityValue = (float) $quantity;
    $averageValue = (float) $averageCost;
    $costBasis = number_format($quantityValue * $averageValue, 8, '.', '');
    $update = $pdo->prepare(
        'UPDATE investment_positions SET quantity = :quantity, average_cost = :average_cost, cost_basis = :cost_basis WHERE id = :id'
    );
    $update->execute(['quantity' => $quantity, 'average_cost' => $averageCost, 'cost_basis' => $costBasis, 'id' => $positionId]);
    $after = ['quantity' => $quantityValue, 'average_cost' => $averageValue, 'cost_basis' => (float) $costBasis, 'reason' => $reason];
    admin_record_audit($pdo, (int) $admin['id'], 'portfolio_position_updated', 'investment_position', (int) $positionId, $before, $after);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_portfolio_updated', 'success');
    $pdo->commit();
    auth_respond(['ok' => true, 'position_id' => (int) $positionId, 'position' => $after]);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Portfolio update service unavailable.'], 503);
}