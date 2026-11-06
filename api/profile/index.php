<?php

declare(strict_types=1);

require __DIR__ . '/../auth/common.php';
$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'PUT'], true)) {
    header('Allow: GET, PUT');
    auth_respond(['ok' => false, 'message' => 'Method not allowed.'], 405);
}

try {
    $pdo = auth_database();
    $user = auth_require_user($pdo);
    if ($method === 'GET') {
        $payoutQuery = $pdo->prepare('SELECT withdrawal_wallet, withdrawal_details FROM user_profiles WHERE user_id = :user_id');
        $payoutQuery->execute(['user_id' => $user['id']]);
        $payout = $payoutQuery->fetch() ?: [];
        $details = is_string($payout['withdrawal_details'] ?? null) ? json_decode($payout['withdrawal_details'], true) : [];
        if (!is_array($details) || !$details) {
            $legacyWallet = trim((string) ($payout['withdrawal_wallet'] ?? ''));
            $details = $legacyWallet === '' ? [] : ['preferred_method' => 'crypto', 'crypto_wallet' => $legacyWallet];
        }
        $user['withdrawal_details'] = $details;
        auth_respond(['ok' => true, 'profile' => $user]);
    }

    $data = auth_request_data();
    auth_require_csrf($data);
    $name = trim((string) ($data['name'] ?? ''));
    $email = strtolower(trim((string) ($data['email'] ?? '')));
    $countryCode = strtoupper(trim((string) ($data['country_code'] ?? '')));
    $details = $data['withdrawal_details'] ?? [];
    $invalidDetailsShape = !is_array($details) || count($details) > 0 && array_is_list($details);
    $allowedDetailFields = [
        'preferred_method', 'bank_name', 'bank_account_name', 'bank_account_number',
        'bank_routing_number', 'bank_iban', 'bank_swift_bic', 'crypto_network', 'crypto_wallet',
    ];
    if (!$invalidDetailsShape) {
        foreach ($details as $key => $value) {
            if (!is_string($key) || !in_array($key, $allowedDetailFields, true) || !is_string($value)) {
                $invalidDetailsShape = true;
                break;
            }
        }
    }
    $normalizedDetails = [];
    if (!$invalidDetailsShape) {
        foreach ($allowedDetailFields as $field) {
            $value = trim((string) ($details[$field] ?? ''));
            $maxLength = match ($field) {
                'preferred_method' => 24,
                'bank_name' => 120,
                'bank_account_name' => 160,
                'bank_account_number' => 128,
                'bank_routing_number' => 64,
                'bank_iban' => 64,
                'bank_swift_bic' => 32,
                'crypto_network' => 120,
                'crypto_wallet' => 512,
            };
            if (mb_strlen($value) > $maxLength) {
                $invalidDetailsShape = true;
                break;
            }
            $normalizedDetails[$field] = $value;
        }
    }
    if ($name === '' || mb_strlen($name) > 160 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254
        || $invalidDetailsShape || !in_array($normalizedDetails['preferred_method'] ?? '', ['', 'bank_transfer', 'crypto'], true)) {
        auth_respond(['ok' => false, 'message' => 'Enter a name and a valid email address.'], 422);
    }
    if ($countryCode !== '' && !preg_match('/^[A-Z]{2}$/', $countryCode)) {
        auth_respond(['ok' => false, 'message' => 'Choose a valid country or region.'], 422);
    }

    $parts = preg_split('/\s+/', $name, 2) ?: [$name];
    $firstName = $parts[0];
    $lastName = $parts[1] ?? '';
    if ($lastName === '') {
        $lastName = $firstName;
    }

    $pdo->beginTransaction();
    $updateUser = $pdo->prepare('UPDATE users SET email = :email WHERE id = :user_id');
    $updateUser->execute(['email' => $email, 'user_id' => $user['id']]);
    $updateProfile = $pdo->prepare(
        'UPDATE user_profiles SET first_name = :first_name, last_name = :last_name, country_code = :country_code, '
        . 'withdrawal_wallet = :withdrawal_wallet, withdrawal_details = :withdrawal_details '
        . 'WHERE user_id = :user_id'
    );
    $updateProfile->execute([
        'first_name' => $firstName,
        'last_name' => $lastName,
        'country_code' => $countryCode === '' ? null : $countryCode,
        'withdrawal_wallet' => ($normalizedDetails['preferred_method'] ?? '') === 'crypto' && $normalizedDetails['crypto_wallet'] !== ''
            ? $normalizedDetails['crypto_wallet']
            : null,
        'withdrawal_details' => json_encode($normalizedDetails, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'user_id' => $user['id'],
    ]);
    $pdo->commit();

    auth_respond([
        'ok' => true,
        'profile' => [
            'id' => (int) $user['id'],
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'country_code' => $countryCode === '' ? null : $countryCode,
            'withdrawal_details' => $normalizedDetails,
        ],
    ]);
} catch (PDOException $error) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond([
        'ok' => false,
        'message' => $error->getCode() === '23000' ? 'An account with that email already exists.' : 'Could not save your profile.',
    ], $error->getCode() === '23000' ? 409 : 500);
} catch (Throwable) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    auth_respond(['ok' => false, 'message' => 'Profile service unavailable.'], 503);
}