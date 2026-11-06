<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
require __DIR__ . '/../mail.php';
auth_require_method('POST');
$data = auth_request_data();
auth_require_csrf($data);

$firstName = trim((string) ($data['first_name'] ?? ''));
$lastName = trim((string) ($data['last_name'] ?? ''));
$email = strtolower(trim((string) ($data['email'] ?? '')));
$password = (string) ($data['password'] ?? '');
$phoneCountryCode = trim((string) ($data['phone_country_code'] ?? ''));
$phoneNumber = trim((string) ($data['phone_number'] ?? ''));
$countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
$region = trim((string) ($data['region'] ?? ''));
$invalidPhoneCountryCode = $phoneCountryCode !== '' && !preg_match('/^\+?[0-9]{1,7}$/', $phoneCountryCode);
$invalidPhoneNumber = $phoneNumber !== '' && !preg_match('/^[0-9+(). \-]{1,32}$/', $phoneNumber);
$invalidCountryCode = $countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode);
if ($firstName === '' || mb_strlen($firstName) > 80 || $lastName === '' || mb_strlen($lastName) > 80
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
    || $invalidPhoneCountryCode || $invalidPhoneNumber || $invalidCountryCode
    || mb_strlen($region) > 120) {
    auth_respond(['ok' => false, 'message' => 'Enter valid name and account details.'], 422);
}
if (strlen($password) < 12 || strlen($password) > 4096) {
    auth_respond(['ok' => false, 'message' => 'Use a password between 12 and 4096 characters.'], 422);
}

try {
    $pdo = auth_database();
    $pdo->beginTransaction();
    $insertUser = $pdo->prepare(
        'INSERT INTO users (email, password_hash, status) VALUES (:email, :password_hash, \'active\')'
    );
    $insertUser->execute([
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $profile = $pdo->prepare(
        'INSERT INTO user_profiles (user_id, first_name, last_name, phone_country_code, phone_number, country_code, region) '
        . 'VALUES (:user_id, :first_name, :last_name, :phone_country_code, :phone_number, :country_code, :region)'
    );
    $profile->execute([
        'user_id' => $userId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_country_code' => $phoneCountryCode === '' ? null : $phoneCountryCode,
        'phone_number' => $phoneNumber === '' ? null : $phoneNumber,
        'country_code' => $countryCode === '' ? null : $countryCode,
        'region' => $region === '' ? null : $region,
    ]);
    $preferences = $pdo->prepare('INSERT INTO user_preferences (user_id) VALUES (:user_id)');
    $preferences->execute(['user_id' => $userId]);

    foreach ([['cash-primary', 'cash', 'Cash'], ['investment-primary', 'investment', 'Investments']] as [$code, $type, $label]) {
        $ledger = $pdo->prepare(
            'INSERT INTO ledger_accounts (owner_user_id, account_code, account_type, currency) '
            . 'VALUES (:user_id, :account_code, :account_type, \'USD\')'
        );
        $ledger->execute(['user_id' => $userId, 'account_code' => $code, 'account_type' => $type]);
        $account = $pdo->prepare(
            'INSERT INTO financial_accounts (user_id, ledger_account_id, account_name, account_type, currency) '
            . 'VALUES (:user_id, :ledger_id, :account_name, :account_type, \'USD\')'
        );
        $account->execute([
            'user_id' => $userId,
            'ledger_id' => $pdo->lastInsertId(),
            'account_name' => $label,
            'account_type' => $type === 'cash' ? 'checking' : 'investment',
        ]);
    }
    auth_record_security_event($pdo, $userId, 'registration', 'success');
    $pdo->commit();

    cladvest_send_registration_email($email, $firstName);
    auth_establish_session($pdo, $userId);
    auth_respond(['ok' => true, 'user' => ['id' => $userId, 'email' => $email], 'redirect' => 'dashboard.html'], 201);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($error->getCode() === '23000') {
        auth_respond(['ok' => false, 'message' => 'An account with that email already exists.'], 409);
    }
    auth_respond(['ok' => false, 'message' => 'Account creation failed. Check the local database and try again.'], 500);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Account creation failed. Check the local database and try again.'], 500);
}