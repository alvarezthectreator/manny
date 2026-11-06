<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

try {
    $config = require __DIR__ . '/../config/database.php';
    $pdo = new PDO($config['dsn'], $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $email = 'demo@cladvest.local';
    $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $existing->execute(['email' => $email]);
    if ($existing->fetch()) {
        fwrite(STDERR, "Demo account already exists; no changes were made.\n");
        exit(1);
    }

    $password = bin2hex(random_bytes(8)) . 'aA9!';
    $pdo->beginTransaction();
    $user = $pdo->prepare('INSERT INTO users (email, password_hash, status) VALUES (:email, :password_hash, \'active\')');
    $user->execute(['email' => $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();

    $profile = $pdo->prepare('INSERT INTO user_profiles (user_id, first_name, last_name) VALUES (:user_id, \'Demo\', \'User\')');
    $profile->execute(['user_id' => $userId]);
    $preferences = $pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (:user_id)');
    $preferences->execute(['user_id' => $userId]);

    foreach ([['cash-primary', 'cash', 'Cash', 'checking'], ['investment-primary', 'investment', 'Investments', 'investment']] as [$code, $ledgerType, $name, $accountType]) {
        $ledger = $pdo->prepare(
            'INSERT INTO ledger_accounts (owner_user_id, account_code, account_type, currency) '
            . 'VALUES (:user_id, :account_code, :account_type, \'USD\')'
        );
        $ledger->execute(['user_id' => $userId, 'account_code' => $code, 'account_type' => $ledgerType]);
        $account = $pdo->prepare(
            'INSERT INTO financial_accounts (user_id, ledger_account_id, account_name, account_type, currency) '
            . 'VALUES (:user_id, :ledger_id, :account_name, :account_type, \'USD\')'
        );
        $account->execute([
            'user_id' => $userId,
            'ledger_id' => $pdo->lastInsertId(),
            'account_name' => $name,
            'account_type' => $accountType,
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, "Demo account created.\nEmail: {$email}\nPassword: {$password}\n");
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Could not create the demo account: {$error->getMessage()}\n");
    exit(1);
}