<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';

function admin_require_user(PDO $pdo): array
{
    $user = auth_require_user($pdo);
    $statement = $pdo->prepare('SELECT role FROM admin_users WHERE user_id = :user_id');
    $statement->execute(['user_id' => $user['id']]);
    $role = $statement->fetchColumn();
    if (!is_string($role)) {
        auth_respond(['ok' => false, 'message' => 'Administrator access required.'], 403);
    }
    $user['admin_role'] = $role;
    return $user;
}

function admin_require_write(array $data): void
{
    auth_require_csrf($data);
}

function admin_record_audit(PDO $pdo, int $actorId, string $action, string $entityType, string|int|null $entityId, ?array $before, ?array $after): void
{
    $statement = $pdo->prepare(
        'INSERT INTO audit_events (actor_user_id, action, entity_type, entity_id, request_id, ip_address, before_data, after_data) '
        . 'VALUES (:actor_user_id, :action, :entity_type, :entity_id, :request_id, INET6_ATON(:ip_address), :before_data, :after_data)'
    );
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
    if (!is_string($requestId) || !preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
        $requestId = sprintf('%08x-%04x-4%03x-%04x-%012x', random_int(0, 0xffffffff), random_int(0, 0xffff), random_int(0, 0x0fff), random_int(0, 0x3fff), random_int(0, 0xffffffffffff));
    }
    $statement->execute([
        'actor_user_id' => $actorId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId === null ? null : (string) $entityId,
        'request_id' => $requestId,
        'ip_address' => auth_client_ip(),
        'before_data' => $before === null ? null : json_encode($before, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
        'after_data' => $after === null ? null : json_encode($after, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE),
    ]);
}

function admin_cash_account(PDO $pdo, int $userId, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(
        'SELECT id, ledger_account_id, currency FROM financial_accounts '
        . 'WHERE user_id = :user_id AND account_type IN (\'checking\', \'savings\') AND status = \'active\' '
        . 'ORDER BY id LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $statement->execute(['user_id' => $userId]);
    return $statement->fetch() ?: null;
}

function admin_cash_balance(PDO $pdo, int $ledgerAccountId, bool $forUpdate = false): float
{
    $statement = $pdo->prepare(
        'SELECT COALESCE(SUM(CASE entry_side WHEN \'debit\' THEN amount ELSE -amount END), 0) '
        . 'FROM ledger_entries WHERE ledger_account_id = :ledger_account_id' . ($forUpdate ? ' FOR UPDATE' : '')
    );
    $statement->execute(['ledger_account_id' => $ledgerAccountId]);
    return (float) $statement->fetchColumn();
}

function admin_clearing_account(PDO $pdo, int $userId, string $currency): int
{
    $accountCode = 'external-settlement-' . strtolower($currency);
    $statement = $pdo->prepare(
        'SELECT id FROM ledger_accounts WHERE owner_user_id = :user_id AND account_code = :account_code FOR UPDATE'
    );
    $statement->execute(['user_id' => $userId, 'account_code' => $accountCode]);
    $id = $statement->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    $insert = $pdo->prepare(
        'INSERT INTO ledger_accounts (owner_user_id, account_code, account_type, currency) '
        . 'VALUES (:user_id, :account_code, \'clearing\', :currency)'
    );
    $insert->execute(['user_id' => $userId, 'account_code' => $accountCode, 'currency' => $currency]);
    return (int) $pdo->lastInsertId();
}

function admin_post_cash_ledger_entries(PDO $pdo, int $transactionId, int $cashLedgerId, int $clearingLedgerId, string $direction, string $amount, string $currency): void
{
    $entries = $pdo->prepare(
        'INSERT INTO ledger_entries (transaction_id, ledger_account_id, entry_side, amount, currency) '
        . 'VALUES (:transaction_id, :ledger_account_id, :entry_side, :amount, :currency)'
    );
    $cashSide = $direction === 'credit' ? 'debit' : 'credit';
    $clearingSide = $direction === 'credit' ? 'credit' : 'debit';
    $entries->execute([
        'transaction_id' => $transactionId,
        'ledger_account_id' => $cashLedgerId,
        'entry_side' => $cashSide,
        'amount' => $amount,
        'currency' => $currency,
    ]);
    $entries->execute([
        'transaction_id' => $transactionId,
        'ledger_account_id' => $clearingLedgerId,
        'entry_side' => $clearingSide,
        'amount' => $amount,
        'currency' => $currency,
    ]);
}