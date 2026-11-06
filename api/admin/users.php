<?php

declare(strict_types=1);

require __DIR__ . '/common.php';
auth_require_method('PATCH');

try {
    $pdo = auth_database();
    $admin = admin_require_user($pdo);
    $data = auth_request_data();
    admin_require_write($data);
    $userId = filter_var($data['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $firstName = trim((string) ($data['first_name'] ?? ''));
    $lastName = trim((string) ($data['last_name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $status = trim((string) ($data['status'] ?? 'active'));
    $phoneCountryCode = trim((string) ($data['phone_country_code'] ?? ''));
    $phoneNumber = trim((string) ($data['phone_number'] ?? ''));
    $countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
    $region = trim((string) ($data['region'] ?? ''));
    $timezone = trim((string) ($data['timezone'] ?? 'UTC'));
    $locale = trim((string) ($data['locale'] ?? 'en'));
    $newPassword = (string) ($data['new_password'] ?? '');
    $reason = trim((string) ($data['reason'] ?? ''));
    $invalidPhoneCountryCode = $phoneCountryCode !== '' && !preg_match('/^\+?[0-9]{1,7}$/', $phoneCountryCode);
    $invalidPhoneNumber = $phoneNumber !== '' && !preg_match('/^[0-9+(). \-]{1,32}$/', $phoneNumber);
    $invalidCountryCode = $countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode);
    $invalidNewPassword = $newPassword !== '' && (strlen($newPassword) < 12 || strlen($newPassword) > 4096);
    if ($userId === false || $userId === null || $firstName === '' || mb_strlen($firstName) > 80
        || $lastName === '' || mb_strlen($lastName) > 80 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !in_array($status, ['active', 'locked', 'closed'], true)
        || $invalidPhoneCountryCode || $invalidPhoneNumber || $invalidCountryCode
        || mb_strlen($region) > 120 || $timezone === '' || mb_strlen($timezone) > 64
        || !in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)
        || !preg_match('/^[A-Za-z0-9][A-Za-z0-9-]{0,15}$/', $locale)
        || $invalidNewPassword
        || mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
        auth_respond(['ok' => false, 'message' => 'Enter valid account details and an audit reason. New passwords must be at least 12 characters.'], 422);
    }

    $pdo->beginTransaction();
    $beforeQuery = $pdo->prepare(
        'SELECT u.email, u.status, p.first_name, p.last_name, p.phone_country_code, p.phone_number, '
        . 'p.country_code, p.region, p.timezone, p.locale FROM users u '
        . 'LEFT JOIN user_profiles p ON p.user_id = u.id WHERE u.id = :user_id FOR UPDATE'
    );
    $beforeQuery->execute(['user_id' => $userId]);
    $before = $beforeQuery->fetch();
    if (!$before) {
        $pdo->rollBack();
        auth_respond(['ok' => false, 'message' => 'User not found.'], 404);
    }
    if ($newPassword !== '') {
        $updateUser = $pdo->prepare('UPDATE users SET email = :email, status = :status, password_hash = :password_hash WHERE id = :user_id');
        $updateUser->execute([
            'email' => $email,
            'status' => $status,
            'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'user_id' => $userId,
        ]);
        $revokeSessions = $pdo->prepare('UPDATE user_sessions SET revoked_at = UTC_TIMESTAMP(6) WHERE user_id = :user_id AND revoked_at IS NULL');
        $revokeSessions->execute(['user_id' => $userId]);
    } else {
        $updateUser = $pdo->prepare('UPDATE users SET email = :email, status = :status WHERE id = :user_id');
        $updateUser->execute(['email' => $email, 'status' => $status, 'user_id' => $userId]);
    }
    $updateProfile = $pdo->prepare(
        'INSERT INTO user_profiles (user_id, first_name, last_name, phone_country_code, phone_number, country_code, region, timezone, locale) '
        . 'VALUES (:user_id, :first_name, :last_name, :phone_country_code, :phone_number, :country_code, :region, :timezone, :locale) '
        . 'ON DUPLICATE KEY UPDATE first_name = VALUES(first_name), last_name = VALUES(last_name), '
        . 'phone_country_code = VALUES(phone_country_code), phone_number = VALUES(phone_number), country_code = VALUES(country_code), '
        . 'region = VALUES(region), timezone = VALUES(timezone), locale = VALUES(locale)'
    );
    $updateProfile->execute([
        'user_id' => $userId,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_country_code' => $phoneCountryCode === '' ? null : $phoneCountryCode,
        'phone_number' => $phoneNumber === '' ? null : $phoneNumber,
        'country_code' => $countryCode === '' ? null : $countryCode,
        'region' => $region === '' ? null : $region,
        'timezone' => $timezone,
        'locale' => $locale,
    ]);
    $after = [
        'email' => $email,
        'status' => $status,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'phone_country_code' => $phoneCountryCode,
        'phone_number' => $phoneNumber,
        'country_code' => $countryCode,
        'region' => $region,
        'timezone' => $timezone,
        'locale' => $locale,
        'password_reset' => $newPassword !== '',
        'reason' => $reason,
    ];
    admin_record_audit($pdo, (int) $admin['id'], 'user_profile_updated', 'user', (int) $userId, $before, $after);
    auth_record_security_event($pdo, (int) $admin['id'], 'admin_user_profile_updated', 'success');
    if ($newPassword !== '') {
        admin_record_audit($pdo, (int) $admin['id'], 'user_password_reset', 'user', (int) $userId, null, ['sessions_revoked' => true, 'reason' => $reason]);
        auth_record_security_event($pdo, (int) $userId, 'admin_password_reset', 'success');
    }
    $pdo->commit();
    auth_respond(['ok' => true, 'user_id' => (int) $userId, 'profile' => $after]);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => $error->getCode() === '23000' ? 'That email is already in use.' : 'User update failed.'], $error->getCode() === '23000' ? 409 : 503);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'User update service unavailable.'], 503);
}